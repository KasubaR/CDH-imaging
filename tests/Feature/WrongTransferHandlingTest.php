<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\NotificationType;
use App\Enums\Permission as PermissionEnum;
use App\Enums\TransferRecipientStatus;
use App\Models\Department;
use App\Models\Examination;
use App\Models\ExaminationType;
use App\Models\Notification;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\Transfer;
use App\Models\User;
use Database\Seeders\DepartmentPermissionSeeder;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\ExaminationTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WrongTransferHandlingTest extends TestCase
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

        Storage::fake('local');
    }

    public function test_sender_can_recall_before_acknowledgement(): void
    {
        $sender = $this->staffWithPermissions('RAD', [PermissionEnum::ViewImages, PermissionEnum::Send]);
        $opdStaff = $this->staffWithPermissions('OPD', [PermissionEnum::ViewImages, PermissionEnum::Receive]);
        $opd = Department::query()->where('code', 'OPD')->firstOrFail();
        $examination = $this->examinationForDepartment('RAD');

        $this->actingAs($sender)->post(route('examinations.transfers.store', $examination), [
            'department_ids' => [$opd->id],
        ]);

        $transfer = Transfer::query()->where('examination_id', $examination->id)->sole();

        $this->actingAs($sender)
            ->post(route('transfers.recall', $transfer), ['department_id' => $opd->id])
            ->assertRedirect();

        $recipient = $transfer->recipients()->sole();
        $this->assertSame(TransferRecipientStatus::Recalled, $recipient->status);
        $this->assertNotNull($recipient->recalled_at);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $opdStaff->id,
            'type' => NotificationType::TransferRecalled->value,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::Recalled->value,
            'account_id' => $sender->id,
        ]);
    }

    public function test_sender_cannot_recall_after_acknowledgement(): void
    {
        $sender = $this->staffWithPermissions('RAD', [PermissionEnum::ViewImages, PermissionEnum::Send]);
        $opdStaff = $this->staffWithPermissions('OPD', [PermissionEnum::ViewImages, PermissionEnum::Receive]);
        $opd = Department::query()->where('code', 'OPD')->firstOrFail();
        $examination = $this->examinationForDepartment('RAD');

        $this->actingAs($sender)->post(route('examinations.transfers.store', $examination), [
            'department_ids' => [$opd->id],
        ]);

        $transfer = Transfer::query()->where('examination_id', $examination->id)->sole();

        $this->actingAs($opdStaff)->post(route('transfers.acknowledge', $transfer));

        $this->actingAs($sender)
            ->post(route('transfers.recall', $transfer), ['department_id' => $opd->id])
            ->assertSessionHasErrors('transfer');

        $this->assertSame(TransferRecipientStatus::Acknowledged, $transfer->recipients()->sole()->status);
    }

    public function test_reject_requires_a_structured_reason_and_notifies_the_sender(): void
    {
        $sender = $this->staffWithPermissions('RAD', [PermissionEnum::ViewImages, PermissionEnum::Send]);
        $opdStaff = $this->staffWithPermissions('OPD', [PermissionEnum::ViewImages, PermissionEnum::Receive]);
        $opd = Department::query()->where('code', 'OPD')->firstOrFail();
        $examination = $this->examinationForDepartment('RAD');

        $this->actingAs($sender)->post(route('examinations.transfers.store', $examination), [
            'department_ids' => [$opd->id],
        ]);

        $transfer = Transfer::query()->where('examination_id', $examination->id)->sole();

        $this->actingAs($opdStaff)
            ->post(route('transfers.reject', $transfer))
            ->assertSessionHasErrors('reason');

        $this->actingAs($opdStaff)
            ->post(route('transfers.reject', $transfer), ['reason' => 'wrong_department'])
            ->assertRedirect();

        $this->assertSame('wrong_department', $transfer->recipients()->sole()->rejection_reason);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $sender->id,
            'type' => NotificationType::TransferRejected->value,
        ]);
        $this->assertTrue(
            Notification::query()
                ->where('user_id', $sender->id)
                ->where('type', NotificationType::TransferRejected)
                ->get()
                ->contains(fn (Notification $notification) => str_contains($notification->body, 'Wrong department')),
        );
    }

    private function examinationForDepartment(string $departmentCode): Examination
    {
        $department = Department::query()->where('code', $departmentCode)->firstOrFail();
        $type = ExaminationType::query()->where('code', 'CHEST')->firstOrFail();

        return Examination::factory()->create([
            'patient_id' => Patient::factory()->create()->id,
            'examination_type_id' => $type->id,
            'referring_department_id' => $department->id,
        ]);
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
