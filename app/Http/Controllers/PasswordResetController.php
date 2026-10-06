<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\PanelPasswordResetService;
use App\Support\PasswordRules;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    public function __construct(
        private PanelPasswordResetService $passwordResetService,
    ) {}

    public function showForgotForm()
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request)
    {
        $request->validate([
            'email' => 'required|string|max:255',
        ], [
            'email.required' => 'Indique su email o usuario de panel.',
        ]);

        $this->passwordResetService->sendResetLink((string) $request->input('email'));

        return back()->with(
            'success',
            'Si existe una cuenta de panel con esos datos, recibirá un correo con instrucciones para restablecer la contraseña.'
        );
    }

    public function showResetForm(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => PasswordRules::registration(),
        ], PasswordRules::messages());

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                if (! $user->canAccessWebPanel() || $user->isAdministrationContactOnly()) {
                    throw ValidationException::withMessages([
                        'email' => __('passwords.user'),
                    ]);
                }

                $user->forceFill([
                    'password' => $password,
                    'must_change_password' => false,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with(
                'success',
                'Contraseña actualizada. Ya puede iniciar sesión con la nueva contraseña.'
            );
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => __($status)]);
    }
}
