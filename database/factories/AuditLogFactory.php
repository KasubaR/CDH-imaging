<?php

namespace Database\Factories;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Image;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        $department = Department::factory()->create();
        $image = Image::factory()->create();

        return [
            'department_id' => $department->id,
            'account_id' => User::factory()->create(['department_id' => $department->id]),
            'action' => AuditAction::Uploaded,
            'subject_type' => $image->getMorphClass(),
            'subject_id' => $image->id,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'created_at' => now(),
        ];
    }
}
