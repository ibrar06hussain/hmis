<?php

declare(strict_types=1);

namespace App\Modules\Iam\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'login' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string'],
        ]);

        $loginField = filter_var($credentials['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $attempted = Auth::attempt([
            $loginField => trim($credentials['login']),
            'password' => $credentials['password'],
            'is_active' => true,
        ], $request->boolean('remember'));

        if (! $attempted) {
            $this->auditLogger->log(
                action: 'auth.login_failed',
                actor: null,
                newValues: ['login_identifier' => trim($credentials['login'])],
            );

            return back()
                ->withInput($request->only('login', 'remember'))
                ->withErrors(['login' => 'Invalid credentials or inactive staff account.']);
        }

        $request->session()->regenerate();

        /** @var User $user */
        $user = Auth::user();
        $user->forceFill([
            'last_login_at' => Carbon::now('UTC'),
            'last_login_ip' => $request->ip(),
        ])->save();

        $this->auditLogger->log(
            action: 'auth.login_succeeded',
            actor: $user,
            subject: $user,
        );

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user !== null) {
            $this->auditLogger->log(
                action: 'auth.logout',
                actor: $user,
                subject: $user,
            );
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'You have been securely signed out.');
    }
}
