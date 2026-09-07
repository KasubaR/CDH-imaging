<?php

namespace Database\Factories;

use App\Enums\TransferRecipientStatus;
use App\Models\Department;
use App\Models\Transfer;
use App\Models\TransferRecipient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransferRecipient>
 */
class TransferRecipientFactory extends Factory
{
    protected $model = TransferRecipient::class;

    public function definition(): array
    {
        return [
            'transfer_id' => Transfer::factory(),
            'department_id' => Department::factory(),
            'status' => TransferRecipientStatus::Pending,
            'delivered_at' => null,
            'acknowledged_at' => null,
            'received_by' => null,
            'viewed_at' => null,
            'downloaded_at' => null,
            'completed_at' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
        ];
    }
}
