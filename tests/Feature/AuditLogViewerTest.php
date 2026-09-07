<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogViewerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_the_audit_log(): void
    {
        $admin = $this->admin();
        $log = AuditLog::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.audit-logs.index'))
            ->assertOk()
            ->assertSee($log->account->name)
            ->assertSee($log->department->name);
    }

    public function test_staff_cannot_view_the_audit_log(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->get(route('admin.audit-logs.index'))
            ->assertForbidden();
    }

    public function test_audit_log_can_be_filtered_by_department(): void
    {
        $admin = $this->admin();
        $matching = AuditLog::factory()->create();
        $other = AuditLog::factory()->create();

        $response = $this->actingAs($admin)->get(route('admin.audit-logs.index', [
            'department_id' => $matching->department_id,
        ]));

        $response->assertOk()
            ->assertSee($matching->account->name)
            ->assertDontSee($other->account->name);
    }

    public function test_audit_log_can_be_filtered_by_action(): void
    {
        $admin = $this->admin();
        $department = Department::factory()->create();
        $account = User::factory()->create(['department_id' => $department->id, 'name' => 'Action Filter Tester']);

        AuditLog::factory()->create([
            'department_id' => $department->id,
            'account_id' => $account->id,
            'action' => AuditAction::Uploaded,
        ]);
        AuditLog::factory()->create([
            'department_id' => $department->id,
            'account_id' => $account->id,
            'action' => AuditAction::Downloaded,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.audit-logs.index', [
            'action' => AuditAction::Downloaded->value,
        ]));

        // The filter dropdown itself always lists every action label as an option,
        // so assert against the rendered row text (actor + action) rather than the
        // bare action label, which would also match the dropdown's own markup.
        $response->assertOk()
            ->assertSee($account->name.' '.AuditAction::Downloaded->label())
            ->assertDontSee($account->name.' '.AuditAction::Uploaded->label());
    }

    public function test_audit_log_search_matches_actor_name_or_username(): void
    {
        $admin = $this->admin();
        $department = Department::factory()->create();
        $actor = User::factory()->create(['department_id' => $department->id, 'name' => 'Jane Radiologist', 'username' => 'jane.rad']);
        $log = AuditLog::factory()->create(['department_id' => $department->id, 'account_id' => $actor->id]);
        $other = AuditLog::factory()->create();

        $response = $this->actingAs($admin)->get(route('admin.audit-logs.index', [
            'search' => 'Jane',
        ]));

        $response->assertOk()
            ->assertSee($log->account->name)
            ->assertDontSee($other->account->name);
    }

    public function test_a_system_recorded_event_with_no_actor_displays_as_system(): void
    {
        $admin = $this->admin();

        AuditLog::factory()->create([
            'department_id' => null,
            'account_id' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.audit-logs.index'))
            ->assertOk()
            ->assertSee('System');
    }

    private function admin(): User
    {
        return User::factory()->admin()->create([
            'department_id' => Department::factory()->create(['is_active' => true])->id,
        ]);
    }
}
