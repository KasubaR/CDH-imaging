<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Examination;
use App\Models\ExaminationType;
use App\Models\User;
use Database\Seeders\ExaminationTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExaminationTypeManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ExaminationTypeSeeder::class);
    }

    public function test_admin_can_manage_examination_types(): void
    {
        $admin = User::factory()->admin()->create([
            'department_id' => Department::factory()->create()->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.examination-types.index'))
            ->assertOk()
            ->assertSee('Chest')
            ->assertSee('Hand');

        $this->actingAs($admin)
            ->post(route('admin.examination-types.store'), [
                'name' => 'Wrist',
                'code' => 'WRIST',
                'description' => 'Wrist radiograph.',
            ])
            ->assertRedirect(route('admin.examination-types.index'));

        $this->assertDatabaseHas('examination_types', [
            'name' => 'Wrist',
            'code' => 'WRIST',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_deactivate_and_activate_examination_type(): void
    {
        $admin = User::factory()->admin()->create([
            'department_id' => Department::factory()->create()->id,
        ]);

        $type = ExaminationType::query()->where('code', 'FOOT')->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('admin.examination-types.deactivate', $type))
            ->assertRedirect(route('admin.examination-types.index'));

        $this->assertFalse($type->fresh()->is_active);

        $this->actingAs($admin)
            ->patch(route('admin.examination-types.activate', $type))
            ->assertRedirect(route('admin.examination-types.index'));

        $this->assertTrue($type->fresh()->is_active);
    }

    public function test_staff_cannot_access_examination_type_administration(): void
    {
        $staff = User::factory()->create([
            'department_id' => Department::factory()->create()->id,
            'role' => 'staff',
        ]);

        $this->actingAs($staff)
            ->get(route('admin.examination-types.index'))
            ->assertForbidden();
    }

    public function test_examination_type_with_examinations_can_be_deactivated(): void
    {
        $admin = User::factory()->admin()->create([
            'department_id' => Department::factory()->create()->id,
        ]);

        $type = ExaminationType::query()->where('code', 'CHEST')->firstOrFail();
        Examination::factory()->create(['examination_type_id' => $type->id]);

        $this->actingAs($admin)
            ->patch(route('admin.examination-types.deactivate', $type))
            ->assertRedirect(route('admin.examination-types.index'));

        $this->assertFalse($type->fresh()->is_active);
        $this->assertDatabaseHas('examination_types', ['id' => $type->id]);
    }
}
