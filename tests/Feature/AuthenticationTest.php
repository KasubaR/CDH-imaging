<?php

namespace Tests\Feature;

use App\Enums\Permission as PermissionEnum;
use App\Models\Department;
use App\Models\Permission;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_login_page_is_available_to_guests(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Department sign in')
            ->assertSee('Username');
    }

    public function test_authenticated_user_is_redirected_from_login_page(): void
    {
        $user = User::factory()->admin()->create(['username' => 'admin']);

        $this->actingAs($user)
            ->get('/')
            ->assertRedirect('/admin/dashboard');
    }

    public function test_admin_can_log_in_and_is_redirected_to_admin_dashboard(): void
    {
        $department = Department::factory()->create(['is_active' => true]);

        User::factory()->admin()->create([
            'username' => 'admin',
            'password' => 'password',
            'department_id' => $department->id,
            'is_active' => true,
        ]);

        $response = $this->post('/', [
            'username' => 'admin',
            'password' => 'password',
        ]);

        $response->assertRedirect('/admin/dashboard');
        $this->assertAuthenticatedAs(User::query()->where('username', 'admin')->first());
    }

    public function test_staff_can_log_in_and_is_redirected_to_department_dashboard(): void
    {
        $department = Department::factory()->create(['is_active' => true]);

        $staff = User::factory()->create([
            'username' => 'rad.staff',
            'password' => 'password',
            'department_id' => $department->id,
            'role' => 'staff',
            'is_active' => true,
        ]);

        $staff->permissions()->sync(
            Permission::query()->where('slug', PermissionEnum::ViewImages->value)->pluck('id')
        );

        $response = $this->post('/', [
            'username' => 'rad.staff',
            'password' => 'password',
        ]);

        $response->assertRedirect('/department/dashboard');
        $this->assertAuthenticatedAs($staff);
    }

    public function test_invalid_credentials_return_to_login_with_error(): void
    {
        User::factory()->create([
            'username' => 'known.user',
            'password' => 'password',
        ]);

        $response = $this->from('/')->post('/', [
            'username' => 'known.user',
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect('/');
        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        User::factory()->create([
            'username' => 'inactive.user',
            'password' => 'password',
            'is_active' => false,
        ]);

        $response = $this->from('/')->post('/', [
            'username' => 'inactive.user',
            'password' => 'password',
        ]);

        $response->assertRedirect('/');
        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_staff_with_inactive_department_cannot_log_in(): void
    {
        $department = Department::factory()->create(['is_active' => false]);

        User::factory()->create([
            'username' => 'dept.inactive',
            'password' => 'password',
            'department_id' => $department->id,
            'role' => 'staff',
            'is_active' => true,
        ]);

        $response = $this->from('/')->post('/', [
            'username' => 'dept.inactive',
            'password' => 'password',
        ]);

        $response->assertRedirect('/');
        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_admin_login_page_is_available_to_guests(): void
    {
        $this->get('/admin')
            ->assertOk()
            ->assertSee('Admin sign in')
            ->assertSee('Username');
    }

    public function test_guest_cannot_access_protected_routes(): void
    {
        $this->get('/admin/dashboard')->assertRedirect('/login');
        $this->get('/department')->assertRedirect('/login');
        $this->get('/patients')->assertRedirect('/login');
    }

    public function test_staff_without_view_images_permission_cannot_access_department_routes(): void
    {
        $staff = User::factory()->create([
            'role' => 'staff',
            'department_id' => Department::factory()->create(['is_active' => true])->id,
        ]);

        $this->actingAs($staff)
            ->get('/department')
            ->assertForbidden();
    }

    public function test_staff_cannot_access_admin_routes(): void
    {
        $department = Department::factory()->create(['is_active' => true]);

        $staff = User::factory()->create([
            'role' => 'staff',
            'department_id' => $department->id,
        ]);

        $staff->permissions()->sync(
            Permission::query()->where('slug', PermissionEnum::ViewImages->value)->pluck('id')
        );

        $this->actingAs($staff)
            ->get('/admin/dashboard')
            ->assertForbidden();
    }

    public function test_admin_can_access_admin_routes(): void
    {
        $admin = User::factory()->admin()->create([
            'department_id' => Department::factory()->create(['is_active' => true])->id,
        ]);

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('System Dashboard');
    }

    public function test_staff_cannot_log_in_via_admin_login(): void
    {
        $department = Department::factory()->create(['is_active' => true]);

        User::factory()->create([
            'username' => 'rad.staff',
            'password' => 'password',
            'department_id' => $department->id,
            'role' => 'staff',
            'is_active' => true,
        ]);

        $response = $this->from('/admin')->post('/admin', [
            'username' => 'rad.staff',
            'password' => 'password',
        ]);

        $response->assertRedirect('/admin');
        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_admin_can_log_in_via_admin_login(): void
    {
        $department = Department::factory()->create(['is_active' => true]);

        User::factory()->admin()->create([
            'username' => 'admin',
            'password' => 'password',
            'department_id' => $department->id,
            'is_active' => true,
        ]);

        $response = $this->post('/admin', [
            'username' => 'admin',
            'password' => 'password',
        ]);

        $response->assertRedirect('/admin/dashboard');
        $this->assertAuthenticatedAs(User::query()->where('username', 'admin')->first());
    }

    public function test_user_can_log_out(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect('/');

        $this->assertGuest();
    }
}
