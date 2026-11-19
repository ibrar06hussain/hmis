<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Iam;

use App\Models\User;
use Database\Seeders\HospitalOrganizationSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationAndAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(HospitalOrganizationSeeder::class);
    }

    public function test_unauthenticated_users_are_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/patients')->assertRedirect('/login');
    }

    public function test_staff_can_login_with_username_and_audit_log_is_recorded(): void
    {
        $user = User::query()->create([
            'name' => 'Test Receptionist',
            'username' => 'rec_test',
            'email' => 'rec@chg.test',
            'password' => Hash::make('SecretPass123!'),
            'is_active' => true,
        ]);
        $user->assignRole('receptionist');

        $response = $this->post('/login', [
            'login' => 'rec_test',
            'password' => 'SecretPass123!',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.login_succeeded',
            'actor_user_id' => $user->id,
        ]);

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.logout',
            'actor_user_id' => $user->id,
        ]);
    }

    public function test_failed_login_and_inactive_accounts_are_blocked_and_audited(): void
    {
        $inactive = User::query()->create([
            'name' => 'Suspended Staff',
            'username' => 'suspended',
            'email' => 'suspended@chg.test',
            'password' => Hash::make('SecretPass123!'),
            'is_active' => false,
        ]);
        $inactive->assignRole('receptionist');

        $this->post('/login', [
            'login' => 'suspended',
            'password' => 'SecretPass123!',
        ])->assertSessionHasErrors('login');

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.login_failed',
        ]);
    }
}
