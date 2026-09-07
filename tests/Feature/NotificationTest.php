<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Enums\Permission as PermissionEnum;
use App\Models\Department;
use App\Models\Examination;
use App\Models\ExaminationType;
use App\Models\Notification;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\Transfer;
use App\Models\User;
use Database\Seeders\DepartmentPermissionSeeder;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\ExaminationTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
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
    }

    public function test_sending_a_transfer_notifies_active_staff_in_destination_departments(): void
    {
        $sender = $this->staffWithPermissions('RAD', [PermissionEnum::ViewImages, PermissionEnum::Send]);
        $opdActive = $this->staffWithPermissions('OPD', [PermissionEnum::ViewImages]);
        $opdInactive = $this->staffWithPermissions('OPD', [PermissionEnum::ViewImages], active: false);
        $nursingStaff = $this->staffWithPermissions('NURS', [PermissionEnum::ViewImages]);
        $opd = Department::query()->where('code', 'OPD')->firstOrFail();

        $patient = Patient::factory()->create(['patient_name' => 'John Banda']);
        $type = ExaminationType::query()->where('code', 'CHEST')->firstOrFail();
        $examination = Examination::factory()->create([
            'patient_id' => $patient->id,
            'examination_type_id' => $type->id,
            'referring_department_id' => $sender->department_id,
        ]);

        $this->actingAs($sender)->post(route('examinations.transfers.store', $examination), [
            'department_ids' => [$opd->id],
        ])->assertRedirect();

        $transfer = Transfer::query()->where('examination_id', $examination->id)->sole();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $opdActive->id,
            'type' => NotificationType::TransferReceived->value,
            'title' => 'New X-ray received',
        ]);

        $notification = Notification::query()->where('user_id', $opdActive->id)->sole();
        $this->assertNull($notification->read_at);
        $this->assertSame(
            "Patient: John Banda\nExamination: Chest\nFrom: Radiology",
            $notification->body,
        );
        $this->assertSame([
            'transfer_id' => $transfer->id,
            'examination_id' => $examination->id,
            'from_department_id' => $sender->department_id,
        ], $notification->data);

        $this->assertDatabaseMissing('notifications', ['user_id' => $sender->id]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $opdInactive->id]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $nursingStaff->id]);
    }

    public function test_sending_to_two_departments_fans_out_notifications(): void
    {
        $sender = $this->staffWithPermissions('RAD', [PermissionEnum::ViewImages, PermissionEnum::Send]);
        $opdStaff = $this->staffWithPermissions('OPD', [PermissionEnum::ViewImages]);
        $orthoStaff = $this->staffWithPermissions('ORTH', [PermissionEnum::ViewImages]);
        $opd = Department::query()->where('code', 'OPD')->firstOrFail();
        $ortho = Department::query()->where('code', 'ORTH')->firstOrFail();
        $examination = $this->examinationForDepartment('RAD');

        $this->actingAs($sender)->post(route('examinations.transfers.store', $examination), [
            'department_ids' => [$opd->id, $ortho->id],
        ])->assertRedirect();

        $this->assertDatabaseHas('notifications', ['user_id' => $opdStaff->id]);
        $this->assertDatabaseHas('notifications', ['user_id' => $orthoStaff->id]);
        $this->assertSame(2, Notification::query()->count());
    }

    public function test_recipient_topbar_shows_notification_copy_and_unread_badge(): void
    {
        $sender = $this->staffWithPermissions('RAD', [PermissionEnum::ViewImages, PermissionEnum::Send]);
        $opdStaff = $this->staffWithPermissions('OPD', [PermissionEnum::ViewImages, PermissionEnum::Receive]);
        $opd = Department::query()->where('code', 'OPD')->firstOrFail();

        $patient = Patient::factory()->create(['patient_name' => 'John Banda']);
        $type = ExaminationType::query()->where('code', 'CHEST')->firstOrFail();
        $examination = Examination::factory()->create([
            'patient_id' => $patient->id,
            'examination_type_id' => $type->id,
            'referring_department_id' => $sender->department_id,
        ]);

        $this->actingAs($sender)->post(route('examinations.transfers.store', $examination), [
            'department_ids' => [$opd->id],
        ]);

        $response = $this->actingAs($opdStaff)->get(route('department.index'));

        $response->assertOk();
        $response->assertSee('New X-ray received');
        $response->assertSee('Patient: John Banda');
        $response->assertSee('Examination: Chest');
        $response->assertSee('From: Radiology');
        $response->assertSee('aria-label="1 unread"', false);
        $response->assertSee('topbar__notification-badge', false);
    }

    public function test_opening_a_notification_marks_it_read_and_redirects_to_inbox(): void
    {
        $user = $this->staffWithPermissions('OPD', [PermissionEnum::ViewImages]);
        $notification = Notification::factory()->unread()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('notifications.show', $notification))
            ->assertRedirect(route('department.index'));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_another_user_cannot_open_someone_elses_notification(): void
    {
        $owner = $this->staffWithPermissions('OPD', [PermissionEnum::ViewImages]);
        $outsider = $this->staffWithPermissions('NURS', [PermissionEnum::ViewImages]);
        $notification = Notification::factory()->unread()->create(['user_id' => $owner->id]);

        $this->actingAs($outsider)
            ->get(route('notifications.show', $notification))
            ->assertForbidden();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_guests_are_redirected_from_notification_routes(): void
    {
        $notification = Notification::factory()->create();

        $this->get(route('notifications.show', $notification))
            ->assertRedirect('/login');

        $this->post(route('notifications.read-all'))
            ->assertRedirect('/login');
    }

    public function test_mark_all_as_read_only_affects_the_current_user(): void
    {
        $user = $this->staffWithPermissions('OPD', [PermissionEnum::ViewImages]);
        $other = $this->staffWithPermissions('NURS', [PermissionEnum::ViewImages]);

        $mine = Notification::factory()->unread()->count(2)->create(['user_id' => $user->id]);
        $theirs = Notification::factory()->unread()->create(['user_id' => $other->id]);

        $this->actingAs($user)
            ->from(route('department.index'))
            ->post(route('notifications.read-all'))
            ->assertRedirect(route('department.index'));

        foreach ($mine as $notification) {
            $this->assertNotNull($notification->fresh()->read_at);
        }

        $this->assertNull($theirs->fresh()->read_at);
    }

    public function test_dangerous_patient_name_is_escaped_in_the_topbar(): void
    {
        $sender = $this->staffWithPermissions('RAD', [PermissionEnum::ViewImages, PermissionEnum::Send]);
        $opdStaff = $this->staffWithPermissions('OPD', [PermissionEnum::ViewImages, PermissionEnum::Receive]);
        $opd = Department::query()->where('code', 'OPD')->firstOrFail();

        $patient = Patient::factory()->create([
            'patient_name' => "O'Reilly <script>alert('xss')</script>",
        ]);
        $type = ExaminationType::query()->where('code', 'CHEST')->firstOrFail();
        $examination = Examination::factory()->create([
            'patient_id' => $patient->id,
            'examination_type_id' => $type->id,
            'referring_department_id' => $sender->department_id,
        ]);

        $this->actingAs($sender)->post(route('examinations.transfers.store', $examination), [
            'department_ids' => [$opd->id],
        ]);

        $html = $this->actingAs($opdStaff)->get(route('department.index'))->getContent();

        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString("<script>alert('xss')</script>", $html);
    }

    private function examinationForDepartment(string $departmentCode): Examination
    {
        $department = Department::query()->where('code', $departmentCode)->firstOrFail();
        $type = ExaminationType::query()->where('code', 'CHEST')->firstOrFail();

        return Examination::factory()->create([
            'examination_type_id' => $type->id,
            'referring_department_id' => $department->id,
        ]);
    }

    /**
     * @param  list<PermissionEnum>  $permissions
     */
    private function staffWithPermissions(string $departmentCode, array $permissions, bool $active = true): User
    {
        $department = Department::query()->where('code', $departmentCode)->firstOrFail();

        $user = User::factory()->create([
            'department_id' => $department->id,
            'role' => 'staff',
            'is_active' => $active,
        ]);

        $permissionIds = Permission::query()
            ->whereIn('slug', array_map(fn (PermissionEnum $permission) => $permission->value, $permissions))
            ->pluck('id');

        $user->permissions()->sync($permissionIds);

        return $user->fresh(['permissions', 'department']);
    }
}
