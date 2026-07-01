<?php

namespace App\Http\Controllers;

use App\Models\CookieConsent;
use App\Services\CookieConsentService;
use App\Services\LegalAcceptanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LegalApiController extends Controller
{
    public function __construct(
        private readonly LegalAcceptanceService $legalAcceptance,
        private readonly CookieConsentService $cookieConsent,
    ) {}

    public function config(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->legalAcceptance->clientConfig(),
        ]);
    }

    public function documents(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'documents' => $this->legalAcceptance->listPublicDocuments(),
        ]);
    }

    public function document(string $slug): JsonResponse
    {
        $document = $this->legalAcceptance->findDocumentBySlug($slug);
        if (! $document) {
            return response()->json([
                'success' => false,
                'message' => 'Documento no encontrado.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'document' => $document,
        ]);
    }

    public function pendingAcceptances(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'No autorizado.'], 401);
        }

        return response()->json([
            'success' => true,
            'pending' => $this->legalAcceptance->pendingAcceptancesForUser($user),
        ]);
    }

    public function storeCookieConsent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'choice' => 'required|in:all,necessary,custom',
            'cookies_analiticas' => 'nullable|boolean',
        ]);

        $choice = $validated['choice'];
        $analytics = (bool) ($validated['cookies_analiticas'] ?? ($choice === CookieConsent::CHOICE_ALL));

        $result = $this->cookieConsent->store(
            $request,
            $choice,
            $analytics,
            $request->user(),
        );

        $response = response()->json([
            'success' => true,
            'cookies_analiticas' => $result['analytics'],
        ]);

        foreach ($result['cookies'] as $cookie) {
            $response->cookie($cookie);
        }

        return $response;
    }

    public function cookieStatus(Request $request): JsonResponse
    {
        $consent = $this->cookieConsent->readFromRequest($request);

        return response()->json([
            'success' => true,
            'needs_banner' => $this->cookieConsent->needsBanner($request),
            'consent' => $consent,
        ]);
    }
}
