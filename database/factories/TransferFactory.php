<?php

namespace Database\Factories;

use App\Enums\TransferStatus;
use App\Models\Department;
use App\Models\Examination;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transfer>
 */
class TransferFactory extends Factory
{
    protected $model = Transfer::class;

    public function definition(): array
    {
        return [
            'examination_id' => Examination::factory(),
            'from_department_id' => Department::factory(),
            'sent_by' => User::factory(),
            'message' => fake()->optional()->sentence(),
            'status' => TransferStatus::Sent,
            'sent_at' => now(),
        ];
    }
}
