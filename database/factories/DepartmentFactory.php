<?php

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    public function definition(): array
    {
        $code = strtoupper(fake()->unique()->lexify('???'));

        return [
            'name' => fake()->company(),
            'code' => $code,
            'can_send' => fake()->boolean(),
            'can_receive' => fake()->boolean(),
            'is_active' => true,
        ];
    }

    public function sender(): static
    {
        return $this->state(fn (array $attributes) => [
            'can_send' => true,
        ]);
    }

    public function receiver(): static
    {
        return $this->state(fn (array $attributes) => [
            'can_receive' => true,
        ]);
    }
}
