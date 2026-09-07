<?php

namespace Tests\Feature;

use App\Enums\Permission as PermissionEnum;
use App\Models\Department;
use App\Models\DepartmentPermission;
use App\Models\Examination;
use App\Models\Image;
use App\Models\Permission;
use App\Models\Transfer;
use App\Models\User;
use Database\Seeders\DepartmentPermissionSeeder;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class TransferAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Department $radiology;

    private Department $opd;

    private Department $nursing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            DepartmentSeeder::class,
            DepartmentPermissionSeeder::class,
            PermissionSeeder::class,
        ]);

        $this->radiology = Department::query()->where('code', 'RAD')->firstOrFail();
        $this->opd = Department::query()->where('code', 'OPD')->firstOrFail();
        $this->nursing = Department::query()->where('code', 'NURS')->firstOrFail();
    }

    public function test_radiology_staff_can_send_to_opd(): void
    {
        $user = $this->staffWithPermissions($this->radiology, [PermissionEnum::Send]);

        $this->assertTrue(Gate::forUser($user)->allows('send-transfer-to', $this->opd));
        $this->assertTrue($user->can('create', [Transfer::class, $this->opd]));
    }

    public function test_radiology_staff_can_send_to_nursing(): void
    {
        $user = $this->staffWithPermissions($this->radiology, [PermissionEnum::Send]);

        $this->assertTrue(Gate::forUser($user)->allows('send-transfer-to', $this->nursing));
    }

    public function test_opd_staff_cannot_send_to_radiology(): void
    {
        $user = $this->staffWithPermissions($this->opd, [PermissionEnum::Send]);

        $this->assertFalse(Gate::forUser($user)->allows('send-transfer-to', $this->radiology));
        $this->assertFalse($user->can('create', [Transfer::class, $this->radiology]));
    }

    public function test_staff_without_send_permission_cannot_send_even_when_matrix_allows(): void
    {
        $user = $this->staffWithPermissions($this->radiology, [PermissionEnum::ViewImages]);

        $this->assertFalse(Gate::forUser($user)->allows('send-transfer-to', $this->opd));
    }

    public function test_admin_can_send_to_any_active_department(): void
    {
        $admin = User::factory()->admin()->create([
            'department_id' => $this->radiology->id,
        ]);

        $this->assertTrue(Gate::forUser($admin)->allows('send-transfer-to', $this->opd));
        $this->assertTrue(Gate::forUser($admin)->allows('send-transfer-to', $this->nursing));
        $this->assertTrue($admin->can('create', [Transfer::class, $this->opd]));
    }

    public function test_inactive_destination_department_is_denied_for_staff(): void
    {
        $inactive = Department::factory()->create(['is_active' => false, 'code' => 'INA']);

        DepartmentPermission::query()->create([
            'from_department_id' => $this->radiology->id,
            'to_department_id' => $inactive->id,
            'can_send' => true,
            'can_receive' => true,
        ]);

        $user = $this->staffWithPermissions($this->radiology, [PermissionEnum::Send]);

        $this->assertFalse(Gate::forUser($user)->allows('send-transfer-to', $inactive));
    }

    public function test_opd_staff_can_receive_from_radiology(): void
    {
        $user = $this->staffWithPermissions($this->opd, [PermissionEnum::Receive]);

        $this->assertTrue(Gate::forUser($user)->allows('receive-transfer-from', $this->radiology));
    }

    public function test_transfer_policy_view_allows_sender_and_recipient_departments(): void
    {
        $sender = $this->staffWithPermissions($this->radiology, [PermissionEnum::ViewImages]);
        $recipient = $this->staffWithPermissions($this->opd, [PermissionEnum::ViewImages]);
        $other = $this->staffWithPermissions($this->nursing, [PermissionEnum::ViewImages]);

        $transfer = Transfer::factory()->create([
            'from_department_id' => $this->radiology->id,
        ]);

        $transfer->recipients()->create([
            'department_id' => $this->opd->id,
            'status' => 'pending',
        ]);

        $this->assertTrue($sender->can('view', $transfer));
        $this->assertTrue($recipient->can('view', $transfer));
        $this->assertFalse($other->can('view', $transfer));
    }

    public function test_image_policy_requires_department_access(): void
    {
        $sender = $this->staffWithPermissions($this->radiology, [PermissionEnum::ViewImages, PermissionEnum::Download]);
        $recipient = $this->staffWithPermissions($this->opd, [PermissionEnum::ViewImages, PermissionEnum::Download]);
        $other = $this->staffWithPermissions($this->nursing, [PermissionEnum::ViewImages, PermissionEnum::Download]);

        $examination = Examination::factory()->create([
            'referring_department_id' => $this->radiology->id,
        ]);

        $image = Image::factory()->create([
            'examination_id' => $examination->id,
        ]);

        $transfer = Transfer::factory()->create([
            'examination_id' => $examination->id,
            'from_department_id' => $this->radiology->id,
        ]);

        $transfer->recipients()->create([
            'department_id' => $this->opd->id,
            'status' => 'pending',
        ]);

        $this->assertTrue($sender->can('view', $image));
        $this->assertTrue($recipient->can('download', $image));
        $this->assertFalse($other->can('view', $image));
    }

    public function test_examination_policy_allows_referring_department(): void
    {
        $user = $this->staffWithPermissions($this->radiology, [PermissionEnum::ViewImages]);

        $examination = Examination::factory()->create([
            'referring_department_id' => $this->radiology->id,
        ]);

        $this->assertTrue($user->can('view', $examination));
    }

    /**
     * @param  list<PermissionEnum>  $permissions
     */
    private function staffWithPermissions(Department $department, array $permissions): User
    {
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
