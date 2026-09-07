<?php

namespace Database\Factories;

use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    protected $model = Patient::class;

    public function definition(): array
    {
        return [
            'patient_name' => fake()->name(),
            'nrc' => fake()->boolean(70) ? fake()->unique()->numerify('######/##/1') : null,
            'gender' => null,
            'date_of_birth' => null,
        ];
    }
}
