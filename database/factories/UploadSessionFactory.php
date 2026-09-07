<?php

namespace Database\Factories;

use App\Enums\UploadSessionStatus;
use App\Models\Examination;
use App\Models\UploadSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UploadSession>
 */
class UploadSessionFactory extends Factory
{
    protected $model = UploadSession::class;

    public function definition(): array
    {
        return [
            'uuid' => fake()->uuid(),
            'examination_id' => Examination::factory(),
            'uploaded_by' => User::factory(),
            'original_filename' => fake()->word().'.jpg',
            'declared_mime_type' => 'image/jpeg',
            'declared_size' => fake()->numberBetween(10000, 5000000),
            'total_chunks' => fake()->numberBetween(1, 4),
            'status' => UploadSessionStatus::Pending,
            'image_id' => null,
            'failure_reason' => null,
            'expires_at' => now()->addHours(24),
        ];
    }

    public function completed(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => UploadSessionStatus::Completed,
        ]);
    }

    public function failed(string $reason = 'Upload failed.'): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => UploadSessionStatus::Failed,
            'failure_reason' => $reason,
        ]);
    }
}
