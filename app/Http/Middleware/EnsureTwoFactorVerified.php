<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces TOTP 2FA verification for enrolled users and mandatory enrollment for privileged roles
 * when HMIS_2FA_ENFORCED=true.
 */
class EnsureTwoFactorVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if ($request->routeIs('two-factor.*', 'logout', 'locale.switch')) {
            return $next($request);
        }

        // If the user has active confirmed 2FA, require passing the TOTP challenge in this session
        // (including before accessing security settings to disable 2FA)
        if ($user->hasTwoFactorEnabled() && ! $request->session()->get('hmis_2fa_passed', false)) {
            return redirect()->route('two-factor.challenge');
        }

        if ($request->routeIs('security.two-factor.*')) {
            return $next($request);
        }

        // If hospital policy enforces 2FA for privileged roles, force enrollment before clinical/admin access
        if (config('hmis.two_factor_enforced', false) && $user->requiresPrivilegedTwoFactor() && ! $user->hasTwoFactorEnabled()) {
            return redirect()
                ->route('security.two-factor.show')
                ->with('warning', 'Your privileged hospital role requires Two-Factor Authentication (TOTP) enrollment before continuing.');
        }

        return $next($request);
    }
}
