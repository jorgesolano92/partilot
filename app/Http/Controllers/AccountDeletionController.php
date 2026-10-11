<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AccountDeletionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AccountDeletionController extends Controller
{
    public function __construct(
        private readonly AccountDeletionService $accountDeletion,
    ) {}

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'No autorizado.'], 401);
        }

        return response()->json([
            'success' => true,
            'status' => $this->accountDeletion->statusForUser($user),
        ]);
    }

    /**
     * Reactivación durante el periodo de gracia. Pública: la cuenta ya no admite tokens.
     */
    public function cancel(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])->first();
        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Las credenciales proporcionadas no coinciden con nuestros registros.',
            ], 401);
        }

        if (! $this->accountDeletion->cancelDeletion($user, $request)) {
            return response()->json([
                'success' => false,
                'message' => 'Esta cuenta no tiene una solicitud de baja que se pueda anular.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Tu cuenta se ha reactivado. Ya puedes iniciar sesión.',
        ]);
    }

    public function request(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'No autorizado.'], 401);
        }

        $validated = $request->validate([
            'email_confirm' => 'required|string|email|max:255',
        ]);

        $result = $this->accountDeletion->requestDeletion(
            $user,
            $request,
            (string) $validated['email_confirm']
        );

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'status' => $result['status'] ?? $this->accountDeletion->statusForUser($user->fresh()),
        ], $result['success'] ? 200 : 422);
    }
}
