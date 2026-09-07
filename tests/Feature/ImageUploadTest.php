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

class ImageUploadTest extends TestCase
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

    public function test_staff_with_upload_can_upload_images_to_own_department_examination(): void
    {
        $user = $this->staffWithPermissions([PermissionEnum::ViewImages, PermissionEnum::Upload]);
        $examination = $this->examinationForDepartment('RAD');

        $files = [
            UploadedFile::fake()->image('AP.jpg', 100, 100)->size(2100),
            UploadedFile::fake()->image('Lateral.png', 100, 100)->size(1900),
        ];

        $response = $this->actingAs($user)
            ->post(route('examinations.images.store', $examination), [
                'images' => $files,
            ]);

        $response->assertRedirect(route('examinations.show', $examination));
        $this->assertDatabaseCount('images', 2);
        $this->assertDatabaseHas('images', [
            'examination_id' => $examination->id,
            'uploaded_by' => $user->id,
            'original_filename' => 'AP.jpg',
            'disk' => 'local',
        ]);

        $examination->refresh();
        foreach ($examination->images as $image) {
            $this->assertNotEmpty($image->uuid);
            $this->assertSame($image->uuid.'.'.pathinfo($image->storage_path, PATHINFO_EXTENSION), $image->stored_filename);
            $this->assertSame(64, strlen($image->checksum));
            $this->assertStringStartsWith('xrays/originals/', $image->storage_path);
            $this->assertNull($image->thumbnail_path);
            Storage::disk('local')->assertExists($image->storage_path);
        }
    }

    public function test_file_larger_than_15mb_is_rejected(): void
    {
        $user = $this->staffWithPermissions([PermissionEnum::ViewImages, PermissionEnum::Upload]);
        $examination = $this->examinationForDepartment('RAD');

        $response = $this->actingAs($user)
            ->post(route('examinations.images.store', $examination), [
                'images' => [UploadedFile::fake()->image('too-big.jpg')->size(15361)],
            ]);

        $response->assertSessionHasErrors('images.0');
        $this->assertDatabaseCount('images', 0);
    }

    public function test_non_image_file_is_rejected(): void
    {
        $user = $this->staffWithPermissions([PermissionEnum::ViewImages, PermissionEnum::Upload]);
        $examination = $this->examinationForDepartment('RAD');

        $response = $this->actingAs($user)
            ->post(route('examinations.images.store', $examination), [
                'images' => [UploadedFile::fake()->create('scan.pdf', 500, 'application/pdf')],
            ]);

        $response->assertSessionHasErrors('images.0');
        $this->assertDatabaseCount('images', 0);
    }

    public function test_staff_without_upload_permission_cannot_upload(): void
    {
        $user = $this->staffWithPermissions([PermissionEnum::ViewImages]);
        $examination = $this->examinationForDepartment('RAD');

        $this->actingAs($user)
            ->post(route('examinations.images.store', $examination), [
                'images' => [UploadedFile::fake()->image('AP.jpg')],
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('images', 0);
    }

    public function test_staff_cannot_upload_to_examination_outside_their_department(): void
    {
        $user = $this->staffWithPermissions([PermissionEnum::ViewImages, PermissionEnum::Upload]);
        $examination = $this->examinationForDepartment('OPD');

        $this->actingAs($user)
            ->post(route('examinations.images.store', $examination), [
                'images' => [UploadedFile::fake()->image('AP.jpg')],
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('images', 0);
    }

    public function test_admin_can_upload_to_any_examination(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $examination = $this->examinationForDepartment('OPD');

        $this->actingAs($admin)
            ->post(route('examinations.images.store', $examination), [
                'images' => [UploadedFile::fake()->image('AP.jpg')],
            ])
            ->assertRedirect(route('examinations.show', $examination));

        $this->assertDatabaseCount('images', 1);
    }

    public function test_svg_file_is_rejected(): void
    {
        $user = $this->staffWithPermissions([PermissionEnum::ViewImages, PermissionEnum::Upload]);
        $examination = $this->examinationForDepartment('RAD');

        $response = $this->actingAs($user)
            ->post(route('examinations.images.store', $examination), [
                'images' => [UploadedFile::fake()->create('logo.svg', 5, 'image/svg+xml')],
            ]);

        $response->assertSessionHasErrors('images.0');
        $this->assertDatabaseCount('images', 0);
    }

    public function test_zero_byte_file_is_rejected(): void
    {
        $user = $this->staffWithPermissions([PermissionEnum::ViewImages, PermissionEnum::Upload]);
        $examination = $this->examinationForDepartment('RAD');

        $response = $this->actingAs($user)
            ->post(route('examinations.images.store', $examination), [
                'images' => [UploadedFile::fake()->create('empty.jpg', 0, 'image/jpeg')],
            ]);

        $response->assertSessionHasErrors('images');
        $this->assertDatabaseCount('images', 0);
    }

    /**
     * The form request validates the client-declared extension and MIME type — but a
     * client can lie about both. This proves the independent, content-based check in
     * ImageController::verifyGenuineImage() (finfo + getimagesize on the real uploaded
     * bytes) catches what a filename/Content-Type-based check alone would miss.
     */
    public function test_non_image_content_disguised_with_an_image_extension_is_rejected(): void
    {
        $user = $this->staffWithPermissions([PermissionEnum::ViewImages, PermissionEnum::Upload]);
        $examination = $this->examinationForDepartment('RAD');

        $malicious = UploadedFile::fake()->createWithContent(
            'shell.jpg',
            '<?php system($_GET["cmd"]); ?>',
        );

        $response = $this->actingAs($user)
            ->post(route('examinations.images.store', $examination), [
                'images' => [$malicious],
            ]);

        $response->assertSessionHasErrors('images');
        $this->assertDatabaseCount('images', 0);
        Storage::disk('local')->assertDirectoryEmpty('xrays/originals');
    }

    public function test_stored_filename_is_server_generated_never_the_client_supplied_name(): void
    {
        $user = $this->staffWithPermissions([PermissionEnum::ViewImages, PermissionEnum::Upload]);
        $examination = $this->examinationForDepartment('RAD');

        $this->actingAs($user)
            ->post(route('examinations.images.store', $examination), [
                'images' => [UploadedFile::fake()->image('evil.php.jpg')],
            ])
            ->assertRedirect(route('examinations.show', $examination));

        $image = $examination->images()->sole();

        $this->assertSame('evil.php.jpg', $image->original_filename);
        $this->assertMatchesRegularExpression(
            '#^xrays/originals/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.jpg$#',
            $image->storage_path,
        );
        $this->assertStringNotContainsString('evil', $image->storage_path);
        $this->assertStringNotContainsString('php', $image->storage_path);
    }

    public function test_uploading_identical_file_content_twice_is_rejected_as_a_duplicate(): void
    {
        $user = $this->staffWithPermissions([PermissionEnum::ViewImages, PermissionEnum::Upload]);
        $examination = $this->examinationForDepartment('RAD');

        // Same pixel content, different filename — the checksum should still catch it.
        $original = UploadedFile::fake()->image('AP.jpg', 50, 50);
        $sameContentDifferentName = new UploadedFile(
            $original->getRealPath(),
            'AP-renamed-copy.jpg',
            'image/jpeg',
            null,
            true,
        );

        $this->actingAs($user)
            ->post(route('examinations.images.store', $examination), [
                'images' => [$original],
            ])
            ->assertRedirect(route('examinations.show', $examination));

        $this->assertDatabaseCount('images', 1);

        $response = $this->actingAs($user)
            ->post(route('examinations.images.store', $examination), [
                'images' => [$sameContentDifferentName],
            ]);

        $response->assertSessionHasErrors('images');
        $this->assertDatabaseCount('images', 1);
    }

    public function test_two_different_files_upload_successfully_with_distinct_checksums(): void
    {
        $user = $this->staffWithPermissions([PermissionEnum::ViewImages, PermissionEnum::Upload]);
        $examination = $this->examinationForDepartment('RAD');

        $this->actingAs($user)
            ->post(route('examinations.images.store', $examination), [
                'images' => [
                    UploadedFile::fake()->image('AP.jpg', 50, 50),
                    UploadedFile::fake()->image('Lateral.jpg', 80, 40),
                ],
            ])
            ->assertRedirect(route('examinations.show', $examination));

        $examination->refresh();
        $this->assertDatabaseCount('images', 2);
        $checksums = $examination->images->pluck('checksum');
        $this->assertNotSame($checksums->first(), $checksums->last());
    }

    public function test_authorized_user_can_view_an_uploaded_image_and_unauthorized_department_cannot(): void
    {
        $owner = $this->staffWithPermissions([PermissionEnum::ViewImages, PermissionEnum::Upload]);
        $examination = $this->examinationForDepartment('RAD');

        $this->actingAs($owner)
            ->post(route('examinations.images.store', $examination), [
                'images' => [UploadedFile::fake()->image('AP.jpg', 20, 20)],
            ]);

        $image = $examination->images()->sole();

        $this->actingAs($owner)
            ->get(route('images.show', $image))
            ->assertOk();

        $outsideDepartment = Department::query()->where('code', 'OPD')->firstOrFail();
        $outsider = User::factory()->create(['department_id' => $outsideDepartment->id, 'role' => 'staff']);
        $permissionIds = Permission::query()->where('slug', PermissionEnum::ViewImages->value)->pluck('id');
        $outsider->permissions()->sync($permissionIds);

        $this->actingAs($outsider->fresh(['permissions', 'department']))
            ->get(route('images.show', $image))
            ->assertForbidden();
    }

    public function test_thumbnail_is_generated_for_a_large_uploaded_image(): void
    {
        $user = $this->staffWithPermissions([PermissionEnum::ViewImages, PermissionEnum::Upload]);
        $examination = $this->examinationForDepartment('RAD');

        $this->actingAs($user)
            ->post(route('examinations.images.store', $examination), [
                'images' => [UploadedFile::fake()->image('AP.jpg', 800, 800)],
            ])
            ->assertRedirect(route('examinations.show', $examination));

        $image = $examination->images()->sole();

        $this->assertNotNull($image->thumbnail_path);
        $this->assertStringStartsWith('xrays/thumbnails/', $image->thumbnail_path);
        Storage::disk('local')->assertExists($image->thumbnail_path);

        // The original itself is completely unaffected by thumbnailing.
        Storage::disk('local')->assertExists($image->storage_path);

        $info = getimagesizefromstring(Storage::disk('local')->get($image->thumbnail_path));
        $this->assertSame('image/jpeg', $info['mime']);
        $this->assertLessThanOrEqual(500, max($info[0], $info[1]));
    }

    public function test_small_uploaded_image_has_no_thumbnail_and_serving_falls_back_to_the_original(): void
    {
        $user = $this->staffWithPermissions([PermissionEnum::ViewImages, PermissionEnum::Upload]);
        $examination = $this->examinationForDepartment('RAD');

        $this->actingAs($user)
            ->post(route('examinations.images.store', $examination), [
                'images' => [UploadedFile::fake()->image('AP.jpg', 50, 50)],
            ]);

        $image = $examination->images()->sole();
        $this->assertNull($image->thumbnail_path);

        $response = $this->actingAs($user)->get(route('images.thumbnail', $image));

        $response->assertOk();
        $this->assertSame(
            Storage::disk('local')->get($image->storage_path),
            $response->streamedContent(),
        );
    }

    public function test_thumbnail_route_enforces_the_same_department_authorization_as_the_original(): void
    {
        $owner = $this->staffWithPermissions([PermissionEnum::ViewImages, PermissionEnum::Upload]);
        $examination = $this->examinationForDepartment('RAD');

        $this->actingAs($owner)
            ->post(route('examinations.images.store', $examination), [
                'images' => [UploadedFile::fake()->image('AP.jpg', 800, 800)],
            ]);

        $image = $examination->images()->sole();

        $this->actingAs($owner)
            ->get(route('images.thumbnail', $image))
            ->assertOk();

        $outsideDepartment = Department::query()->where('code', 'OPD')->firstOrFail();
        $outsider = User::factory()->create(['department_id' => $outsideDepartment->id, 'role' => 'staff']);
        $permissionIds = Permission::query()->where('slug', PermissionEnum::ViewImages->value)->pluck('id');
        $outsider->permissions()->sync($permissionIds);

        $this->actingAs($outsider->fresh(['permissions', 'department']))
            ->get(route('images.thumbnail', $image))
            ->assertForbidden();
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
