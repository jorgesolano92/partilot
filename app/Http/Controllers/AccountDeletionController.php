<?php

namespace App\Http\Controllers;

use App\Services\AccountDeletionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
