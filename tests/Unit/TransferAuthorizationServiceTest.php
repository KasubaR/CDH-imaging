<?php

namespace Tests\Unit;

use App\Models\Department;
use App\Models\DepartmentPermission;
use App\Services\TransferAuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransferAuthorizationServiceTest extends TestCase
{
    use RefreshDatabase;

    private TransferAuthorizationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(TransferAuthorizationService::class);
    }

    public function test_transfer_is_allowed_when_permission_row_has_send_and_receive(): void
    {
        $from = Department::factory()->create(['is_active' => true]);
        $to = Department::factory()->create(['is_active' => true]);

        DepartmentPermission::query()->create([
            'from_department_id' => $from->id,
            'to_department_id' => $to->id,
            'can_send' => true,
            'can_receive' => true,
        ]);

        $this->assertTrue($this->service->canTransfer($from, $to));
    }

    public function test_transfer_is_denied_when_permission_row_is_missing(): void
    {
        $from = Department::factory()->create(['is_active' => true]);
        $to = Department::factory()->create(['is_active' => true]);

        $this->assertFalse($this->service->canTransfer($from, $to));
    }

    public function test_transfer_is_denied_when_only_can_send_is_true(): void
    {
        $from = Department::factory()->create(['is_active' => true]);
        $to = Department::factory()->create(['is_active' => true]);

        DepartmentPermission::query()->create([
            'from_department_id' => $from->id,
            'to_department_id' => $to->id,
            'can_send' => true,
            'can_receive' => false,
        ]);

        $this->assertFalse($this->service->canTransfer($from, $to));
    }

    public function test_transfer_is_denied_when_destination_department_is_inactive(): void
    {
        $from = Department::factory()->create(['is_active' => true]);
        $to = Department::factory()->create(['is_active' => false]);

        DepartmentPermission::query()->create([
            'from_department_id' => $from->id,
            'to_department_id' => $to->id,
            'can_send' => true,
            'can_receive' => true,
        ]);

        $this->assertFalse($this->service->canTransfer($from, $to));
    }

    public function test_transfer_is_denied_for_same_department(): void
    {
        $department = Department::factory()->create(['is_active' => true]);

        $this->assertFalse($this->service->canTransfer($department, $department));
    }
}
