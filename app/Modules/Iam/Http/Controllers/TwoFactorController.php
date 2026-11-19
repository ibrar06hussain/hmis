<?php

declare(strict_types=1);

namespace App\Modules\Iam\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Iam\Services\TotpService;
use App\Support\OfflineQrSvg;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class TwoFactorController extends Controller
{
    public function __construct(
        private readonly TotpService $totpService,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function showSetup(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $pendingSecret = null;
        $provisioningUri = null;
        $qrSvg = null;

        if (! $user->hasTwoFactorEnabled()) {
            $pendingSecret = (string) $request->session()->get('hmis_2fa_pending_secret');
            if ($pendingSecret === '') {
                $pendingSecret = $this->totpService->generateSecret();
                $request->session()->put('hmis_2fa_pending_secret', $pendingSecret);
            }
            $provisioningUri = $this->totpService->provisioningUri($user->username ?: $user->email, $pendingSecret);
            $qrSvg = OfflineQrSvg::render($provisioningUri, 200);
        }

        return view('modules.iam.two-factor-setup', [
            'user' => $user,
            'pendingSecret' => $pendingSecret,
            'provisioningUri' => $provisioningUri,
            'qrSvg' => $qrSvg,
            'newRecoveryCodes' => session('hmis_2fa_new_recovery_codes', []),
        ]);
    }

    public function confirmSetup(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'min:6', 'max:10'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $pendingSecret = (string) $request->session()->get('hmis_2fa_pending_secret');

        if ($pendingSecret === '') {
            return redirect()->route('security.two-factor.show')->withErrors(['code' => 'Setup session expired. Please try again.']);
        }

        if (! $this->totpService->verifyCode($pendingSecret, (string) $validated['code'])) {
            return back()->withErrors(['code' => 'Invalid 6-digit authenticator code. Verify your device clock and try again.']);
        }

        $recovery = $this->totpService->generateRecoveryCodes(8);

        $user->forceFill([
            'two_factor_secret' => $pendingSecret,
            'two_factor_recovery_codes' => $recovery['hashed'],
            'two_factor_confirmed_at' => Carbon::now('UTC'),
        ])->save();

        $request->session()->forget('hmis_2fa_pending_secret');
        $request->session()->put('hmis_2fa_passed', true);

        $this->auditLogger->log(
            action: 'security.2fa.enrolled',
            actor: $user,
            subject: $user,
        );

        return redirect()
            ->route('security.two-factor.show')
            ->with('status', 'Two-Factor Authentication (TOTP) enabled. Save your one-time recovery codes securely offline.')
            ->with('hmis_2fa_new_recovery_codes', $recovery['plain']);
    }

    public function disable(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
        ]);

        /** @var User $user */
        $user = $request->user();

        if (! Hash::check((string) $validated['current_password'], (string) $user->password)) {
            return back()->withErrors(['current_password' => 'Current password does not match.']);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $request->session()->forget('hmis_2fa_passed');

        $this->auditLogger->log(
            action: 'security.2fa.disabled',
            actor: $user,
            subject: $user,
        );

        return redirect()
            ->route('security.two-factor.show')
            ->with('warning', 'Two-Factor Authentication has been disabled for your account.');
    }

    public function showChallenge(Request $request): View|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->hasTwoFactorEnabled() || $request->session()->get('hmis_2fa_passed', false)) {
            return redirect()->route('dashboard');
        }

        return view('auth.two-factor-challenge');
    }

    public function verifyChallenge(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'min:6', 'max:24'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $input = trim((string) $validated['code']);

        // Try 6-digit TOTP first
        if ($user->two_factor_secret && $this->totpService->verifyCode((string) $user->two_factor_secret, $input)) {
            $request->session()->put('hmis_2fa_passed', true);

            $this->auditLogger->log(
                action: 'auth.2fa_verified',
                actor: $user,
                subject: $user,
            );

            return redirect()->intended(route('dashboard'));
        }

        // Try single-use recovery code
        $storedHashes = (array) ($user->two_factor_recovery_codes ?? []);
        $remaining = $this->totpService->consumeRecoveryCode($input, $storedHashes);

        if ($remaining !== null) {
            $user->forceFill(['two_factor_recovery_codes' => $remaining])->save();
            $request->session()->put('hmis_2fa_passed', true);

            $this->auditLogger->log(
                action: 'auth.2fa_recovery_used',
                actor: $user,
                subject: $user,
                newValues: ['remaining_recovery_codes' => count($remaining)],
            );

            return redirect()
                ->intended(route('dashboard'))
                ->with('warning', 'Signed in using a single-use recovery code ('.count($remaining).' remaining).');
        }

        $this->auditLogger->log(
            action: 'auth.2fa_failed',
            actor: $user,
            subject: $user,
        );

        return back()->withErrors(['code' => 'Invalid authenticator or recovery code.']);
    }
}
