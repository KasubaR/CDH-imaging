<?php

namespace Database\Factories;

use App\Models\Examination;
use App\Models\Image;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Image>
 */
class ImageFactory extends Factory
{
    protected $model = Image::class;

    public function definition(): array
    {
        $uuid = fake()->uuid();

        return [
            'examination_id' => Examination::factory(),
            'uploaded_by' => User::factory(),
            'disk' => 'local',
            'uuid' => $uuid,
            'stored_filename' => $uuid.'.jpg',
            'storage_path' => 'xrays/originals/'.$uuid.'.jpg',
            'thumbnail_path' => null,
            'original_filename' => fake()->word().'.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => fake()->numberBetween(10000, 5000000),
            'checksum' => hash('sha256', $uuid),
        ];
    }
}
