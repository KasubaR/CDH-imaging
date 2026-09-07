<?php

namespace Tests\Unit;

use App\Enums\NotificationType;
use App\Models\Department;
use App\Models\Examination;
use App\Models\ExaminationType;
use App\Models\Notification;
use App\Models\Patient;
use App\Models\Transfer;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\TransferLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_notify_transfer_received_fans_out_to_active_destination_staff(): void
    {
        $radiology = Department::factory()->create(['name' => 'Radiology', 'code' => 'RAD']);
        $opd = Department::factory()->create(['name' => 'OPD', 'code' => 'OPD']);
        $nursing = Department::factory()->create(['name' => 'Nursing', 'code' => 'NURS']);

        $sender = User::factory()->create(['department_id' => $radiology->id, 'is_active' => true]);
        $opdActive = User::factory()->create(['department_id' => $opd->id, 'is_active' => true]);
        $opdInactive = User::factory()->create(['department_id' => $opd->id, 'is_active' => false]);
        User::factory()->create(['department_id' => $nursing->id, 'is_active' => true]);

        $patient = Patient::factory()->create(['patient_name' => 'John Banda']);
        $type = ExaminationType::factory()->create(['name' => 'Chest X-ray']);
        $examination = Examination::factory()->create([
            'patient_id' => $patient->id,
            'examination_type_id' => $type->id,
            'referring_department_id' => $radiology->id,
        ]);

        $transfer = Transfer::factory()->create([
            'examination_id' => $examination->id,
            'from_department_id' => $radiology->id,
            'sent_by' => $sender->id,
        ]);
        $transfer->recipients()->create(['department_id' => $opd->id]);

        app(NotificationService::class)->notifyTransferReceived($transfer->fresh());

        $this->assertSame(1, Notification::query()->count());
        $notification = Notification::query()->sole();
        $this->assertSame($opdActive->id, $notification->user_id);
        $this->assertSame(NotificationType::TransferReceived, $notification->type);
        $this->assertSame('New X-ray received', $notification->title);
        $this->assertSame(
            "Patient: John Banda\nExamination: Chest X-ray\nFrom: Radiology",
            $notification->body,
        );
        $this->assertDatabaseMissing('notifications', ['user_id' => $opdInactive->id]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $sender->id]);
    }

    public function test_send_creates_notifications_inside_the_lifecycle_transaction(): void
    {
        $radiology = Department::factory()->create(['name' => 'Radiology']);
        $opd = Department::factory()->create(['name' => 'OPD']);
        $sender = User::factory()->create(['department_id' => $radiology->id]);
        $opdStaff = User::factory()->create(['department_id' => $opd->id, 'is_active' => true]);
        $examination = Examination::factory()->create([
            'referring_department_id' => $radiology->id,
        ]);

        app(TransferLifecycleService::class)->send($examination, $sender, [$opd->id]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $opdStaff->id,
            'type' => NotificationType::TransferReceived->value,
            'title' => 'New X-ray received',
        ]);
    }
}
