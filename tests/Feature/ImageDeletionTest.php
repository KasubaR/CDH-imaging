<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\Permission as PermissionEnum;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Examination;
use App\Models\ExaminationType;
use App\Models\Image;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\User;
use Database\Seeders\DepartmentPermissionSeeder;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\ExaminationTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            DepartmentSeeder::class,
            DepartmentPermissionSeeder::class,
            PermissionSeeder::class,
            ExaminationTypeSeeder::class,
        ]);

        Storage::fake('local');
    }

    public function test_admin_can_view_delete_confirmation_page(): void
    {
        $admin = User::factory()->admin()->create();
        $image = $this->storedImage();

        $this->actingAs($admin)
            ->get(route('images.delete', $image))
            ->assertOk()
            ->assertSee($image->uuid)
            ->assertSee('PERMANENTLY DELETE');
    }

    public function test_staff_cannot_view_delete_confirmation_page(): void
    {
        $staff = $this->staffWithPermissions('RAD', [PermissionEnum::ViewImages]);
        $image = $this->storedImage();

        $this->actingAs($staff)
            ->get(route('images.delete', $image))
            ->assertForbidden();
    }

    public function test_admin_can_permanently_delete_image_with_confirmation(): void
    {
        $admin = User::factory()->admin()->create();
        $image = $this->storedImage(withThumbnail: true);
        $examination = $image->examination;
        $originalPath = $image->storage_path;
        $thumbnailPath = $image->thumbnail_path;

        Storage::disk('local')->assertExists($originalPath);
        Storage::disk('local')->assertExists($thumbnailPath);

        $this->actingAs($admin)
            ->delete(route('images.destroy', $image), [
                'confirmation' => 'PERMANENTLY DELETE',
            ])
            ->assertRedirect(route('examinations.show', $examination));

        $this->assertDatabaseMissing('images', ['id' => $image->id]);
        Storage::disk('local')->assertMissing($originalPath);
        Storage::disk('local')->assertMissing($thumbnailPath);

        $this->assertDatabaseHas('audit_logs', [
            'account_id' => $admin->id,
            'action' => AuditAction::Deleted->value,
            'subject_type' => $image->getMorphClass(),
            'subject_id' => $image->id,
        ]);
    }

    public function test_admin_delete_fails_without_correct_confirmation(): void
    {
        $admin = User::factory()->admin()->create();
        $image = $this->storedImage();

        $this->actingAs($admin)
            ->from(route('images.delete', $image))
            ->delete(route('images.destroy', $image), [
                'confirmation' => 'delete',
            ])
            ->assertRedirect(route('images.delete', $image))
            ->assertSessionHasErrors('confirmation');

        $this->assertDatabaseHas('images', ['id' => $image->id]);
        $this->assertSame(0, AuditLog::query()->where('action', AuditAction::Deleted)->count());
    }

    public function test_staff_cannot_destroy_image(): void
    {
        $staff = $this->staffWithPermissions('RAD', [PermissionEnum::ViewImages, PermissionEnum::Upload]);
        $image = $this->storedImage();

        $this->actingAs($staff)
            ->delete(route('images.destroy', $image), [
                'confirmation' => 'PERMANENTLY DELETE',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('images', ['id' => $image->id]);
    }

    public function test_guests_are_redirected_from_destroy(): void
    {
        $image = $this->storedImage();

        $this->delete(route('images.destroy', $image), [
            'confirmation' => 'PERMANENTLY DELETE',
        ])->assertRedirect('/login');
    }

    private function storedImage(bool $withThumbnail = false): Image
    {
        $examination = $this->examinationForDepartment('RAD');
        $uuid = fake()->uuid();
        $originalPath = 'xrays/originals/'.$uuid.'.jpg';
        $thumbnailPath = $withThumbnail ? 'xrays/thumbnails/'.$uuid.'.jpg' : null;

        Storage::disk('local')->put($originalPath, 'fake-original');

        if ($thumbnailPath !== null) {
            Storage::disk('local')->put($thumbnailPath, 'fake-thumb');
        }

        return Image::factory()->create([
            'examination_id' => $examination->id,
            'disk' => 'local',
            'uuid' => $uuid,
            'stored_filename' => $uuid.'.jpg',
            'storage_path' => $originalPath,
            'thumbnail_path' => $thumbnailPath,
        ]);
    }

    private function examinationForDepartment(string $departmentCode): Examination
    {
        $department = Department::query()->where('code', $departmentCode)->firstOrFail();
        $type = ExaminationType::query()->where('code', 'CHEST')->firstOrFail();

        return Examination::factory()->create([
            'patient_id' => Patient::factory()->create()->id,
            'examination_type_id' => $type->id,
            'referring_department_id' => $department->id,
        ]);
    }

    /**
     * @param  list<PermissionEnum>  $permissions
     */
    private function staffWithPermissions(string $departmentCode, array $permissions): User
    {
        $department = Department::query()->where('code', $departmentCode)->firstOrFail();

        $user = User::factory()->create([
            'department_id' => $department->id,
            'role' => 'staff',
        ]);

        $permissionIds = Permission::query()
            ->whereIn('slug', array_map(fn (PermissionEnum $permission) => $permission->value, $permissions))
            ->pluck('id');

        $user->permissions()->sync($permissionIds);

        return $user->fresh(['permissions', 'department']);
    }
}
