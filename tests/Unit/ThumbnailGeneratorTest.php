<?php

namespace Tests\Unit;

use App\Services\ThumbnailGenerator;
use Tests\TestCase;

class ThumbnailGeneratorTest extends TestCase
{
    private ThumbnailGenerator $generator;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = new ThumbnailGenerator;
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    public function test_large_image_is_scaled_down_to_fit_within_500px(): void
    {
        $path = $this->makeJpeg(4000, 4000);

        $bytes = $this->generator->generate($path, 'image/jpeg');

        $this->assertNotNull($bytes);

        $info = getimagesizefromstring($bytes);
        $this->assertSame('image/jpeg', $info['mime']);
        $this->assertLessThanOrEqual(500, $info[0]);
        $this->assertLessThanOrEqual(500, $info[1]);
        $this->assertSame(500, max($info[0], $info[1]));
    }

    public function test_aspect_ratio_is_preserved_for_non_square_originals(): void
    {
        $path = $this->makeJpeg(1000, 400);

        $bytes = $this->generator->generate($path, 'image/jpeg');
        $info = getimagesizefromstring($bytes);

        $this->assertSame(500, $info[0]);
        $this->assertSame(200, $info[1]);
    }

    public function test_original_bytes_are_never_modified(): void
    {
        $path = $this->makeJpeg(4000, 4000);
        $originalChecksum = hash_file('sha256', $path);

        $this->generator->generate($path, 'image/jpeg');

        $this->assertSame($originalChecksum, hash_file('sha256', $path));
    }

    public function test_image_already_within_bounds_is_skipped(): void
    {
        $path = $this->makeJpeg(300, 200);

        $this->assertNull($this->generator->generate($path, 'image/jpeg'));
    }

    public function test_png_source_is_re_encoded_as_jpeg(): void
    {
        $path = $this->makePng(800, 800);

        $bytes = $this->generator->generate($path, 'image/png');

        $this->assertNotNull($bytes);
        $this->assertSame('image/jpeg', getimagesizefromstring($bytes)['mime']);
    }

    public function test_unparseable_content_fails_gracefully_without_throwing(): void
    {
        $path = $this->tempFile('not-actually-an-image');

        $this->assertNull($this->generator->generate($path, 'image/jpeg'));
    }

    public function test_unsupported_mime_type_returns_null(): void
    {
        $path = $this->makeJpeg(600, 600);

        $this->assertNull($this->generator->generate($path, 'application/pdf'));
    }

    private function makeJpeg(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 120, 140, 160));

        $path = $this->tempFile();
        imagejpeg($image, $path, 90);
        imagedestroy($image);

        return $path;
    }

    private function makePng(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 60, 90, 120));

        $path = $this->tempFile();
        imagepng($image, $path);
        imagedestroy($image);

        return $path;
    }

    private function tempFile(?string $content = null): string
    {
        $path = tempnam(sys_get_temp_dir(), 'thumb_test_');
        $this->tempFiles[] = $path;

        if ($content !== null) {
            file_put_contents($path, $content);
        }

        return $path;
    }
}
