<?php

namespace Tests\Feature;

use App\Enums\TransferRecipientStatus;
use App\Models\Department;
use App\Models\Examination;
use App\Models\ExaminationType;
use App\Models\Image;
use App\Models\Patient;
use App\Models\Transfer;
use App\Models\TransferRecipient;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\ExaminationTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            DepartmentSeeder::class,
            ExaminationTypeSeeder::class,
        ]);

        Carbon::setTestNow('2026-09-03 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_dashboard_shows_live_overview_and_activity(): void
    {
        $admin = User::factory()->admin()->create(['department_id' => null]);
        $rad = Department::query()->where('code', 'RAD')->firstOrFail();
        $opd = Department::query()->where('code', 'OPD')->firstOrFail();
        $nurs = Department::query()->where('code', 'NURS')->firstOrFail();
        $sender = User::factory()->create(['department_id' => $rad->id]);
        $type = ExaminationType::query()->where('code', 'CHEST')->firstOrFail();

        $todayExam = Examination::factory()->create([
            'patient_id' => Patient::factory()->create()->id,
            'examination_type_id' => $type->id,
            'referring_department_id' => $rad->id,
            'created_by' => $sender->id,
        ]);
        $oldExam = Examination::factory()->create([
            'patient_id' => Patient::factory()->create()->id,
            'examination_type_id' => $type->id,
            'referring_department_id' => $rad->id,
            'created_by' => $sender->id,
        ]);

        $todayImage = Image::factory()->create([
            'examination_id' => $todayExam->id,
            'uploaded_by' => $sender->id,
            'file_size' => 1024 ** 3,
        ]);
        $todayImage->forceFill(['created_at' => now()])->save();

        $oldImage = Image::factory()->create([
            'examination_id' => $oldExam->id,
            'uploaded_by' => $sender->id,
            'file_size' => 512 * (1024 ** 2),
        ]);
        $oldImage->forceFill(['created_at' => now()->subDay()])->save();

        Transfer::factory()->count(2)->create([
            'examination_id' => $todayExam->id,
            'from_department_id' => $rad->id,
            'sent_by' => $sender->id,
            'sent_at' => now(),
        ]);
        Transfer::factory()->create([
            'examination_id' => $todayExam->id,
            'from_department_id' => $opd->id,
            'sent_by' => $sender->id,
            'sent_at' => now(),
        ]);
        Transfer::factory()->create([
            'examination_id' => $todayExam->id,
            'from_department_id' => $rad->id,
            'sent_by' => $sender->id,
            'sent_at' => now()->subDay(),
        ]);

        $failedTransfer = Transfer::factory()->create([
            'examination_id' => $todayExam->id,
            'from_department_id' => $rad->id,
            'sent_by' => $sender->id,
            'sent_at' => now(),
        ]);
        TransferRecipient::factory()->create([
            'transfer_id' => $failedTransfer->id,
            'department_id' => $opd->id,
            'status' => TransferRecipientStatus::Rejected,
            'rejected_at' => now(),
        ]);
        TransferRecipient::factory()->create([
            'transfer_id' => $failedTransfer->id,
            'department_id' => $nurs->id,
            'status' => TransferRecipientStatus::Rejected,
            'rejected_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertDontSee('Avg Transfer Time');
        $response->assertDontSee('Active Nodes');
        $response->assertSee('Departments');
        $response->assertSee('Images today');
        $response->assertSee('Transfers today');
        $response->assertSee('Failed transfers');
        $response->assertSee('Storage used');
        $response->assertSee('Activity');
        $response->assertSee('Radiology');
        $response->assertSee('OPD');

        $stats = $response->viewData('stats');
        $this->assertSame(6, $stats['departments']);
        $this->assertSame(1, $stats['images_today']);
        $this->assertSame(4, $stats['transfers_today']);
        $this->assertSame(1, $stats['failed_transfers']);
        $this->assertSame('1.50 GB', $stats['storage_used']);

        $activity = $response->viewData('activity');
        $this->assertSame('Radiology', $activity->first()->department->name);
        $this->assertSame(3, $activity->first()->count);
    }
}
