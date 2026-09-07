<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Models\Image;
use App\Services\SettingsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PurgeExpiredImagesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Carbon::setTestNow('2026-09-03 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_purge_deletes_images_older_than_retention_with_system_audit(): void
    {
        $expired = $this->storedImage(createdAt: now()->subMonths(6)->subDay(), withThumbnail: true);
        $fresh = $this->storedImage(createdAt: now()->subMonths(5));

        $expiredOriginal = $expired->storage_path;
        $expiredThumb = $expired->thumbnail_path;
        $freshOriginal = $fresh->storage_path;

        Artisan::call('images:purge-expired');

        $this->assertDatabaseMissing('images', ['id' => $expired->id]);
        $this->assertDatabaseHas('images', ['id' => $fresh->id]);

        Storage::disk('local')->assertMissing($expiredOriginal);
        Storage::disk('local')->assertMissing($expiredThumb);
        Storage::disk('local')->assertExists($freshOriginal);

        $this->assertDatabaseHas('audit_logs', [
            'account_id' => null,
            'department_id' => null,
            'action' => AuditAction::Deleted->value,
            'subject_type' => $expired->getMorphClass(),
            'subject_id' => $expired->id,
        ]);
    }

    public function test_purge_keeps_images_exactly_at_retention_boundary(): void
    {
        $boundary = $this->storedImage(createdAt: now()->subMonths(6));

        Artisan::call('images:purge-expired');

        $this->assertDatabaseHas('images', ['id' => $boundary->id]);
    }

    public function test_purge_respects_an_admin_configured_retention_override(): void
    {
        app(SettingsService::class)->set('image_retention_months', 1);

        $expired = $this->storedImage(createdAt: now()->subMonths(2));
        $withinOverride = $this->storedImage(createdAt: now()->subDays(1));

        Artisan::call('images:purge-expired');

        $this->assertDatabaseMissing('images', ['id' => $expired->id]);
        $this->assertDatabaseHas('images', ['id' => $withinOverride->id]);
    }

    private function storedImage(\DateTimeInterface $createdAt, bool $withThumbnail = false): Image
    {
        $uuid = fake()->uuid();
        $originalPath = 'xrays/originals/'.$uuid.'.jpg';
        $thumbnailPath = $withThumbnail ? 'xrays/thumbnails/'.$uuid.'.jpg' : null;

        Storage::disk('local')->put($originalPath, 'fake-original');

        if ($thumbnailPath !== null) {
            Storage::disk('local')->put($thumbnailPath, 'fake-thumb');
        }

        $image = Image::factory()->create([
            'disk' => 'local',
            'uuid' => $uuid,
            'stored_filename' => $uuid.'.jpg',
            'storage_path' => $originalPath,
            'thumbnail_path' => $thumbnailPath,
        ]);

        $image->forceFill(['created_at' => $createdAt])->save();

        return $image->fresh();
    }
}
