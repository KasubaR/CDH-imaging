<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\ExaminationTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_department_seeder_creates_expected_departments(): void
    {
        $this->seed(DepartmentSeeder::class);

        $this->assertDatabaseCount('departments', 6);
        $this->assertDatabaseHas('departments', [
            'code' => 'RAD',
            'name' => 'Radiology',
            'can_send' => 1,
            'can_receive' => 1,
            'is_active' => 1,
        ]);
        $this->assertDatabaseHas('departments', [
            'code' => 'MOIC',
            'name' => 'Medical Officer in Charge',
            'can_send' => 0,
            'can_receive' => 1,
        ]);
    }

    public function test_examination_type_seeder_creates_xray_types(): void
    {
        $this->seed(ExaminationTypeSeeder::class);

        $this->assertDatabaseCount('examination_types', 12);
        $this->assertDatabaseHas('examination_types', ['code' => 'CHEST']);
        $this->assertDatabaseHas('examination_types', ['code' => 'HAND']);
    }

    public function test_user_belongs_to_department(): void
    {
        $department = Department::factory()->create(['code' => 'TST']);
        $user = User::factory()->create([
            'department_id' => $department->id,
            'role' => 'admin',
        ]);

        $this->assertTrue($user->department->is($department));
        $this->assertTrue($user->isAdmin());
        $this->assertCount(1, $department->users);
    }
}
