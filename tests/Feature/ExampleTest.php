<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_application_returns_department_login_for_guests(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Department sign in');
        $response->assertSee('Chililabombwe District Hospital');
    }

    public function test_login_url_redirects_to_home(): void
    {
        $this->get('/login')->assertRedirect('/');
    }

    public function test_area_pages_are_available(): void
    {
        $admin = User::factory()->admin()->create([
            'department_id' => Department::factory()->create()->id,
        ]);

        $this->actingAs($admin);

        $this->get('/admin/dashboard')->assertOk()->assertSee('System Dashboard');
        $this->get('/department')->assertOk()->assertSee('Inbox');

        $patient = Patient::factory()->create(['patient_name' => 'Mwansa, John']);
        $this->get('/patients')->assertOk()->assertSee('Mwansa, John');
        $this->get('/')->assertRedirect('/admin/dashboard');
    }

    public function test_api_health_endpoint_returns_ok(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJson([
                'status' => 'ok',
                'app' => 'CDH',
            ]);
    }
}
