<?php

namespace Tests\Feature;

use App\Enums\Permission as PermissionEnum;
use App\Models\Department;
use App\Models\Examination;
use App\Models\ExaminationType;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\User;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\ExaminationTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            DepartmentSeeder::class,
            PermissionSeeder::class,
            ExaminationTypeSeeder::class,
        ]);

        Storage::fake('local');
    }

    public function test_staff_with_download_permission_can_download_the_original(): void
    {
        $user = $this->staffWithPermissions([PermissionEnum::ViewImages, PermissionEnum::Upload, PermissionEnum::Download]);
        $examination = $this->examinationForDepartment('RAD');

        $this->actingAs($user)
            ->post(route('examinations.images.store', $examination), [
                'images' => [UploadedFile::fake()->image('AP.jpg', 40, 40)],
            ]);

        $image = $examination->images()->sole();

        $response = $this->actingAs($user)->get(route('images.download', $image));

        $response->assertOk();
        $response->assertHeader('Content-Disposition');
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertSame(
            Storage::disk('local')->get($image->storage_path),
            $response->streamedContent(),
        );
    }

    public function test_staff_without_download_permission_is_forbidden(): void
    {
        $uploader = $this->staffWithPermissions([PermissionEnum::ViewImages, PermissionEnum::Upload]);
        $examination = $this->examinationForDepartment('RAD');

        $this->actingAs($uploader)
            ->post(route('examinations.images.store', $examination), [
                'images' => [UploadedFile::fake()->image('AP.jpg', 40, 40)],
            ]);

        $image = $examination->images()->sole();

        // Same user, same department, view permission only — still forbidden without `download`.
        $this->actingAs($uploader)
            ->get(route('images.download', $image))
            ->assertForbidden();
    }

    public function test_download_is_forbidden_for_a_user_outside_the_owning_department(): void
    {
        $uploader = $this->staffWithPermissions([PermissionEnum::ViewImages, PermissionEnum::Upload]);
        $examination = $this->examinationForDepartment('RAD');

        $this->actingAs($uploader)
            ->post(route('examinations.images.store', $examination), [
                'images' => [UploadedFile::fake()->image('AP.jpg', 40, 40)],
            ]);

        $image = $examination->images()->sole();

        $outsideDepartment = Department::query()->where('code', 'OPD')->firstOrFail();
        $outsider = $this->staffWithPermissions([PermissionEnum::ViewImages, PermissionEnum::Download], $outsideDepartment->code);

        $this->actingAs($outsider)
            ->get(route('images.download', $image))
            ->assertForbidden();
    }

    public function test_admin_can_always_download(): void
    {
        $uploader = $this->staffWithPermissions([PermissionEnum::ViewImages, PermissionEnum::Upload]);
        $examination = $this->examinationForDepartment('RAD');

        $this->actingAs($uploader)
            ->post(route('examinations.images.store', $examination), [
                'images' => [UploadedFile::fake()->image('AP.jpg', 40, 40)],
            ]);

        $image = $examination->images()->sole();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('images.download', $image))
            ->assertOk();
    }

    private function examinationForDepartment(string $departmentCode): Examination
    {
        $department = Department::query()->where('code', $departmentCode)->firstOrFail();
        $type = ExaminationType::query()->where('code', 'CHEST')->firstOrFail();
        $patient = Patient::factory()->create();

        return Examination::factory()->create([
            'patient_id' => $patient->id,
            'examination_type_id' => $type->id,
            'referring_department_id' => $department->id,
        ]);
    }

    /**
     * @param  list<PermissionEnum>  $permissions
     */
    private function staffWithPermissions(array $permissions, string $departmentCode = 'RAD'): User
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
