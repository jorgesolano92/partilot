<?php

namespace App\Http\Controllers;

use App\Services\EntityLotteryPrizePaymentService;
use Illuminate\Http\Request;

class PrizePaymentContractController extends Controller
{
    public function __construct(
        private EntityLotteryPrizePaymentService $prizePaymentService
    ) {}

    public function show(string $token)
    {
        $setting = $this->findPendingByToken($token);
        if (! $setting) {
            return view('prize_payments.contract-result', [
                'success' => false,
                'title' => 'Enlace no válido',
                'message' => 'El enlace de firma no es válido o el contrato ya fue gestionado.',
            ]);
        }

        $setting->load(['entity', 'lottery']);

        return view('prize_payments.contract-sign', [
            'setting' => $setting,
            'token' => $token,
        ]);
    }

    public function store(Request $request, string $token)
    {
        $setting = $this->findPendingByToken($token);
        if (! $setting) {
            return view('prize_payments.contract-result', [
                'success' => false,
                'title' => 'Enlace no válido',
                'message' => 'El enlace de firma no es válido o el contrato ya fue gestionado.',
            ]);
        }

        $data = $request->validate([
            'signer_name' => 'required|string|max:255',
            'accept_terms' => 'accepted',
        ], [
            'accept_terms.accepted' => 'Debes aceptar el contrato para continuar.',
        ]);

        try {
            $this->prizePaymentService->signContractByToken(
                $token,
                $data['signer_name'],
                auth()->id(),
                $request->ip()
            );
        } catch (\InvalidArgumentException $e) {
            return view('prize_payments.contract-result', [
                'success' => false,
                'title' => 'No se pudo firmar',
                'message' => $e->getMessage(),
            ]);
        }

        return view('prize_payments.contract-result', [
            'success' => true,
            'title' => 'Contrato firmado',
            'message' => 'El contrato ha quedado registrado correctamente. PARTILOT gestionará la activación del cobro cuando corresponda.',
        ]);
    }

    protected function findPendingByToken(string $token)
    {
        return \App\Models\EntityLotteryPrizeSetting::query()
            ->where('contract_token', $token)
            ->where('contract_status', \App\Models\EntityLotteryPrizeSetting::CONTRACT_PENDING)
            ->first();
    }
}
