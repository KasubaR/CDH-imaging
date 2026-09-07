<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\Permission as PermissionEnum;
use App\Models\AuditLog;
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
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuditLogTest extends TestCase
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

    public function test_audit_logs_table_has_questionnaire_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('audit_logs', [
            'id',
            'department_id',
            'account_id',
            'action',
            'subject_type',
            'subject_id',
            'ip_address',
            'user_agent',
            'created_at',
        ]));

        $this->assertFalse(Schema::hasColumn('audit_logs', 'user_id'));
        $this->assertFalse(Schema::hasColumn('audit_logs', 'updated_at'));
        $this->assertFalse(Schema::hasColumn('audit_logs', 'old_values'));
    }

    public function test_uploading_an_image_writes_an_uploaded_audit_log(): void
    {
        $user = $this->staffWithPermissions('RAD', [PermissionEnum::ViewImages, PermissionEnum::Upload]);
        $examination = $this->examinationForDepartment('RAD');

        $this->actingAs($user)->post(route('examinations.images.store', $examination), [
            'images' => [UploadedFile::fake()->image('AP.jpg', 40, 40)],
        ])->assertRedirect();

        $image = $examination->images()->sole();
        $log = AuditLog::query()->where('action', AuditAction::Uploaded)->sole();

        $this->assertSame($user->id, $log->account_id);
        $this->assertSame($user->department_id, $log->department_id);
        $this->assertSame(Image::class, $log->subject_type);
        $this->assertSame($image->id, $log->subject_id);
        $this->assertNotNull($log->created_at);
    }

    public function test_sending_and_acknowledging_write_sent_and_received_audit_logs(): void
    {
        $sender = $this->staffWithPermissions('RAD', [PermissionEnum::ViewImages, PermissionEnum::Send]);
        $recipient = $this->staffWithPermissions('OPD', [PermissionEnum::ViewImages, PermissionEnum::Receive]);
        $opd = Department::query()->where('code', 'OPD')->firstOrFail();
        $examination = $this->examinationForDepartment('RAD');

        $this->actingAs($sender)->post(route('examinations.transfers.store', $examination), [
            'department_ids' => [$opd->id],
        ])->assertRedirect();

        $transfer = Transfer::query()->where('examination_id', $examination->id)->sole();
        $sent = AuditLog::query()->where('action', AuditAction::Sent)->sole();
        $this->assertSame($sender->id, $sent->account_id);
        $this->assertSame($transfer->id, $sent->subject_id);

        $this->actingAs($recipient)->post(route('transfers.acknowledge', $transfer))->assertRedirect();

        $received = AuditLog::query()->where('action', AuditAction::Received)->sole();
        $this->assertSame($recipient->id, $received->account_id);
        $this->assertSame($transfer->recipients()->sole()->id, $received->subject_id);
    }

    public function test_viewing_and_downloading_an_image_write_audit_logs(): void
    {
        $user = $this->staffWithPermissions('RAD', [
            PermissionEnum::ViewImages,
            PermissionEnum::Upload,
            PermissionEnum::Download,
        ]);
        $examination = $this->examinationForDepartment('RAD');

        $this->actingAs($user)->post(route('examinations.images.store', $examination), [
            'images' => [UploadedFile::fake()->image('AP.jpg', 40, 40)],
        ]);

        $image = $examination->images()->sole();

        $this->actingAs($user)->get(route('images.show', $image))->assertOk();
        $this->actingAs($user)->get(route('images.download', $image))->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::Viewed->value,
            'account_id' => $user->id,
            'subject_id' => $image->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::Downloaded->value,
            'account_id' => $user->id,
            'subject_id' => $image->id,
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
