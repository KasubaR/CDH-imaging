<?php

namespace Tests\Feature;

use App\Enums\Permission as PermissionEnum;
use App\Enums\TransferRecipientStatus;
use App\Models\Department;
use App\Models\Examination;
use App\Models\ExaminationType;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\Transfer;
use App\Models\TransferRecipient;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DepartmentPermissionSeeder;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\ExaminationTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentDashboardTest extends TestCase
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

        Carbon::setTestNow('2026-09-03 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_department_dashboard_shows_live_transfer_kpis_and_recent_rows(): void
    {
        $opdStaff = $this->staffWithPermissions('OPD', [PermissionEnum::ViewImages, PermissionEnum::Receive]);
        $opd = Department::query()->where('code', 'OPD')->firstOrFail();
        $rad = Department::query()->where('code', 'RAD')->firstOrFail();

        $this->sendTransferTo($opd, 'Mwansa, John', $rad, TransferRecipientStatus::Delivered);
        $this->sendTransferTo($opd, 'Banda, Grace', $rad, TransferRecipientStatus::Pending);
        $received = $this->sendTransferTo($opd, 'Phiri, Mary', $rad, TransferRecipientStatus::Acknowledged);
        $received->recipients()->sole()->update(['acknowledged_at' => now()]);
        $completed = $this->sendTransferTo($opd, 'Tembo, Paul', $rad, TransferRecipientStatus::Completed);
        $completed->recipients()->sole()->update([
            'acknowledged_at' => now()->subDay(),
            'completed_at' => now(),
        ]);

        Transfer::factory()->create([
            'from_department_id' => $opd->id,
            'sent_by' => $opdStaff->id,
            'sent_at' => now(),
        ]);

        $response = $this->actingAs($opdStaff)->get(route('department.dashboard'));

        $response->assertOk();
        $response->assertSee('X-RAY TRANSFER');
        $response->assertSee('Recent Transfers');
        $response->assertSee('Mwansa, John');
        $response->assertSee('New');
        $response->assertSee('Received today');
        $response->assertSee('Sent today');
        $response->assertSee('Pending');
        $response->assertSee('Completed');

        $stats = $response->viewData('stats');
        $this->assertSame(2, $stats['new']);
        $this->assertSame(1, $stats['received_today']);
        $this->assertSame(1, $stats['sent_today']);
        $this->assertSame(1, $stats['pending']);
        $this->assertSame(1, $stats['completed']);

        $this->actingAs($opdStaff)
            ->get(route('department.index'))
            ->assertOk()
            ->assertDontSee('X-RAY TRANSFER', false);
    }

    private function sendTransferTo(
        Department $to,
        string $patientName,
        Department $from,
        TransferRecipientStatus $status,
    ): Transfer {
        $sender = User::factory()->create(['department_id' => $from->id, 'role' => 'staff']);
        $type = ExaminationType::query()->where('code', 'CHEST')->firstOrFail();
        $patient = Patient::factory()->create(['patient_name' => $patientName]);

        $examination = Examination::factory()->create([
            'patient_id' => $patient->id,
            'examination_type_id' => $type->id,
            'referring_department_id' => $from->id,
        ]);

        $transfer = Transfer::factory()->create([
            'examination_id' => $examination->id,
            'from_department_id' => $from->id,
            'sent_by' => $sender->id,
            'sent_at' => now(),
        ]);

        TransferRecipient::factory()->create([
            'transfer_id' => $transfer->id,
            'department_id' => $to->id,
            'status' => $status,
            'delivered_at' => $status === TransferRecipientStatus::Pending ? null : now(),
        ]);

        return $transfer->fresh('recipients');
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
