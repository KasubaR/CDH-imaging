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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The Medical Officer in Charge role: sees every department's images like an
 * admin (given the ViewImages/Download permissions), but none of the admin
 * management screens and none of admin's implicit upload/send/receive/
 * forward/delete abilities. See App\Enums\UserRole::Moic.
 */
class MoicRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_admin_can_create_a_moic_account_with_view_and_download_permissions(): void
    {
        $admin = $this->admin();
        $this->seedPermissions();

        $response = $this->actingAs($admin)->post(route('admin.accounts.store'), [
            'name' => 'Chief Medical Officer',
            'username' => 'cmo',
            'email' => 'cmo@cdh.test',
            'role' => 'moic',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'permissions' => [PermissionEnum::ViewImages->value, PermissionEnum::Download->value],
        ]);

        $response->assertRedirect(route('admin.accounts.index'));

        $account = User::query()->where('username', 'cmo')->sole();
        $this->assertSame('moic', $account->role);
        $this->assertNull($account->department_id);
        $this->assertTrue($account->isMoic());

        $account->load('permissions');
        $this->assertSame(
            [PermissionEnum::Download->value, PermissionEnum::ViewImages->value],
            $account->permissions->pluck('slug')->sort()->values()->all(),
        );
    }

    public function test_moic_can_view_and_download_an_image_in_a_department_it_has_no_affiliation_with(): void
    {
        $moic = $this->moic([PermissionEnum::ViewImages, PermissionEnum::Download]);
        $image = $this->imageInSomeOtherDepartment();

        $this->actingAs($moic)->get(route('images.show', $image))->assertOk();
        $this->actingAs($moic)->get(route('images.download', $image))->assertOk();
    }

    public function test_moic_can_view_an_examination_in_a_department_it_has_no_affiliation_with(): void
    {
        $moic = $this->moic([PermissionEnum::ViewImages]);
        $image = $this->imageInSomeOtherDepartment();

        $this->actingAs($moic)
            ->get(route('examinations.show', $image->examination))
            ->assertOk();
    }

    public function test_moic_without_view_images_permission_is_still_denied(): void
    {
        // MOIC is not a blanket bypass like admin — it still needs ViewImages
        // explicitly assigned, it just isn't limited to one department once granted.
        $moic = $this->moic([]);
        $image = $this->imageInSomeOtherDepartment();

        $this->actingAs($moic)->get(route('images.show', $image))->assertForbidden();
    }

    public function test_moic_cannot_upload_images(): void
    {
        $moic = $this->moic([PermissionEnum::ViewImages]);
        $image = $this->imageInSomeOtherDepartment();

        $this->actingAs($moic)
            ->get(route('examinations.images.create', $image->examination))
            ->assertForbidden();
    }

    public function test_moic_cannot_access_admin_management_screens(): void
    {
        $moic = $this->moic([PermissionEnum::ViewImages]);

        $this->actingAs($moic)->get(route('admin.accounts.index'))->assertForbidden();
        $this->actingAs($moic)->get(route('admin.departments.index'))->assertForbidden();
        $this->actingAs($moic)->get(route('admin.audit-logs.index'))->assertForbidden();
        $this->actingAs($moic)->get(route('admin.permissions.index'))->assertForbidden();
        $this->actingAs($moic)->get(route('admin.settings.edit'))->assertForbidden();
    }

    public function test_admin_cannot_demote_their_own_account_to_moic(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.accounts.update', $admin), [
            'name' => $admin->name,
            'username' => $admin->username,
            'email' => $admin->email,
            'role' => 'moic',
        ]);

        $this->assertSame('admin', $admin->fresh()->role);
    }

    private function admin(): User
    {
        return User::factory()->admin()->create([
            'department_id' => Department::factory()->create(['is_active' => true])->id,
        ]);
    }

    /**
     * @param  list<PermissionEnum>  $permissions
     */
    private function moic(array $permissions): User
    {
        $moic = User::factory()->create(['role' => 'moic', 'department_id' => null]);

        if ($permissions !== []) {
            $this->seedPermissions();
            $ids = Permission::query()
                ->whereIn('slug', array_map(fn (PermissionEnum $p) => $p->value, $permissions))
                ->pluck('id');
            $moic->permissions()->sync($ids);
        }

        return $moic->fresh(['permissions']);
    }

    private function imageInSomeOtherDepartment(): Image
    {
        $department = Department::factory()->create(['is_active' => true]);
        $patient = Patient::factory()->create();
        $examinationType = ExaminationType::factory()->create();
        $examination = Examination::factory()->create([
            'patient_id' => $patient->id,
            'examination_type_id' => $examinationType->id,
            'referring_department_id' => $department->id,
        ]);

        $image = Image::factory()->create(['examination_id' => $examination->id, 'disk' => 'local']);

        Storage::disk('local')->put($image->storage_path, 'fake-original-bytes');

        return $image;
    }

    private function seedPermissions(): void
    {
        foreach (PermissionEnum::cases() as $permission) {
            Permission::query()->firstOrCreate(
                ['slug' => $permission->value],
                ['name' => $permission->label()],
            );
        }
    }
}
