<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\PatientAccount;
use App\Services\Audit\AuditLogger;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Acceso al portal del paciente (guard "patient", separado del personal).
 */
class PortalAuthController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function showLogin(): Response
    {
        return Inertia::render('portal/login', ['status' => session('status')]);
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate(
            ['email' => ['required', 'email'], 'password' => ['required', 'string']],
            attributes: ['email' => 'correo electrónico', 'password' => 'contraseña'],
        );

        $key = 'portal-login|'.Str::lower($credentials['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Demasiados intentos. Espere '.RateLimiter::availableIn($key).' segundos.']);
        }

        $valid = Auth::guard('patient')->attempt([
            'email' => Str::lower($credentials['email']),
            'password' => $credentials['password'],
            'is_active' => true,
        ]);

        if (! $valid) {
            RateLimiter::hit($key, 60);
            $this->audit->record('portal_login_failed', new: ['email' => Str::lower($credentials['email'])]);

            throw ValidationException::withMessages(['email' => 'El correo o la contraseña no son correctos.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        /** @var PatientAccount $account */
        $account = Auth::guard('patient')->user();
        $account->forceFill(['last_login_at' => now()])->saveQuietly();
        $this->audit->record('portal_login', $account);

        return redirect()->intended(route('portal.home'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('patient')->logout();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login');
    }

    public function showForgot(): Response
    {
        return Inertia::render('portal/forgot-password', ['status' => session('status')]);
    }

    public function sendLink(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email']], attributes: ['email' => 'correo electrónico']);

        Password::broker('patients')->sendResetLink(['email' => Str::lower($data['email'])]);

        // Mismo mensaje exista o no la cuenta: no revela qué correos están registrados.
        return back()->with('status', 'Si el correo está registrado, le enviamos un enlace para crear una contraseña nueva.');
    }

    public function editPassword(Request $request, string $token): Response
    {
        return Inertia::render('portal/set-password', ['token' => $token, 'email' => (string) $request->query('email')]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(10)->letters()->numbers()],
        ], attributes: ['email' => 'correo electrónico', 'password' => 'contraseña']);

        $status = Password::broker('patients')->reset(
            ['email' => Str::lower($data['email']), 'password' => $data['password'], 'password_confirmation' => $request->input('password_confirmation'), 'token' => $data['token']],
            function (PatientAccount $account, string $password) {
                $account->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                event(new PasswordReset($account));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'El enlace no es válido o venció. Pida uno nuevo al consultorio.']);
        }

        return redirect()->route('portal.login')->with('status', 'Contraseña creada. Ya puede ingresar.');
    }
}
