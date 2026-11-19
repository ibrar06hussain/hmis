<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Iam;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Iam\Services\TotpService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TwoFactorAndLocalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('hmis.demo_mode', true);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_totp_service_generates_verifies_and_consumes_single_use_recovery_codes(): void
    {
        $service = app(TotpService::class);

        $secret = $service->generateSecret();
        $this->assertSame(32, strlen($secret));

        $timestamp = 1760000000;
        $validCode = $service->currentCode($secret, $timestamp);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $validCode);
        $this->assertTrue($service->verifyCode($secret, $validCode, window: 1, timestamp: $timestamp));
        $this->assertTrue($service->verifyCode($secret, $validCode, window: 1, timestamp: $timestamp + 30)); // ±1 step drift tolerance
        $this->assertFalse($service->verifyCode($secret, $validCode, window: 1, timestamp: $timestamp + 120));

        $uri = $service->provisioningUri('consultant', $secret);
        $this->assertStringStartsWith('otpauth://totp/', $uri);
        $this->assertStringContainsString('secret='.$secret, $uri);

        $recovery = $service->generateRecoveryCodes(8);
        $this->assertCount(8, $recovery['plain']);
        $this->assertCount(8, $recovery['hashed']);

        $firstPlain = $recovery['plain'][0];
        $remaining = $service->consumeRecoveryCode($firstPlain, $recovery['hashed']);
        $this->assertNotNull($remaining);
        $this->assertCount(7, $remaining);

        // Consumed recovery code cannot be reused
        $this->assertNull($service->consumeRecoveryCode($firstPlain, $remaining));
    }

    public function test_locale_switcher_toggles_urdu_rtl_and_english_ltr_and_rejects_unsupported_locales(): void
    {
        $this->from('/login')
            ->post(route('locale.switch'), ['locale' => 'ur'])
            ->assertRedirect('/login')
            ->assertSessionHas('hmis_locale', 'ur');

        $this->withSession(['hmis_locale' => 'ur'])
            ->get('/login')
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('کارڈیک ہسپتال گلگت')
            ->assertSee('سسٹم میں لاگ ان کریں');

        $consultant = User::query()->where('username', 'consultant')->firstOrFail();

        $this->actingAs($consultant)
            ->withSession(['hmis_locale' => 'ur'])
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('ڈیش بورڈ')
            ->assertSee('دوہری تصدیق (2FA)');

        $this->post(route('logout'));

        $this->from('/login')
            ->post(route('locale.switch'), ['locale' => 'en'])
            ->assertRedirect('/login')
            ->assertSessionHas('hmis_locale', 'en');

        $this->withSession(['hmis_locale' => 'en'])
            ->get('/login')
            ->assertOk()
            ->assertSee('dir="ltr"', false)
            ->assertSee('Sign In to HMIS');

        $this->from('/login')
            ->post(route('locale.switch'), ['locale' => 'fr'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('locale');
    }

    public function test_full_totp_enrollment_login_challenge_recovery_code_and_password_guarded_disable(): void
    {
        $service = app(TotpService::class);
        $consultant = User::query()->where('username', 'consultant')->firstOrFail();

        // 1. Open setup page to generate pending Base32 secret
        $setupResponse = $this->actingAs($consultant)->get(route('security.two-factor.show'))->assertOk();
        $pendingSecret = (string) session('hmis_2fa_pending_secret');
        $this->assertNotSame('', $pendingSecret);
        $setupResponse->assertSee($pendingSecret);

        // 2. Reject invalid confirmation code
        $this->actingAs($consultant)
            ->post(route('security.two-factor.confirm'), ['code' => '999999'])
            ->assertSessionHasErrors('code');

        $consultant->refresh();
        $this->assertFalse($consultant->hasTwoFactorEnabled());

        // 3. Confirm with valid live TOTP code -> enables 2FA and returns 8 recovery codes once
        $confirmResponse = $this->actingAs($consultant)
            ->post(route('security.two-factor.confirm'), ['code' => $service->currentCode($pendingSecret)])
            ->assertRedirect(route('security.two-factor.show'))
            ->assertSessionHas('hmis_2fa_new_recovery_codes');

        $plainRecoveryCodes = (array) session('hmis_2fa_new_recovery_codes');
        $this->assertCount(8, $plainRecoveryCodes);

        $consultant->refresh();
        $this->assertTrue($consultant->hasTwoFactorEnabled());
        $this->assertTrue(AuditLog::query()->where('action', 'security.2fa.enrolled')->where('actor_user_id', $consultant->id)->exists());

        // 4. Logout & re-login -> redirected to 2FA challenge, blocked from dashboard & security setup until verified
        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->post(route('login.attempt'), [
            'login' => 'consultant',
            'password' => 'DemoHmis@2026!',
        ])->assertRedirect(route('two-factor.challenge'));

        $this->get('/dashboard')->assertRedirect(route('two-factor.challenge'));
        $this->get(route('security.two-factor.show'))->assertRedirect(route('two-factor.challenge'));
        $this->delete(route('security.two-factor.disable'), ['current_password' => 'DemoHmis@2026!'])
            ->assertRedirect(route('two-factor.challenge'));

        // 5. Failed 2FA attempt is audited
        $this->post(route('two-factor.verify'), ['code' => '000000'])
            ->assertSessionHasErrors('code');
        $this->assertTrue(AuditLog::query()->where('action', 'auth.2fa_failed')->where('actor_user_id', $consultant->id)->exists());

        // 6. Valid TOTP code unlocks dashboard
        $this->post(route('two-factor.verify'), ['code' => $service->currentCode((string) $consultant->two_factor_secret)])
            ->assertRedirect(route('dashboard'));
        $this->get('/dashboard')->assertOk();
        $this->assertTrue(AuditLog::query()->where('action', 'auth.2fa_verified')->where('actor_user_id', $consultant->id)->exists());

        // 7. Re-login and verify using a single-use recovery code
        $this->post(route('logout'));
        $this->post(route('login.attempt'), [
            'login' => 'consultant',
            'password' => 'DemoHmis@2026!',
        ])->assertRedirect(route('two-factor.challenge'));

        $usedRecoveryCode = $plainRecoveryCodes[0];
        $this->post(route('two-factor.verify'), ['code' => $usedRecoveryCode])
            ->assertRedirect(route('dashboard'));
        $this->assertTrue(AuditLog::query()->where('action', 'auth.2fa_recovery_used')->where('actor_user_id', $consultant->id)->exists());

        // 8. Re-login and verify the SAME recovery code fails on reuse
        $this->post(route('logout'));
        $this->post(route('login.attempt'), [
            'login' => 'consultant',
            'password' => 'DemoHmis@2026!',
        ])->assertRedirect(route('two-factor.challenge'));

        $this->post(route('two-factor.verify'), ['code' => $usedRecoveryCode])
            ->assertSessionHasErrors('code');

        // Pass challenge with TOTP to test disablement
        $this->post(route('two-factor.verify'), ['code' => $service->currentCode((string) $consultant->two_factor_secret)])
            ->assertRedirect(route('dashboard'));

        // 9. Disabling requires correct current password
        $this->delete(route('security.two-factor.disable'), ['current_password' => 'WrongPass123!'])
            ->assertSessionHasErrors('current_password');

        $this->delete(route('security.two-factor.disable'), ['current_password' => 'DemoHmis@2026!'])
            ->assertRedirect(route('security.two-factor.show'));

        $consultant->refresh();
        $this->assertFalse($consultant->hasTwoFactorEnabled());
        $this->assertTrue(AuditLog::query()->where('action', 'security.2fa.disabled')->where('actor_user_id', $consultant->id)->exists());
    }

    public function test_privileged_roles_are_redirected_to_enrollment_when_policy_enforced_while_non_privileged_proceed(): void
    {
        config()->set('hmis.two_factor_enforced', true);

        $consultant = User::query()->where('username', 'consultant')->firstOrFail();
        $itAdmin = User::query()->where('username', 'it_admin')->firstOrFail();
        $reception = User::query()->where('username', 'reception')->firstOrFail();

        $this->actingAs($consultant)
            ->get('/dashboard')
            ->assertRedirect(route('security.two-factor.show'));

        $this->actingAs($itAdmin)
            ->get('/dashboard')
            ->assertRedirect(route('security.two-factor.show'));

        // Receptionist is not in privileged roles list and can access dashboard directly
        $this->actingAs($reception)
            ->get('/dashboard')
            ->assertOk();
    }
}
