<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_service_falls_back_to_the_config_default_with_no_override(): void
    {
        $settings = app(SettingsService::class);

        $this->assertSame((int) config('cdh.image_retention_months'), $settings->get('image_retention_months'));
    }

    public function test_settings_service_returns_a_stored_override(): void
    {
        $settings = app(SettingsService::class);

        $settings->set('image_retention_months', 3);

        $this->assertSame(3, $settings->get('image_retention_months'));
        $this->assertDatabaseHas('settings', ['key' => 'image_retention_months', 'value' => '3']);
    }

    public function test_admin_can_view_the_settings_page(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertSee('Image retention (months)');
    }

    public function test_admin_can_update_settings(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->put(route('admin.settings.update'), [
            'image_retention_months' => 3,
            'image_max_mb' => 10,
            'upload_session_ttl_hours' => 12,
            'backup_retention_days' => 30,
        ]);

        $response->assertRedirect(route('admin.settings.edit'));

        $settings = app(SettingsService::class);
        $this->assertSame(3, $settings->get('image_retention_months'));
        $this->assertSame(10 * 1024 * 1024, $settings->get('image_max_bytes'));
        $this->assertSame(12, $settings->get('upload_session_ttl_hours'));
        $this->assertSame(30, $settings->get('backup_retention_days'));
    }

    public function test_updating_settings_validates_each_field(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'image_retention_months' => 0,
            'image_max_mb' => 0,
            'upload_session_ttl_hours' => 0,
            'backup_retention_days' => 0,
        ])->assertSessionHasErrors([
            'image_retention_months',
            'image_max_mb',
            'upload_session_ttl_hours',
            'backup_retention_days',
        ]);
    }

    public function test_staff_cannot_view_or_update_settings(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->get(route('admin.settings.edit'))
            ->assertForbidden();

        $this->actingAs($staff)
            ->put(route('admin.settings.update'), [
                'image_retention_months' => 3,
                'image_max_mb' => 10,
                'upload_session_ttl_hours' => 12,
                'backup_retention_days' => 30,
            ])
            ->assertForbidden();
    }

    private function admin(): User
    {
        return User::factory()->admin()->create([
            'department_id' => Department::factory()->create(['is_active' => true])->id,
        ]);
    }
}
