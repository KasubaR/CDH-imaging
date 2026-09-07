<?php

namespace Tests\Feature;

use App\Enums\Permission as PermissionEnum;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecureImageAccessTest extends TestCase
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

    public function test_images_are_resolved_by_uuid_not_numeric_id(): void
    {
        $user = $this->staffWithPermissions('RAD', [PermissionEnum::ViewImages, PermissionEnum::Upload]);
        $examination = $this->examinationForDepartment('RAD');

        $this->actingAs($user)->post(route('examinations.images.store', $examination), [
            'images' => [UploadedFile::fake()->image('AP.jpg', 40, 40)],
        ]);

        $image = $examination->images()->sole();

        $this->actingAs($user)
            ->get('/images/'.$image->uuid)
            ->assertOk();

        $this->actingAs($user)
            ->get('/images/'.$image->id)
            ->assertNotFound();
    }

    public function test_guests_cannot_view_images(): void
    {
        $image = Image::factory()->create();

        $this->get(route('images.show', $image))->assertRedirect('/login');
    }

    public function test_inactive_accounts_are_logged_out_before_image_access(): void
    {
        $user = $this->staffWithPermissions('RAD', [PermissionEnum::ViewImages, PermissionEnum::Upload]);
        $examination = $this->examinationForDepartment('RAD');

        $this->actingAs($user)->post(route('examinations.images.store', $examination), [
            'images' => [UploadedFile::fake()->image('AP.jpg', 40, 40)],
        ]);

        $image = $examination->images()->sole();
        $user->update(['is_active' => false]);

        $this->actingAs($user->fresh())
            ->get(route('images.show', $image))
            ->assertRedirect(route('login'));
    }

    public function test_rejected_recipient_department_cannot_view_the_image(): void
    {
        $sender = $this->staffWithPermissions('RAD', [PermissionEnum::ViewImages, PermissionEnum::Upload, PermissionEnum::Send]);
        $recipient = $this->staffWithPermissions('OPD', [PermissionEnum::ViewImages, PermissionEnum::Receive]);
        $opd = Department::query()->where('code', 'OPD')->firstOrFail();
        $examination = $this->examinationForDepartment('RAD');

        $this->actingAs($sender)->post(route('examinations.images.store', $examination), [
            'images' => [UploadedFile::fake()->image('AP.jpg', 40, 40)],
        ]);
        $image = $examination->images()->sole();

        $this->actingAs($sender)->post(route('examinations.transfers.store', $examination), [
            'department_ids' => [$opd->id],
        ]);

        $transfer = $examination->transfers()->sole();

        $this->actingAs($recipient)
            ->post(route('transfers.reject', $transfer), ['reason' => 'wrong_patient'])
            ->assertRedirect();

        $this->actingAs($recipient)
            ->get(route('images.show', $image))
            ->assertForbidden();
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
