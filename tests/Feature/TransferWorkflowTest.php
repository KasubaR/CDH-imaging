<?php

namespace Tests\Feature;

use App\Enums\Permission as PermissionEnum;
use App\Enums\TransferRecipientStatus;
use App\Enums\TransferStatus;
use App\Models\Department;
use App\Models\Examination;
use App\Models\ExaminationType;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\Transfer;
use App\Models\User;
use Database\Seeders\DepartmentPermissionSeeder;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\ExaminationTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TransferWorkflowTest extends TestCase
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

    public function test_full_lifecycle_from_send_to_completed(): void
    {
        $sender = $this->staffWithPermissions('RAD', [PermissionEnum::ViewImages, PermissionEnum::Upload, PermissionEnum::Send]);
        $recipientStaff = $this->staffWithPermissions('OPD', [PermissionEnum::ViewImages, PermissionEnum::Receive, PermissionEnum::Download]);
        $opd = Department::query()->where('code', 'OPD')->firstOrFail();

        $examination = $this->examinationForDepartment('RAD');

        $this->actingAs($sender)->post(route('examinations.images.store', $examination), [
            'images' => [UploadedFile::fake()->image('AP.jpg', 40, 40)],
        ])->assertRedirect();

        $image = $examination->images()->sole();

        $sendResponse = $this->actingAs($sender)->post(route('examinations.transfers.store', $examination), [
            'department_ids' => [$opd->id],
            'message' => 'Please review.',
        ]);
        $sendResponse->assertRedirect(route('examinations.show', $examination));

        $transfer = Transfer::query()->where('examination_id', $examination->id)->sole();
        $recipient = $transfer->recipients()->sole();

        $this->assertSame(TransferStatus::Delivered, $transfer->status);
        $this->assertSame(TransferRecipientStatus::Delivered, $recipient->status);

        // Acknowledge.
        $this->actingAs($recipientStaff)
            ->post(route('transfers.acknowledge', $transfer))
            ->assertRedirect();

        $recipient->refresh();
        $this->assertSame(TransferRecipientStatus::Acknowledged, $recipient->status);
        $this->assertNotNull($recipient->acknowledged_at);
        $this->assertSame($recipientStaff->id, $recipient->received_by);

        // Attempting to complete before viewing is rejected.
        $this->actingAs($recipientStaff)
            ->post(route('transfers.complete', $transfer))
            ->assertSessionHasErrors('transfer');

        $recipient->refresh();
        $this->assertSame(TransferRecipientStatus::Acknowledged, $recipient->status);

        // View (GET examinations.show) records Viewed automatically.
        $this->actingAs($recipientStaff)
            ->get(route('examinations.show', $examination))
            ->assertOk();

        $recipient->refresh();
        $this->assertSame(TransferRecipientStatus::Viewed, $recipient->status);
        $this->assertNotNull($recipient->viewed_at);

        // Download (GET images.download) records Downloaded automatically, separately from Viewed.
        $this->actingAs($recipientStaff)
            ->get(route('images.download', $image))
            ->assertOk();

        $recipient->refresh();
        $this->assertSame(TransferRecipientStatus::Downloaded, $recipient->status);
        $this->assertNotNull($recipient->downloaded_at);
        $this->assertNotSame($recipient->viewed_at, $recipient->downloaded_at);

        // Complete now succeeds, and rolls up onto the parent Transfer.
        $this->actingAs($recipientStaff)
            ->post(route('transfers.complete', $transfer))
            ->assertRedirect();

        $recipient->refresh();
        $transfer->refresh();
        $this->assertSame(TransferRecipientStatus::Completed, $recipient->status);
        $this->assertNotNull($recipient->completed_at);
        $this->assertSame(TransferStatus::Completed, $transfer->status);
        $this->assertNotNull($transfer->completed_at);
    }

    public function test_sending_to_an_unauthorized_department_is_rejected(): void
    {
        $sender = $this->staffWithPermissions('RAD', [PermissionEnum::ViewImages, PermissionEnum::Send]);
        $examination = $this->examinationForDepartment('RAD');

        // No DepartmentPermission row exists from RAD to a freshly created, unrelated department.
        $unrelated = Department::factory()->create(['is_active' => true]);

        $this->actingAs($sender)
            ->post(route('examinations.transfers.store', $examination), [
                'department_ids' => [$unrelated->id],
            ])
            ->assertSessionHasErrors('department_ids');

        $this->assertSame(0, Transfer::query()->count());
    }

    public function test_a_department_outside_the_transfer_cannot_acknowledge_or_complete_it(): void
    {
        $sender = $this->staffWithPermissions('RAD', [PermissionEnum::ViewImages, PermissionEnum::Send]);
        $opd = Department::query()->where('code', 'OPD')->firstOrFail();
        $examination = $this->examinationForDepartment('RAD');

        $this->actingAs($sender)->post(route('examinations.transfers.store', $examination), [
            'department_ids' => [$opd->id],
        ]);

        $transfer = Transfer::query()->where('examination_id', $examination->id)->sole();

        $outsider = $this->staffWithPermissions('NURS', [PermissionEnum::ViewImages, PermissionEnum::Receive]);

        $this->actingAs($outsider)
            ->post(route('transfers.acknowledge', $transfer))
            ->assertForbidden();

        $this->actingAs($outsider)
            ->post(route('transfers.complete', $transfer))
            ->assertForbidden();

        $this->actingAs($outsider)
            ->post(route('transfers.reject', $transfer))
            ->assertForbidden();
    }

    public function test_opening_the_transfer_before_acknowledging_is_blocked_and_does_not_mark_it_viewed(): void
    {
        $sender = $this->staffWithPermissions('RAD', [PermissionEnum::ViewImages, PermissionEnum::Send]);
        $recipientStaff = $this->staffWithPermissions('OPD', [PermissionEnum::ViewImages, PermissionEnum::Receive]);
        $opd = Department::query()->where('code', 'OPD')->firstOrFail();
        $examination = $this->examinationForDepartment('RAD');

        $this->actingAs($sender)->post(route('examinations.transfers.store', $examination), [
            'department_ids' => [$opd->id],
        ]);

        $transfer = Transfer::query()->where('examination_id', $examination->id)->sole();
        $recipient = $transfer->recipients()->sole();

        $this->actingAs($recipientStaff)
            ->get(route('examinations.show', $examination))
            ->assertRedirect(route('department.index'));

        $recipient->refresh();
        $this->assertSame(TransferRecipientStatus::Delivered, $recipient->status);
        $this->assertNull($recipient->viewed_at);
    }

    public function test_recipient_can_reject_a_transfer_and_it_cannot_then_be_completed(): void
    {
        $sender = $this->staffWithPermissions('RAD', [PermissionEnum::ViewImages, PermissionEnum::Send]);
        $recipientStaff = $this->staffWithPermissions('OPD', [PermissionEnum::ViewImages, PermissionEnum::Receive]);
        $opd = Department::query()->where('code', 'OPD')->firstOrFail();
        $examination = $this->examinationForDepartment('RAD');

        $this->actingAs($sender)->post(route('examinations.transfers.store', $examination), [
            'department_ids' => [$opd->id],
        ]);

        $transfer = Transfer::query()->where('examination_id', $examination->id)->sole();
        $recipient = $transfer->recipients()->sole();

        $this->actingAs($recipientStaff)
            ->post(route('transfers.reject', $transfer), ['reason' => 'wrong_patient'])
            ->assertRedirect();

        $recipient->refresh();
        $transfer->refresh();
        $this->assertSame(TransferRecipientStatus::Rejected, $recipient->status);
        $this->assertNotNull($recipient->rejected_at);
        $this->assertSame('wrong_patient', $recipient->rejection_reason);
        // The lone recipient has reached a terminal state, so the transfer as a whole is resolved.
        $this->assertSame(TransferStatus::Completed, $transfer->status);

        $this->actingAs($recipientStaff)
            ->post(route('transfers.complete', $transfer))
            ->assertSessionHasErrors('transfer');
    }

    private function examinationForDepartment(string $departmentCode): Examination
    {
        $department = Department::query()->where('code', $departmentCode)->firstOrFail();
        $type = ExaminationType::query()->first() ?? ExaminationType::factory()->create();
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
