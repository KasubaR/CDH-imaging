<?php

namespace Tests\Feature;

use App\Enums\UploadSessionStatus;
use App\Models\UploadSession;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PurgeStaleUploadSessionsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Carbon::setTestNow('2026-09-07 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_purge_deletes_stale_pending_sessions_and_their_chunk_files(): void
    {
        $stale = $this->sessionWithChunks(UploadSessionStatus::Pending, createdAt: now()->subHours(25));
        $fresh = $this->sessionWithChunks(UploadSessionStatus::Pending, createdAt: now()->subHours(1));

        Artisan::call('images:purge-stale-uploads');

        $this->assertDatabaseMissing('upload_sessions', ['id' => $stale->id]);
        $this->assertDatabaseHas('upload_sessions', ['id' => $fresh->id]);

        Storage::disk('local')->assertDirectoryEmpty('xrays/chunks/'.$stale->uuid);
        Storage::disk('local')->assertExists('xrays/chunks/'.$fresh->uuid.'/0');
    }

    public function test_purge_also_clears_stale_failed_sessions(): void
    {
        $stale = $this->sessionWithChunks(UploadSessionStatus::Failed, createdAt: now()->subHours(48));

        Artisan::call('images:purge-stale-uploads');

        $this->assertDatabaseMissing('upload_sessions', ['id' => $stale->id]);
    }

    public function test_purge_never_touches_a_completed_session(): void
    {
        $completed = UploadSession::factory()->completed()->create();
        $completed->forceFill(['created_at' => now()->subDays(30)])->save();

        Artisan::call('images:purge-stale-uploads');

        $this->assertDatabaseHas('upload_sessions', ['id' => $completed->id]);
    }

    private function sessionWithChunks(UploadSessionStatus $status, \DateTimeInterface $createdAt): UploadSession
    {
        $session = UploadSession::factory()->create(['status' => $status]);
        $session->forceFill(['created_at' => $createdAt])->save();

        Storage::disk('local')->put('xrays/chunks/'.$session->uuid.'/0', 'fake-chunk');

        return $session->fresh();
    }
}
