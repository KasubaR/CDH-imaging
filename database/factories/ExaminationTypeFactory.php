<?php

namespace Database\Factories;

use App\Models\ExaminationType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExaminationType>
 */
class ExaminationTypeFactory extends Factory
{
    protected $model = ExaminationType::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'code' => strtoupper(fake()->unique()->lexify('??')),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
