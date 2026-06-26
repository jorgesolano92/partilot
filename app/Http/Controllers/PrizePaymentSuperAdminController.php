<?php

namespace App\Http\Controllers;

use App\Models\EntityLotteryPrizeSetting;
use App\Services\EntityLotteryPrizePaymentService;
use Illuminate\Http\Request;

class PrizePaymentSuperAdminController extends Controller
{
    public function __construct(
        private EntityLotteryPrizePaymentService $prizePaymentService
    ) {}

    public function index(Request $request)
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $settings = $this->prizePaymentService->listForSuperAdmin(
            $request->query('funds_status'),
            $request->query('mode')
        );

        return view('prize_payments.index', [
            'settings' => $settings,
            'filters' => [
                'funds_status' => $request->query('funds_status'),
                'mode' => $request->query('mode'),
            ],
        ]);
    }

    public function show(EntityLotteryPrizeSetting $prizePayment)
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $prizePayment = $this->prizePaymentService->refreshFundsFromSavedScrutiny($prizePayment);

        $prizePayment->load([
            'entity.administration',
            'lottery',
            'contractSignedBy',
            'activationLogs' => fn ($q) => $q->with('user')->orderByDesc('created_at')->limit(50),
        ]);

        return view('prize_payments.show', [
            'setting' => $prizePayment,
        ]);
    }

    public function confirmFunds(Request $request, EntityLotteryPrizeSetting $prizePayment)
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $data = $request->validate([
            'funds_deposited_amount' => 'nullable|numeric|min:0',
        ]);

        try {
            $this->prizePaymentService->confirmFunds(
                $prizePayment,
                (int) auth()->id(),
                isset($data['funds_deposited_amount']) ? (float) $data['funds_deposited_amount'] : null
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Ingreso de fondos confirmado.');
    }

    public function markContractSigned(EntityLotteryPrizeSetting $prizePayment)
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        try {
            $this->prizePaymentService->markContractSigned($prizePayment, (int) auth()->id());
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Contrato marcado como firmado.');
    }

    public function activateOnline(Request $request, EntityLotteryPrizeSetting $prizePayment)
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $data = $request->validate([
            'unlocked_user_message' => 'nullable|string|max:2000',
        ]);

        try {
            $this->prizePaymentService->activateOnlinePayments(
                $prizePayment,
                (int) auth()->id(),
                $data['unlocked_user_message'] ?? null
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Cobro online activado. Se han enviado notificaciones a los usuarios afectados.');
    }

    public function activatePresencial(EntityLotteryPrizeSetting $prizePayment)
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        try {
            $this->prizePaymentService->activatePresencialPayments($prizePayment, (int) auth()->id());
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pago presencial activado para la entidad.');
    }

    public function updateMessages(Request $request, EntityLotteryPrizeSetting $prizePayment)
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $data = $request->validate([
            'blocked_user_message' => 'nullable|string|max:2000',
            'unlocked_user_message' => 'nullable|string|max:2000',
        ]);

        $this->prizePaymentService->updateAdminMessages(
            $prizePayment,
            (int) auth()->id(),
            $data['blocked_user_message'] ?? null,
            $data['unlocked_user_message'] ?? null
        );

        return back()->with('success', 'Mensajes actualizados.');
    }

    public function sendContract(EntityLotteryPrizeSetting $prizePayment)
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        try {
            $this->prizePaymentService->sendContractInvitation($prizePayment, (int) auth()->id());
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Contrato enviado por email a la entidad.');
    }

    public function changeMode(Request $request, EntityLotteryPrizeSetting $prizePayment)
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $data = $request->validate([
            'prize_payment_mode' => 'required|in:presencial,online',
            'online_payer' => 'nullable|in:partilot,entity',
        ]);

        try {
            $this->prizePaymentService->changeModeBySuperAdmin(
                $prizePayment,
                $data['prize_payment_mode'],
                (int) auth()->id(),
                ($data['prize_payment_mode'] ?? '') === 'online'
                    ? ($data['online_payer'] ?? EntityLotteryPrizeSetting::PAYER_PARTILOT)
                    : null
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Modalidad actualizada por superadmin.');
    }

    public function blockPayments(EntityLotteryPrizeSetting $prizePayment)
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $this->prizePaymentService->blockPaymentsBySuperAdmin($prizePayment, (int) auth()->id());

        return back()->with('success', 'Cobros bloqueados para esta entidad y sorteo.');
    }
}
