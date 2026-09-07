<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Examination;
use App\Models\ExaminationType;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Examination>
 */
class ExaminationFactory extends Factory
{
    protected $model = Examination::class;

    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'examination_type_id' => ExaminationType::factory(),
            'body_part' => fake()->randomElement(['Chest', 'Left Femur', 'Abdomen', 'C-Spine']),
            'description' => fake()->optional()->sentence(),
            'date_taken' => fake()->date(),
            'time_taken' => fake()->time('H:i'),
            'referring_department_id' => Department::factory(),
            'referring_clinician' => fake()->optional()->name(),
            'radiographer' => fake()->optional()->name(),
            'created_by' => User::factory(),
        ];
    }
}
