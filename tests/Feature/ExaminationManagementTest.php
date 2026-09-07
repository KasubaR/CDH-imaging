<?php

namespace Tests\Feature;

use App\Enums\Permission as PermissionEnum;
use App\Models\Department;
use App\Models\Examination;
use App\Models\ExaminationType;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\User;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\ExaminationTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExaminationManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            DepartmentSeeder::class,
            PermissionSeeder::class,
            ExaminationTypeSeeder::class,
        ]);
    }

    public function test_staff_with_upload_can_create_examination_for_patient(): void
    {
        $user = $this->staffWithPermissions([PermissionEnum::ViewImages, PermissionEnum::Upload]);
        $patient = Patient::factory()->create(['patient_name' => 'Mwansa, John']);
        $radiology = Department::query()->where('code', 'RAD')->firstOrFail();
        $opd = Department::query()->where('code', 'OPD')->firstOrFail();
        $type = ExaminationType::query()->where('code', 'CHEST')->firstOrFail();

        $response = $this->actingAs($user)
            ->post(route('patients.examinations.store', $patient), [
                'examination_type_id' => $type->id,
                'body_part' => 'Chest',
                'description' => 'Routine chest film',
                'date_taken' => '2026-09-02',
                'time_taken' => '09:30',
                'referring_department_id' => $opd->id,
                'referring_clinician' => 'Dr. K. Mulenga',
                'radiographer' => 'Tech Banda',
            ]);

        $examination = Examination::query()->where('patient_id', $patient->id)->firstOrFail();

        $response->assertRedirect(route('examinations.show', $examination));
        $this->assertDatabaseHas('examinations', [
            'patient_id' => $patient->id,
            'examination_type_id' => $type->id,
            'body_part' => 'Chest',
            'referring_department_id' => $opd->id,
            'created_by' => $user->id,
            'referring_clinician' => 'Dr. K. Mulenga',
            'radiographer' => 'Tech Banda',
        ]);
    }

    public function test_inactive_examination_type_cannot_be_used(): void
    {
        $user = $this->staffWithPermissions([PermissionEnum::ViewImages, PermissionEnum::Upload]);
        $patient = Patient::factory()->create();
        $department = Department::query()->where('code', 'RAD')->firstOrFail();
        $type = ExaminationType::query()->where('code', 'CHEST')->firstOrFail();
        $type->update(['is_active' => false]);

        $this->actingAs($user)
            ->post(route('patients.examinations.store', $patient), [
                'examination_type_id' => $type->id,
                'body_part' => 'Chest',
                'date_taken' => '2026-09-02',
                'time_taken' => '09:30',
                'referring_department_id' => $department->id,
            ])
            ->assertSessionHasErrors('examination_type_id');
    }

    public function test_examination_viewer_shows_patient_and_study_details(): void
    {
        $user = $this->staffWithPermissions([PermissionEnum::ViewImages]);
        $patient = Patient::factory()->create(['patient_name' => 'Mwansa, John']);
        $department = Department::query()->where('code', 'RAD')->firstOrFail();
        $type = ExaminationType::query()->where('code', 'CHEST')->firstOrFail();

        $examination = Examination::factory()->create([
            'patient_id' => $patient->id,
            'examination_type_id' => $type->id,
            'referring_department_id' => $department->id,
            'created_by' => $user->id,
            'body_part' => 'Chest',
            'referring_clinician' => 'Dr. K. Mulenga',
        ]);

        $this->actingAs($user)
            ->get(route('examinations.show', $examination))
            ->assertOk()
            ->assertSee('Mwansa, John')
            ->assertSee('Chest')
            ->assertSee('Dr. K. Mulenga');
    }

    public function test_create_examination_form_renders_custom_dropdowns(): void
    {
        $user = $this->staffWithPermissions([PermissionEnum::ViewImages, PermissionEnum::Upload]);
        $patient = Patient::factory()->create(['patient_name' => 'Mwansa, John']);
        $type = ExaminationType::query()->where('code', 'CHEST')->firstOrFail();

        $this->actingAs($user)
            ->get(route('patients.examinations.create', $patient))
            ->assertOk()
            ->assertSee('data-dropdown', false)
            ->assertSee('name="examination_type_id"', false)
            ->assertSee('name="referring_department_id"', false)
            ->assertSee($type->name)
            ->assertSee('Select type...');
    }

    public function test_staff_without_upload_cannot_create_examinations(): void
    {
        $user = $this->staffWithPermissions([PermissionEnum::ViewImages]);
        $patient = Patient::factory()->create();

        $this->actingAs($user)
            ->get(route('patients.examinations.create', $patient))
            ->assertForbidden();
    }

    /**
     * @param  list<PermissionEnum>  $permissions
     */
    private function staffWithPermissions(array $permissions): User
    {
        $department = Department::query()->where('code', 'RAD')->firstOrFail();

        $user = User::factory()->create([
            'department_id' => $department->id,
            'role' => 'staff',
        ]);

        $permissionIds = Permission::query()
            ->whereIn('slug', array_map(fn (PermissionEnum $permission) => $permission->value, $permissions))
            ->pluck('id');

        $user->permissions()->sync($permissionIds);

        return $user->fresh(['permissions', 'department']);
    }
}
