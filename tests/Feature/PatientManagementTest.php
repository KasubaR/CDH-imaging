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

class PatientManagementTest extends TestCase
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

    public function test_guests_cannot_access_patients(): void
    {
        $this->get('/patients')->assertRedirect('/login');
    }

    public function test_staff_with_view_permission_can_list_patients(): void
    {
        $user = $this->staffWithPermissions([PermissionEnum::ViewImages]);
        Patient::factory()->create(['patient_name' => 'Mwansa, John']);

        $this->actingAs($user)
            ->get('/patients')
            ->assertOk()
            ->assertSee('Mwansa, John');
    }

    public function test_staff_without_upload_permission_cannot_create_patients(): void
    {
        $user = $this->staffWithPermissions([PermissionEnum::ViewImages]);

        $this->actingAs($user)
            ->get('/patients/create')
            ->assertForbidden();
    }

    public function test_staff_with_upload_permission_can_register_patient(): void
    {
        $user = $this->staffWithPermissions([PermissionEnum::ViewImages, PermissionEnum::Upload]);

        $response = $this->actingAs($user)
            ->post('/patients', [
                'patient_name' => 'Banda, Grace',
                'nrc' => '123456/78/1',
            ]);

        $patient = Patient::query()->where('patient_name', 'Banda, Grace')->first();

        $response->assertRedirect(route('patients.show', $patient));
        $this->assertDatabaseHas('patients', [
            'patient_name' => 'Banda, Grace',
            'nrc' => '123456/78/1',
        ]);
    }

    public function test_patient_name_is_required_and_nrc_is_optional(): void
    {
        $user = $this->staffWithPermissions([PermissionEnum::ViewImages, PermissionEnum::Upload]);

        $this->actingAs($user)
            ->post('/patients', ['patient_name' => ''])
            ->assertSessionHasErrors('patient_name');

        $this->actingAs($user)
            ->post('/patients', ['patient_name' => 'Zulu, Peter'])
            ->assertRedirect();

        $this->assertDatabaseHas('patients', [
            'patient_name' => 'Zulu, Peter',
            'nrc' => null,
        ]);
    }

    public function test_nrc_must_be_unique_when_provided(): void
    {
        $user = $this->staffWithPermissions([PermissionEnum::ViewImages, PermissionEnum::Upload]);
        Patient::factory()->create(['nrc' => '999999/99/1']);

        $this->actingAs($user)
            ->post('/patients', [
                'patient_name' => 'Duplicate NRC',
                'nrc' => '999999/99/1',
            ])
            ->assertSessionHasErrors('nrc');
    }

    public function test_staff_can_view_patient_profile_with_examination_history(): void
    {
        $user = $this->staffWithPermissions([PermissionEnum::ViewImages]);
        $patient = Patient::factory()->create(['patient_name' => 'Mwansa, John']);
        $department = Department::query()->where('code', 'RAD')->firstOrFail();
        $type = ExaminationType::query()->where('code', 'CHEST')->firstOrFail();

        Examination::factory()->create([
            'patient_id' => $patient->id,
            'examination_type_id' => $type->id,
            'referring_department_id' => $department->id,
            'created_by' => $user->id,
            'body_part' => 'Chest',
        ]);

        $this->actingAs($user)
            ->get(route('patients.show', $patient))
            ->assertOk()
            ->assertSee('Mwansa, John')
            ->assertSee('Examination history')
            ->assertSee('Chest');
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
