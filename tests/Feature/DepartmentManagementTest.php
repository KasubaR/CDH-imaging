<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DepartmentPermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_departments(): void
    {
        $admin = $this->admin();
        $department = Department::factory()->create(['name' => 'Radiology']);

        $this->actingAs($admin)
            ->get(route('admin.departments.index'))
            ->assertOk()
            ->assertSee($department->name);
    }

    public function test_admin_can_create_a_department(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('admin.departments.store'), [
            'name' => 'Dental',
            'code' => 'DENT',
            'can_send' => '1',
            'can_receive' => '1',
        ]);

        $response->assertRedirect(route('admin.departments.index'));

        $this->assertDatabaseHas('departments', [
            'name' => 'Dental',
            'code' => 'DENT',
            'can_send' => true,
            'can_receive' => true,
            'is_active' => true,
        ]);
    }

    public function test_creating_a_department_with_a_duplicate_code_is_rejected(): void
    {
        $admin = $this->admin();
        Department::factory()->create(['code' => 'DENT']);

        $this->actingAs($admin)->post(route('admin.departments.store'), [
            'name' => 'Dental 2',
            'code' => 'DENT',
        ])->assertSessionHasErrors('code');

        $this->assertSame(1, Department::query()->where('code', 'DENT')->count());
    }

    public function test_admin_can_edit_a_department(): void
    {
        $admin = $this->admin();
        $department = Department::factory()->create(['name' => 'Old Name', 'can_send' => false]);

        $response = $this->actingAs($admin)->put(route('admin.departments.update', $department), [
            'name' => 'New Name',
            'code' => $department->code,
            'can_send' => '1',
        ]);

        $response->assertRedirect(route('admin.departments.index'));

        $department->refresh();
        $this->assertSame('New Name', $department->name);
        $this->assertTrue($department->can_send);
        $this->assertFalse($department->can_receive);
    }

    public function test_admin_can_deactivate_and_activate_a_department(): void
    {
        $admin = $this->admin();
        $department = Department::factory()->create(['is_active' => true]);

        $this->actingAs($admin)
            ->patch(route('admin.departments.deactivate', $department))
            ->assertRedirect(route('admin.departments.index'));

        $this->assertFalse($department->fresh()->is_active);

        $this->actingAs($admin)
            ->patch(route('admin.departments.activate', $department))
            ->assertRedirect(route('admin.departments.index'));

        $this->assertTrue($department->fresh()->is_active);
    }

    public function test_staff_cannot_access_department_administration(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->get(route('admin.departments.index'))
            ->assertForbidden();

        $this->actingAs($staff)
            ->post(route('admin.departments.store'), [])
            ->assertForbidden();
    }

    public function test_admin_can_view_the_transfer_matrix_with_existing_pairs_checked(): void
    {
        $admin = $this->admin();
        $from = Department::factory()->create(['name' => 'Radiology', 'is_active' => true]);
        $to = Department::factory()->create(['name' => 'OPD', 'is_active' => true]);

        DepartmentPermission::query()->create([
            'from_department_id' => $from->id,
            'to_department_id' => $to->id,
            'can_send' => true,
            'can_receive' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.departments.matrix'));

        $response->assertOk()
            ->assertSee($from->name)
            ->assertSee($to->name);
    }

    public function test_admin_can_enable_a_new_pair_in_the_transfer_matrix(): void
    {
        $admin = $this->admin();
        $from = Department::factory()->create(['is_active' => true]);
        $to = Department::factory()->create(['is_active' => true]);

        $this->actingAs($admin)->put(route('admin.departments.matrix.update'), [
            'matrix' => [
                $from->id => [
                    $to->id => '1',
                ],
            ],
        ])->assertRedirect(route('admin.departments.matrix'));

        $this->assertDatabaseHas('department_permissions', [
            'from_department_id' => $from->id,
            'to_department_id' => $to->id,
            'can_send' => true,
            'can_receive' => true,
        ]);
    }

    public function test_admin_can_disable_an_existing_pair_in_the_transfer_matrix(): void
    {
        $admin = $this->admin();
        $from = Department::factory()->create(['is_active' => true]);
        $to = Department::factory()->create(['is_active' => true]);

        DepartmentPermission::query()->create([
            'from_department_id' => $from->id,
            'to_department_id' => $to->id,
            'can_send' => true,
            'can_receive' => true,
        ]);

        // Submitting the matrix without this pair's checkbox unchecks/removes it.
        $this->actingAs($admin)->put(route('admin.departments.matrix.update'), [
            'matrix' => [],
        ])->assertRedirect(route('admin.departments.matrix'));

        $this->assertDatabaseMissing('department_permissions', [
            'from_department_id' => $from->id,
            'to_department_id' => $to->id,
        ]);
    }

    public function test_the_transfer_matrix_never_creates_a_self_to_self_pair(): void
    {
        $admin = $this->admin();
        $department = Department::factory()->create(['is_active' => true]);

        $this->actingAs($admin)->put(route('admin.departments.matrix.update'), [
            'matrix' => [
                $department->id => [
                    $department->id => '1',
                ],
            ],
        ]);

        $this->assertDatabaseMissing('department_permissions', [
            'from_department_id' => $department->id,
            'to_department_id' => $department->id,
        ]);
    }

    public function test_staff_cannot_access_the_transfer_matrix(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->get(route('admin.departments.matrix'))
            ->assertForbidden();
    }

    private function admin(): User
    {
        return User::factory()->admin()->create([
            'department_id' => Department::factory()->create(['is_active' => true])->id,
        ]);
    }
}
