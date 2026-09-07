<?php

namespace Tests\Feature;

use App\Enums\Permission as PermissionEnum;
use App\Models\Department;
use App\Models\Examination;
use App\Models\ExaminationType;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\Transfer;
use App\Models\TransferRecipient;
use App\Models\User;
use Database\Seeders\DepartmentPermissionSeeder;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\ExaminationTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentInboxTest extends TestCase
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
    }

    public function test_inbox_only_shows_transfers_addressed_to_the_users_own_department(): void
    {
        $opdStaff = $this->staffWithPermissions('OPD', [PermissionEnum::ViewImages, PermissionEnum::Receive]);
        $opd = Department::query()->where('code', 'OPD')->firstOrFail();
        $nursing = Department::query()->where('code', 'NURS')->firstOrFail();

        $forOpd = $this->sendTransferTo($opd, 'Mwansa, John');
        $this->sendTransferTo($nursing, 'Banda, Grace');

        $response = $this->actingAs($opdStaff)->get(route('department.index'));

        $response->assertOk();
        $response->assertSee('Mwansa, John');
        $response->assertDontSee('Banda, Grace');
    }

    public function test_patient_filter_narrows_the_inbox(): void
    {
        $opdStaff = $this->staffWithPermissions('OPD', [PermissionEnum::ViewImages, PermissionEnum::Receive]);
        $opd = Department::query()->where('code', 'OPD')->firstOrFail();

        $this->sendTransferTo($opd, 'Mwansa, John');
        $this->sendTransferTo($opd, 'Banda, Grace');

        $response = $this->actingAs($opdStaff)->get(route('department.index', ['patient' => 'Mwansa']));

        $response->assertOk();
        $response->assertSee('Mwansa, John');
        $response->assertDontSee('Banda, Grace');
    }

    public function test_status_filter_narrows_the_inbox(): void
    {
        $opdStaff = $this->staffWithPermissions('OPD', [PermissionEnum::ViewImages, PermissionEnum::Receive]);
        $opd = Department::query()->where('code', 'OPD')->firstOrFail();

        $this->sendTransferTo($opd, 'Mwansa, John');
        $completed = $this->sendTransferTo($opd, 'Banda, Grace');
        $recipient = $completed->recipients()->sole();
        $recipient->update(['status' => 'completed', 'acknowledged_at' => now(), 'viewed_at' => now(), 'completed_at' => now()]);

        $newOnly = $this->actingAs($opdStaff)->get(route('department.index', ['status' => 'new']));
        $newOnly->assertSee('Mwansa, John');
        $newOnly->assertDontSee('Banda, Grace');

        $completedOnly = $this->actingAs($opdStaff)->get(route('department.index', ['status' => 'completed']));
        $completedOnly->assertSee('Banda, Grace');
        $completedOnly->assertDontSee('Mwansa, John');
    }

    public function test_sender_filter_narrows_the_inbox(): void
    {
        $opdStaff = $this->staffWithPermissions('OPD', [PermissionEnum::ViewImages, PermissionEnum::Receive]);
        $opd = Department::query()->where('code', 'OPD')->firstOrFail();
        $rad = Department::query()->where('code', 'RAD')->firstOrFail();
        $orth = Department::query()->where('code', 'ORTH')->firstOrFail();

        $this->sendTransferTo($opd, 'Mwansa, John', $rad);
        $this->sendTransferTo($opd, 'Banda, Grace', $orth);

        $response = $this->actingAs($opdStaff)->get(route('department.index', ['sender_department_id' => $rad->id]));

        $response->assertOk();
        $response->assertSee('Mwansa, John');
        $response->assertDontSee('Banda, Grace');
    }

    public function test_acknowledge_button_is_shown_for_new_transfers_and_open_for_others(): void
    {
        $opdStaff = $this->staffWithPermissions('OPD', [PermissionEnum::ViewImages, PermissionEnum::Receive]);
        $opd = Department::query()->where('code', 'OPD')->firstOrFail();

        $transfer = $this->sendTransferTo($opd, 'Mwansa, John');

        $response = $this->actingAs($opdStaff)->get(route('department.index'));

        $response->assertOk();
        $response->assertSee(route('transfers.acknowledge', $transfer), false);
        $response->assertSee(route('transfers.reject', $transfer), false);
    }

    public function test_user_with_no_department_sees_an_empty_inbox(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'department_id' => null]);

        $response = $this->actingAs($admin)->get(route('department.index'));

        $response->assertOk();
        $response->assertSee('No transfers match these filters.');
    }

    private function sendTransferTo(Department $to, string $patientName, ?Department $from = null): Transfer
    {
        $from ??= Department::query()->where('code', 'RAD')->firstOrFail();

        $sender = User::factory()->create(['department_id' => $from->id, 'role' => 'staff']);
        $type = ExaminationType::query()->first() ?? ExaminationType::factory()->create();
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
        ]);

        TransferRecipient::factory()->create([
            'transfer_id' => $transfer->id,
            'department_id' => $to->id,
            'status' => 'delivered',
            'delivered_at' => now(),
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
