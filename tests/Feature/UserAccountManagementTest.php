<?php

namespace Tests\Feature;

use App\Enums\Permission as PermissionEnum;
use App\Models\Department;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserAccountManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_accounts(): void
    {
        $admin = $this->admin();
        $staff = User::factory()->create(['name' => 'Radiology Staff']);

        $this->actingAs($admin)
            ->get(route('admin.accounts.index'))
            ->assertOk()
            ->assertSee($admin->name)
            ->assertSee($staff->name);
    }

    public function test_admin_can_create_a_department_account_with_permissions(): void
    {
        $admin = $this->admin();
        $department = Department::factory()->create(['is_active' => true]);
        $this->seedPermissions();

        $response = $this->actingAs($admin)->post(route('admin.accounts.store'), [
            'name' => 'New Staff',
            'username' => 'new.staff',
            'email' => 'new.staff@cdh.test',
            'role' => 'staff',
            'department_id' => $department->id,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'permissions' => [PermissionEnum::ViewImages->value, PermissionEnum::Upload->value],
        ]);

        $response->assertRedirect(route('admin.accounts.index'));

        $account = User::query()->where('username', 'new.staff')->sole();
        $this->assertSame('staff', $account->role);
        $this->assertSame($department->id, $account->department_id);
        $this->assertTrue($account->is_active);
        $this->assertTrue(Hash::check('password123', $account->password));

        $account->load('permissions');
        $this->assertSame(
            [PermissionEnum::Upload->value, PermissionEnum::ViewImages->value],
            $account->permissions->pluck('slug')->sort()->values()->all(),
        );
    }

    public function test_admin_can_create_a_system_administrator_account(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('admin.accounts.store'), [
            'name' => 'Second Admin',
            'username' => 'second.admin',
            'email' => 'second.admin@cdh.test',
            'role' => 'admin',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('admin.accounts.index'));

        $account = User::query()->where('username', 'second.admin')->sole();
        $this->assertSame('admin', $account->role);
        $this->assertNull($account->department_id);
    }

    public function test_creating_a_department_account_without_a_department_fails_validation(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.accounts.store'), [
            'name' => 'No Department',
            'username' => 'no.department',
            'email' => 'no.department@cdh.test',
            'role' => 'staff',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('department_id');

        $this->assertDatabaseMissing('users', ['username' => 'no.department']);
    }

    public function test_duplicate_username_is_rejected(): void
    {
        $admin = $this->admin();
        $existing = User::factory()->create(['username' => 'taken']);

        $this->actingAs($admin)->post(route('admin.accounts.store'), [
            'name' => 'Duplicate',
            'username' => 'taken',
            'email' => 'duplicate@cdh.test',
            'role' => 'admin',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('username');

        $this->assertSame(1, User::query()->where('username', 'taken')->count());
    }

    public function test_admin_can_edit_an_account_and_its_permissions(): void
    {
        $admin = $this->admin();
        $this->seedPermissions();
        $department = Department::factory()->create(['is_active' => true]);
        $otherDepartment = Department::factory()->create(['is_active' => true]);
        $account = User::factory()->create(['department_id' => $department->id, 'role' => 'staff']);
        $account->permissions()->sync(Permission::query()->where('slug', PermissionEnum::ViewImages->value)->pluck('id'));

        $response = $this->actingAs($admin)->put(route('admin.accounts.update', $account), [
            'name' => 'Renamed Staff',
            'username' => $account->username,
            'email' => $account->email,
            'role' => 'staff',
            'department_id' => $otherDepartment->id,
            'permissions' => [PermissionEnum::Send->value, PermissionEnum::Receive->value],
        ]);

        $response->assertRedirect(route('admin.accounts.index'));

        $account = $account->fresh(['permissions']);
        $this->assertSame('Renamed Staff', $account->name);
        $this->assertSame($otherDepartment->id, $account->department_id);
        $this->assertSame(
            [PermissionEnum::Receive->value, PermissionEnum::Send->value],
            $account->permissions->pluck('slug')->sort()->values()->all(),
        );
    }

    public function test_admin_can_deactivate_and_activate_another_account(): void
    {
        $admin = $this->admin();
        $account = User::factory()->create();

        $this->actingAs($admin)
            ->patch(route('admin.accounts.deactivate', $account))
            ->assertRedirect(route('admin.accounts.index'));

        $this->assertFalse($account->fresh()->is_active);

        $this->actingAs($admin)
            ->patch(route('admin.accounts.activate', $account))
            ->assertRedirect(route('admin.accounts.index'));

        $this->assertTrue($account->fresh()->is_active);
    }

    public function test_admin_cannot_deactivate_their_own_account(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->patch(route('admin.accounts.deactivate', $admin));

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_admin_cannot_demote_their_own_account_to_staff(): void
    {
        $admin = $this->admin();
        $department = Department::factory()->create(['is_active' => true]);

        $this->actingAs($admin)->put(route('admin.accounts.update', $admin), [
            'name' => $admin->name,
            'username' => $admin->username,
            'email' => $admin->email,
            'role' => 'staff',
            'department_id' => $department->id,
        ]);

        $this->assertSame('admin', $admin->fresh()->role);
    }

    public function test_admin_can_reset_another_accounts_password(): void
    {
        $admin = $this->admin();
        $account = User::factory()->create(['is_active' => true]);

        $this->actingAs($admin)->patch(route('admin.accounts.reset-password', $account), [
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertRedirect(route('admin.accounts.index'));

        $this->assertTrue(Hash::check('brand-new-password', $account->fresh()->password));
    }

    public function test_staff_cannot_access_account_administration(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->get(route('admin.accounts.index'))
            ->assertForbidden();

        $this->actingAs($staff)
            ->post(route('admin.accounts.store'), [])
            ->assertForbidden();
    }

    private function admin(): User
    {
        return User::factory()->admin()->create([
            'department_id' => Department::factory()->create(['is_active' => true])->id,
        ]);
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
