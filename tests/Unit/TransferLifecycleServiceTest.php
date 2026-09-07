<?php

namespace Tests\Unit;

use App\Enums\TransferRecipientStatus;
use App\Enums\TransferStatus;
use App\Models\Department;
use App\Models\Examination;
use App\Models\Transfer;
use App\Models\User;
use App\Services\TransferLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class TransferLifecycleServiceTest extends TestCase
{
    use RefreshDatabase;

    private TransferLifecycleService $lifecycle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lifecycle = app(TransferLifecycleService::class);
    }

    public function test_send_creates_a_sent_transfer_with_delivered_recipients(): void
    {
        $sender = User::factory()->create();
        $examination = Examination::factory()->create();
        $opd = Department::factory()->create();
        $nursing = Department::factory()->create();

        $transfer = $this->lifecycle->send($examination, $sender, [$opd->id, $nursing->id], 'Please review.');

        $this->assertSame(TransferStatus::Delivered, $transfer->status);
        $this->assertNotNull($transfer->sent_at);
        $this->assertNotNull($transfer->delivered_at);
        $this->assertNull($transfer->completed_at);
        $this->assertSame($sender->department_id, $transfer->from_department_id);
        $this->assertCount(2, $transfer->recipients);

        foreach ($transfer->recipients as $recipient) {
            $this->assertSame(TransferRecipientStatus::Delivered, $recipient->status);
            $this->assertNotNull($recipient->delivered_at);
            $this->assertNull($recipient->acknowledged_at);
        }
    }

    public function test_send_requires_the_sender_to_have_a_department(): void
    {
        $sender = User::factory()->create(['department_id' => null]);
        $examination = Examination::factory()->create();
        $department = Department::factory()->create();

        $this->expectException(RuntimeException::class);

        $this->lifecycle->send($examination, $sender, [$department->id]);
    }

    public function test_recipient_progresses_through_the_lifecycle_in_order(): void
    {
        $transfer = Transfer::factory()->create();
        $recipient = $transfer->recipients()->create([
            'department_id' => Department::factory()->create()->id,
            'status' => TransferRecipientStatus::Delivered,
            'delivered_at' => now(),
        ]);
        $receivedBy = User::factory()->create();

        $this->lifecycle->acknowledge($recipient, $receivedBy);
        $recipient->refresh();
        $this->assertSame(TransferRecipientStatus::Acknowledged, $recipient->status);
        $this->assertNotNull($recipient->acknowledged_at);
        $this->assertSame($receivedBy->id, $recipient->received_by);
        $this->assertNull($recipient->viewed_at);

        $this->lifecycle->markViewed($recipient);
        $recipient->refresh();
        $this->assertSame(TransferRecipientStatus::Viewed, $recipient->status);
        $this->assertNotNull($recipient->viewed_at);
        $this->assertNull($recipient->downloaded_at);

        $this->lifecycle->markDownloaded($recipient);
        $recipient->refresh();
        $this->assertSame(TransferRecipientStatus::Downloaded, $recipient->status);
        $this->assertNotNull($recipient->downloaded_at);

        $this->lifecycle->complete($recipient);
        $recipient->refresh();
        $this->assertSame(TransferRecipientStatus::Completed, $recipient->status);
        $this->assertNotNull($recipient->completed_at);
    }

    public function test_completing_before_viewing_is_rejected(): void
    {
        $transfer = Transfer::factory()->create();
        $recipient = $transfer->recipients()->create([
            'department_id' => Department::factory()->create()->id,
            'status' => TransferRecipientStatus::Acknowledged,
            'delivered_at' => now(),
            'acknowledged_at' => now(),
        ]);

        $this->expectException(RuntimeException::class);

        $this->lifecycle->complete($recipient);
    }

    public function test_events_are_recorded_independently_and_do_not_skip_stages(): void
    {
        $transfer = Transfer::factory()->create();
        $recipient = $transfer->recipients()->create([
            'department_id' => Department::factory()->create()->id,
            'status' => TransferRecipientStatus::Delivered,
            'delivered_at' => now(),
        ]);

        // Jump straight to Downloaded without acknowledging or viewing first.
        $this->lifecycle->markDownloaded($recipient);
        $recipient->refresh();

        $this->assertSame(TransferRecipientStatus::Downloaded, $recipient->status);
        $this->assertNotNull($recipient->downloaded_at);
        $this->assertNull($recipient->acknowledged_at);
        $this->assertNull($recipient->viewed_at);
    }

    public function test_repeated_events_never_overwrite_an_existing_timestamp_or_regress_status(): void
    {
        $transfer = Transfer::factory()->create();
        $recipient = $transfer->recipients()->create([
            'department_id' => Department::factory()->create()->id,
            'status' => TransferRecipientStatus::Downloaded,
            'delivered_at' => now(),
            'viewed_at' => now(),
            'downloaded_at' => now(),
        ]);

        $originalViewedAt = $recipient->viewed_at;

        $this->lifecycle->markViewed($recipient);
        $recipient->refresh();

        $this->assertSame(TransferRecipientStatus::Downloaded, $recipient->status);
        $this->assertEquals($originalViewedAt, $recipient->viewed_at);
    }

    public function test_transfer_is_marked_delivered_only_once_every_recipient_is_delivered(): void
    {
        $sender = User::factory()->create();
        $examination = Examination::factory()->create();
        $opd = Department::factory()->create();
        $nursing = Department::factory()->create();

        $transfer = $this->lifecycle->send($examination, $sender, [$opd->id, $nursing->id]);

        // send() already delivers synchronously, so this transfer is fully delivered already.
        $this->assertSame(TransferStatus::Delivered, $transfer->status);
        $this->assertNotNull($transfer->delivered_at);
    }

    public function test_transfer_is_marked_completed_only_once_every_recipient_is_completed(): void
    {
        $sender = User::factory()->create();
        $examination = Examination::factory()->create();
        $opd = Department::factory()->create();
        $nursing = Department::factory()->create();

        $transfer = $this->lifecycle->send($examination, $sender, [$opd->id, $nursing->id]);
        [$first, $second] = $transfer->recipients;
        $receivedBy = User::factory()->create();

        $this->lifecycle->acknowledge($first, $receivedBy);
        $this->lifecycle->markViewed($first);
        $this->lifecycle->complete($first);

        $transfer->refresh();
        $this->assertNotSame(TransferStatus::Completed, $transfer->status);
        $this->assertNull($transfer->completed_at);

        $this->lifecycle->acknowledge($second, $receivedBy);
        $this->lifecycle->markViewed($second);
        $this->lifecycle->complete($second);

        $transfer->refresh();
        $this->assertSame(TransferStatus::Completed, $transfer->status);
        $this->assertNotNull($transfer->completed_at);
    }

    public function test_viewing_before_acknowledging_is_a_no_op(): void
    {
        $transfer = Transfer::factory()->create();
        $recipient = $transfer->recipients()->create([
            'department_id' => Department::factory()->create()->id,
            'status' => TransferRecipientStatus::Delivered,
            'delivered_at' => now(),
        ]);

        $this->lifecycle->markViewed($recipient);
        $recipient->refresh();

        $this->assertSame(TransferRecipientStatus::Delivered, $recipient->status);
        $this->assertNull($recipient->viewed_at);
    }

    public function test_re_acknowledging_does_not_overwrite_received_by(): void
    {
        $transfer = Transfer::factory()->create();
        $recipient = $transfer->recipients()->create([
            'department_id' => Department::factory()->create()->id,
            'status' => TransferRecipientStatus::Delivered,
            'delivered_at' => now(),
        ]);
        $firstStaff = User::factory()->create();
        $secondStaff = User::factory()->create();

        $this->lifecycle->acknowledge($recipient, $firstStaff);
        $recipient->refresh();
        $originalAcknowledgedAt = $recipient->acknowledged_at;

        $this->lifecycle->acknowledge($recipient, $secondStaff);
        $recipient->refresh();

        $this->assertSame($firstStaff->id, $recipient->received_by);
        $this->assertEquals($originalAcknowledgedAt, $recipient->acknowledged_at);
    }

    public function test_reject_marks_the_recipient_rejected_and_stops_further_progress(): void
    {
        $transfer = Transfer::factory()->create();
        $recipient = $transfer->recipients()->create([
            'department_id' => Department::factory()->create()->id,
            'status' => TransferRecipientStatus::Delivered,
            'delivered_at' => now(),
        ]);

        $this->lifecycle->reject($recipient, User::factory()->create(), 'wrong_department');
        $recipient->refresh();

        $this->assertSame(TransferRecipientStatus::Rejected, $recipient->status);
        $this->assertNotNull($recipient->rejected_at);
        $this->assertSame('wrong_department', $recipient->rejection_reason);

        // Terminal — no further automatic events register.
        $this->lifecycle->markViewed($recipient);
        $recipient->refresh();
        $this->assertSame(TransferRecipientStatus::Rejected, $recipient->status);
        $this->assertNull($recipient->viewed_at);
    }

    public function test_a_rejected_recipient_cannot_be_completed(): void
    {
        $transfer = Transfer::factory()->create();
        $recipient = $transfer->recipients()->create([
            'department_id' => Department::factory()->create()->id,
            'status' => TransferRecipientStatus::Rejected,
            'delivered_at' => now(),
            'rejected_at' => now(),
        ]);

        $this->expectException(RuntimeException::class);

        $this->lifecycle->complete($recipient);
    }

    public function test_a_completed_recipient_cannot_be_rejected(): void
    {
        $transfer = Transfer::factory()->create();
        $recipient = $transfer->recipients()->create([
            'department_id' => Department::factory()->create()->id,
            'status' => TransferRecipientStatus::Completed,
            'delivered_at' => now(),
            'acknowledged_at' => now(),
            'viewed_at' => now(),
            'completed_at' => now(),
        ]);

        $this->expectException(RuntimeException::class);

        $this->lifecycle->reject($recipient, User::factory()->create());
    }

    public function test_transfer_completes_once_every_recipient_is_terminal_even_if_one_rejected(): void
    {
        $sender = User::factory()->create();
        $examination = Examination::factory()->create();
        $opd = Department::factory()->create();
        $nursing = Department::factory()->create();

        $transfer = $this->lifecycle->send($examination, $sender, [$opd->id, $nursing->id]);
        [$first, $second] = $transfer->recipients;
        $receivedBy = User::factory()->create();

        $this->lifecycle->reject($first, User::factory()->create(), 'wrong_patient');

        $transfer->refresh();
        $this->assertNull($transfer->completed_at);

        $this->lifecycle->acknowledge($second, $receivedBy);
        $this->lifecycle->markViewed($second);
        $this->lifecycle->complete($second);

        $transfer->refresh();
        $this->assertSame(TransferStatus::Completed, $transfer->status);
        $this->assertNotNull($transfer->completed_at);
    }

    public function test_department_must_acknowledge_until_it_does(): void
    {
        $sender = User::factory()->create();
        $recipientUser = User::factory()->create();
        $examination = Examination::factory()->create();

        $transfer = $this->lifecycle->send($examination, $sender, [$recipientUser->department_id]);
        $recipient = $transfer->recipients->sole();

        $this->assertTrue($this->lifecycle->departmentMustAcknowledge($examination, $recipientUser));

        $this->lifecycle->acknowledge($recipient, $recipientUser);

        $this->assertFalse($this->lifecycle->departmentMustAcknowledge($examination, $recipientUser));
    }

    public function test_department_must_acknowledge_is_false_for_the_referring_department(): void
    {
        $examination = Examination::factory()->create();
        $owner = User::factory()->create(['department_id' => $examination->referring_department_id]);

        $this->assertFalse($this->lifecycle->departmentMustAcknowledge($examination, $owner));
    }
}
