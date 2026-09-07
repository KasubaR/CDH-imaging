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

class ImageDownloadFilenameTest extends TestCase
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

    public function test_download_uses_patient_safe_filename(): void
    {
        $user = $this->staffWithPermissions([PermissionEnum::ViewImages, PermissionEnum::Upload, PermissionEnum::Download]);
        $patient = Patient::factory()->create(['patient_name' => 'John Banda']);
        $department = Department::query()->where('code', 'RAD')->firstOrFail();
        $type = ExaminationType::query()->where('code', 'CHEST')->firstOrFail();

        $examination = Examination::factory()->create([
            'patient_id' => $patient->id,
            'examination_type_id' => $type->id,
            'referring_department_id' => $department->id,
        ]);

        $this->actingAs($user)->post(route('examinations.images.store', $examination), [
            'images' => [UploadedFile::fake()->image('John_Banda_Xray.jpg', 40, 40)],
        ]);

        $image = $examination->images()->sole();
        $expected = 'XRAY_'.substr($image->uuid, 0, 8).'.jpg';

        $response = $this->actingAs($user)->get(route('images.download', $image));

        $response->assertOk();
        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment', $disposition);
        $this->assertStringContainsString($expected, $disposition);
        $this->assertStringNotContainsString('John', $disposition);
        $this->assertStringNotContainsString('Banda', $disposition);
        $this->assertStringNotContainsString('John_Banda_Xray.jpg', $disposition);
    }

    /**
     * @param  list<PermissionEnum>  $permissions
     */
    private function staffWithPermissions(array $permissions): User
    {
        $department = Department::query()->where('code', 'RAD')->firstOrFail();

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
