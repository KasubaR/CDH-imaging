<?php

namespace Tests\Feature;

use App\Enums\Permission as PermissionEnum;
use App\Models\Department;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionsOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_the_permissions_overview(): void
    {
        $admin = $this->admin();
        $this->seedPermissions();
        $staff = User::factory()->create(['name' => 'Overview Staff', 'role' => 'staff']);
        $staff->permissions()->sync(Permission::query()->where('slug', PermissionEnum::Upload->value)->pluck('id'));

        $response = $this->actingAs($admin)->get(route('admin.permissions.index'));

        $response->assertOk()
            ->assertSee($staff->name)
            ->assertSee(PermissionEnum::Upload->label());
    }

    public function test_admin_accounts_show_as_holding_every_permission_without_a_pivot_row(): void
    {
        $admin = $this->admin();
        $this->seedPermissions();
        $otherAdmin = User::factory()->admin()->create(['name' => 'Second Admin']);

        // Admin accounts never get explicit pivot rows (see UserController::update()).
        $this->assertSame(0, $otherAdmin->permissions()->count());

        $this->actingAs($admin)
            ->get(route('admin.permissions.index'))
            ->assertOk()
            ->assertSee('All permissions (administrator)');
    }

    public function test_a_staff_account_with_no_permissions_is_labelled_as_such(): void
    {
        $admin = $this->admin();
        $this->seedPermissions();
        User::factory()->create(['name' => 'No Permissions Staff', 'role' => 'staff']);

        $this->actingAs($admin)
            ->get(route('admin.permissions.index'))
            ->assertOk()
            ->assertSee('No permissions assigned');
    }

    public function test_overview_can_be_filtered_by_department(): void
    {
        $admin = $this->admin();
        $this->seedPermissions();
        $matchingDepartment = Department::factory()->create();
        $otherDepartment = Department::factory()->create();
        $matching = User::factory()->create(['name' => 'Matching Staff', 'department_id' => $matchingDepartment->id]);
        $other = User::factory()->create(['name' => 'Other Staff', 'department_id' => $otherDepartment->id]);

        $response = $this->actingAs($admin)->get(route('admin.permissions.index', [
            'department_id' => $matchingDepartment->id,
        ]));

        $response->assertOk()
            ->assertSee($matching->name)
            ->assertDontSee($other->name);
    }

    public function test_staff_cannot_view_the_permissions_overview(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->get(route('admin.permissions.index'))
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
