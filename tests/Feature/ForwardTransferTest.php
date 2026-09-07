<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\NotificationType;
use App\Enums\Permission as PermissionEnum;
use App\Models\Department;
use App\Models\Examination;
use App\Models\ExaminationType;
use App\Models\Image;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\Transfer;
use App\Models\User;
use Database\Seeders\DepartmentPermissionSeeder;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\ExaminationTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ForwardTransferTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            DepartmentSeeder::class,
            DepartmentPermissionSeeder::class,
            PermissionSeeder::class,
            ExaminationTypeSeeder::class,
        ]);

        Storage::fake('local');
    }

    public function test_forwarding_creates_a_new_transfer_without_copying_images(): void
    {
        $sender = $this->staffWithPermissions('RAD', [PermissionEnum::ViewImages, PermissionEnum::Upload, PermissionEnum::Send]);
        $opdStaff = $this->staffWithPermissions('OPD', [
            PermissionEnum::ViewImages,
            PermissionEnum::Receive,
            PermissionEnum::Forward,
        ]);
        $orthoStaff = $this->staffWithPermissions('ORTH', [PermissionEnum::ViewImages, PermissionEnum::Receive]);
        $opd = Department::query()->where('code', 'OPD')->firstOrFail();
        $ortho = Department::query()->where('code', 'ORTH')->firstOrFail();
        $examination = $this->examinationForDepartment('RAD');

        $this->actingAs($sender)->post(route('examinations.images.store', $examination), [
            'images' => [UploadedFile::fake()->image('AP.jpg', 40, 40)],
        ]);

        $this->actingAs($sender)->post(route('examinations.transfers.store', $examination), [
            'department_ids' => [$opd->id],
        ]);

        $inbound = Transfer::query()->where('examination_id', $examination->id)->sole();
        $imageCountBefore = Image::query()->count();

        $this->actingAs($opdStaff)
            ->post(route('transfers.acknowledge', $inbound))
            ->assertRedirect();

        $this->actingAs($opdStaff)
            ->post(route('transfers.forward', $inbound), [
                'department_ids' => [$ortho->id],
            ])
            ->assertRedirect();

        $this->assertSame(2, Transfer::query()->where('examination_id', $examination->id)->count());
        $this->assertSame($imageCountBefore, Image::query()->count());

        $forwarded = Transfer::query()
            ->where('examination_id', $examination->id)
            ->where('from_department_id', $opd->id)
            ->sole();

        $this->assertSame($ortho->id, $forwarded->recipients()->sole()->department_id);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $orthoStaff->id,
            'type' => NotificationType::TransferReceived->value,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::Forwarded->value,
            'account_id' => $opdStaff->id,
            'subject_id' => $forwarded->id,
        ]);
    }

    private function examinationForDepartment(string $departmentCode): Examination
    {
        $department = Department::query()->where('code', $departmentCode)->firstOrFail();
        $type = ExaminationType::query()->where('code', 'CHEST')->firstOrFail();

        return Examination::factory()->create([
            'patient_id' => Patient::factory()->create()->id,
            'examination_type_id' => $type->id,
            'referring_department_id' => $department->id,
        ]);
    }

    /**
     * @param  list<PermissionEnum>  $permissions
     */
    private function staffWithPermissions(string $departmentCode, array $permissions): User
    {
        $department = Department::query()->where('code', $departmentCode)->firstOrFail();

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
