<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AppPasswordResetService;
use App\Support\PasswordRules;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AppPasswordResetController extends Controller
{
    public function __construct(
        private AppPasswordResetService $passwordResetService,
    ) {}

    public function forgot(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|max:255',
        ], [
            'email.required' => 'Indique su email.',
            'email.email' => 'Indique un email válido.',
        ]);

        $this->passwordResetService->sendResetLink((string) $request->input('email'));

        return response()->json([
            'success' => true,
            'message' => 'Si existe una cuenta con ese email, recibirá un correo con un enlace para restablecer la contraseña.',
        ]);
    }

    public function reset(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => PasswordRules::registration(),
        ], PasswordRules::messages());

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                if (! $this->passwordResetService->canRequestReset($user)) {
                    throw ValidationException::withMessages([
                        'email' => 'No se pudo restablecer la contraseña de esta cuenta.',
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
            return response()->json([
                'success' => true,
                'message' => 'Contraseña actualizada. Ya puede iniciar sesión con la nueva contraseña.',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $status === Password::INVALID_TOKEN
                ? 'El enlace no es válido o ha caducado. Solicite uno nuevo.'
                : 'No se pudo restablecer la contraseña. Solicite un nuevo enlace.',
        ], 422);
    }
}
