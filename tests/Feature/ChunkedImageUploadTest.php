<?php

namespace Tests\Feature;

use App\Enums\Permission as PermissionEnum;
use App\Enums\UploadSessionStatus;
use App\Models\Department;
use App\Models\Examination;
use App\Models\ExaminationType;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\UploadSession;
use App\Models\User;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\ExaminationTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChunkedImageUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            DepartmentSeeder::class,
            PermissionSeeder::class,
            ExaminationTypeSeeder::class,
        ]);

        Storage::fake('local');
    }

    public function test_a_file_split_into_chunks_assembles_into_one_image_and_cleans_up_chunk_files(): void
    {
        $user = $this->staffWithPermissions([PermissionEnum::ViewImages, PermissionEnum::Upload]);
        $examination = $this->examinationForDepartment('RAD');
        $uploadId = (string) Str::uuid();

        $chunks = $this->splitIntoChunks(UploadedFile::fake()->image('AP.jpg', 800, 800), 3);

        foreach ($chunks as $index => $chunk) {
            $response = $this->postChunk($user, $examination, $uploadId, $index, count($chunks), 'AP.jpg', $chunk);

            if ($index < count($chunks) - 1) {
                $response->assertOk()->assertJson(['status' => 'chunk_received']);
            }
        }

        $response->assertOk()->assertJson(['status' => 'completed']);

        $this->assertDatabaseCount('images', 1);
        $image = $examination->images()->sole();
        $this->assertSame('AP.jpg', $image->original_filename);
        $this->assertStringStartsWith('xrays/originals/', $image->storage_path);
        Storage::disk('local')->assertExists($image->storage_path);

        $session = UploadSession::query()->where('uuid', $uploadId)->sole();
        $this->assertSame(UploadSessionStatus::Completed, $session->status);
        $this->assertSame($image->id, $session->image_id);
        Storage::disk('local')->assertDirectoryEmpty('xrays/chunks/'.$uploadId);

        // GenerateThumbnail is dispatched with ->afterCommit() and QUEUE_CONNECTION=sync
        // in tests, so by the time the last chunk's response returns it has already run
        // against the assembled 800x800 original.
        $this->assertNotNull($image->thumbnail_path);
        Storage::disk('local')->assertExists($image->thumbnail_path);
    }

    public function test_status_endpoint_reports_already_received_chunks_for_resume(): void
    {
        $user = $this->staffWithPermissions([PermissionEnum::ViewImages, PermissionEnum::Upload]);
        $examination = $this->examinationForDepartment('RAD');
        $uploadId = (string) Str::uuid();

        $chunks = $this->splitIntoChunks(UploadedFile::fake()->image('AP.jpg', 800, 800), 3);

        // Only send the first chunk — simulate a dropped connection.
        $this->postChunk($user, $examination, $uploadId, 0, count($chunks), 'AP.jpg', $chunks[0])
            ->assertOk();

        $response = $this->actingAs($user)->getJson(
            route('examinations.images.chunks.status', ['examination' => $examination, 'uploadId' => $uploadId]),
        );

        $response->assertOk()->assertJson([
            'status' => 'pending',
            'received' => [0],
            'total' => count($chunks),
        ]);

        // Resume: send the remaining chunks, skipping index 0.
        foreach ($chunks as $index => $chunk) {
            if ($index === 0) {
                continue;
            }

            $last = $this->postChunk($user, $examination, $uploadId, $index, count($chunks), 'AP.jpg', $chunk);
        }

        $last->assertOk()->assertJson(['status' => 'completed']);
        $this->assertDatabaseCount('images', 1);
    }

    public function test_resubmitting_an_already_received_chunk_index_is_a_harmless_no_op(): void
    {
        $user = $this->staffWithPermissions([PermissionEnum::ViewImages, PermissionEnum::Upload]);
        $examination = $this->examinationForDepartment('RAD');
        $uploadId = (string) Str::uuid();

        $chunks = $this->splitIntoChunks(UploadedFile::fake()->image('AP.jpg', 800, 800), 2);

        $this->postChunk($user, $examination, $uploadId, 0, count($chunks), 'AP.jpg', $chunks[0])->assertOk();
        // Retry the same chunk (as a flaky client would).
        $this->postChunk($user, $examination, $uploadId, 0, count($chunks), 'AP.jpg', $chunks[0])
            ->assertOk()
            ->assertJson(['status' => 'chunk_received', 'received' => 1, 'total' => 2]);

        $this->postChunk($user, $examination, $uploadId, 1, count($chunks), 'AP.jpg', $chunks[1])
            ->assertOk()
            ->assertJson(['status' => 'completed']);

        $this->assertDatabaseCount('images', 1);
    }

    public function test_assembled_upload_still_enforces_the_duplicate_checksum_rejection(): void
    {
        $user = $this->staffWithPermissions([PermissionEnum::ViewImages, PermissionEnum::Upload]);
        $examination = $this->examinationForDepartment('RAD');
        $source = UploadedFile::fake()->image('AP.jpg', 50, 50);

        // First upload, non-chunked — establishes the checksum.
        $this->actingAs($user)->post(route('examinations.images.store', $examination), [
            'images' => [$source],
        ])->assertRedirect();
        $this->assertDatabaseCount('images', 1);

        // Same bytes, chunked this time.
        $chunks = $this->splitIntoChunks($source, 2);
        $uploadId = (string) Str::uuid();

        $this->postChunk($user, $examination, $uploadId, 0, count($chunks), 'AP-copy.jpg', $chunks[0])->assertOk();
        $response = $this->postChunk($user, $examination, $uploadId, 1, count($chunks), 'AP-copy.jpg', $chunks[1]);

        $response->assertStatus(422)->assertJson(['status' => 'failed']);
        $this->assertDatabaseCount('images', 1);

        $session = UploadSession::query()->where('uuid', $uploadId)->sole();
        $this->assertSame(UploadSessionStatus::Failed, $session->status);
        Storage::disk('local')->assertDirectoryEmpty('xrays/chunks/'.$uploadId);
    }

    public function test_assembled_size_exceeding_the_limit_is_rejected_and_chunks_are_cleaned_up(): void
    {
        $user = $this->staffWithPermissions([PermissionEnum::ViewImages, PermissionEnum::Upload]);
        $examination = $this->examinationForDepartment('RAD');
        $uploadId = (string) Str::uuid();

        config(['cdh.image_max_bytes' => 1024]);

        $chunk = UploadedFile::fake()->create('part.bin', 2)->size(2 * 1024);

        $response = $this->postChunk($user, $examination, $uploadId, 0, 1, 'huge.jpg', $chunk);

        $response->assertStatus(422)->assertJson(['status' => 'failed']);
        Storage::disk('local')->assertDirectoryEmpty('xrays/chunks/'.$uploadId);
        $this->assertDatabaseCount('images', 0);
    }

    public function test_staff_without_upload_permission_cannot_post_a_chunk(): void
    {
        $user = $this->staffWithPermissions([PermissionEnum::ViewImages]);
        $examination = $this->examinationForDepartment('RAD');

        $this->postChunk($user, $examination, (string) Str::uuid(), 0, 1, 'AP.jpg', UploadedFile::fake()->create('c', 10))
            ->assertForbidden();

        $this->assertDatabaseCount('upload_sessions', 0);
    }

    public function test_a_chunk_for_someone_elses_upload_session_is_refused(): void
    {
        $owner = $this->staffWithPermissions([PermissionEnum::ViewImages, PermissionEnum::Upload]);
        $intruder = $this->staffWithPermissions([PermissionEnum::ViewImages, PermissionEnum::Upload]);
        $examination = $this->examinationForDepartment('RAD');
        $uploadId = (string) Str::uuid();

        $chunks = $this->splitIntoChunks(UploadedFile::fake()->image('AP.jpg', 50, 50), 2);

        $this->postChunk($owner, $examination, $uploadId, 0, count($chunks), 'AP.jpg', $chunks[0])->assertOk();
        $this->postChunk($intruder, $examination, $uploadId, 1, count($chunks), 'AP.jpg', $chunks[1])->assertForbidden();
    }

    /**
     * @return list<UploadedFile>
     */
    private function splitIntoChunks(UploadedFile $file, int $chunkCount): array
    {
        $bytes = file_get_contents($file->getRealPath());
        $size = strlen($bytes);
        $chunkSize = (int) ceil($size / $chunkCount);
        $chunks = [];

        for ($i = 0; $i < $chunkCount; $i++) {
            $piece = substr($bytes, $i * $chunkSize, $chunkSize);
            $tmpPath = tempnam(sys_get_temp_dir(), 'chunk_test_');
            file_put_contents($tmpPath, $piece);
            $chunks[] = new UploadedFile($tmpPath, "chunk-{$i}", null, null, true);
        }

        return $chunks;
    }

    private function postChunk(
        User $user,
        Examination $examination,
        string $uploadId,
        int $chunkIndex,
        int $totalChunks,
        string $filename,
        UploadedFile $chunk,
    ) {
        return $this->actingAs($user)->postJson(
            route('examinations.images.chunks.store', $examination),
            [
                'upload_id' => $uploadId,
                'chunk_index' => $chunkIndex,
                'total_chunks' => $totalChunks,
                'filename' => $filename,
                'chunk' => $chunk,
            ],
        );
    }

    private function examinationForDepartment(string $departmentCode): Examination
    {
        $department = Department::query()->where('code', $departmentCode)->firstOrFail();
        $type = ExaminationType::query()->where('code', 'CHEST')->firstOrFail();
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
    private function staffWithPermissions(array $permissions): User
    {
        $department = Department::query()->where('code', 'RAD')->firstOrFail();

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
