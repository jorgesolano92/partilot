<?php

namespace App\Http\Controllers;

use App\Models\Entity;
use App\Models\Lottery;
use App\Services\EntityLotteryPrizePaymentService;
use Illuminate\Http\Request;

class EntityLotteryPrizeSettingsController extends Controller
{
    public function __construct(
        private EntityLotteryPrizePaymentService $prizePaymentService
    ) {}

    public function apiShow(Request $request, Entity $entity, Lottery $lottery)
    {
        if (! $request->user()?->canAccessEntity((int) $entity->id)) {
            return response()->json(['success' => false, 'message' => 'No autorizado.'], 403);
        }

        $setting = $this->prizePaymentService->getSettings((int) $entity->id, (int) $lottery->id);

        return response()->json([
            'success' => true,
            'setting' => $setting ? [
                'prize_payment_mode' => $setting->prize_payment_mode,
                'funds_status' => $setting->funds_status,
                'contract_status' => $setting->contract_status,
                'online_payments_enabled' => $setting->online_payments_enabled,
                'presencial_payments_enabled' => $setting->presencial_payments_enabled,
                'has_sold_digital_participations' => $setting->has_sold_digital_participations,
                'presencial_contact' => $this->prizePaymentService->presencialContactPayload($setting),
            ] : null,
        ]);
    }

    public function apiUpdatePresencialContact(Request $request, Entity $entity, Lottery $lottery)
    {
        if (! $request->user()?->canAccessEntity((int) $entity->id)) {
            return response()->json(['success' => false, 'message' => 'No autorizado.'], 403);
        }

        $setting = $this->prizePaymentService->getSettings((int) $entity->id, (int) $lottery->id);
        if (! $setting) {
            return response()->json(['success' => false, 'message' => 'No hay configuración de premios para este sorteo.'], 422);
        }

        $data = $request->validate([
            'presencial_contact_text' => 'nullable|string|max:5000',
            'presencial_contact_address' => 'nullable|string|max:255',
            'presencial_contact_city' => 'nullable|string|max:120',
            'presencial_contact_province' => 'nullable|string|max:120',
            'presencial_contact_schedule' => 'nullable|string|max:255',
            'presencial_contact_phone' => 'nullable|string|max:50',
            'presencial_contact_email' => 'nullable|email|max:255',
            'presencial_contact_notes' => 'nullable|string|max:2000',
        ]);

        try {
            $updated = $this->prizePaymentService->updatePresencialContact(
                $setting,
                $data,
                (int) $request->user()->id
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Contacto presencial actualizado.',
            'presencial_contact' => $this->prizePaymentService->presencialContactPayload($updated),
        ]);
    }

    public function updatePresencialContactWeb(Request $request, Lottery $lottery)
    {
        $user = $request->user();
        $entity = app(\App\Services\EntityLotteryPrizeService::class)->resolveViewEntity($user);
        if (! $entity || ! $user->canAccessEntity((int) $entity->id)) {
            abort(403);
        }

        $setting = $this->prizePaymentService->getSettings((int) $entity->id, (int) $lottery->id);
        if (! $setting || ! $setting->isModePresencial()) {
            return back()->with('error', 'No puedes editar el contacto presencial para este sorteo.');
        }

        $data = $request->validate([
            'presencial_contact_text' => 'nullable|string|max:5000',
            'presencial_contact_address' => 'nullable|string|max:255',
            'presencial_contact_city' => 'nullable|string|max:120',
            'presencial_contact_province' => 'nullable|string|max:120',
            'presencial_contact_schedule' => 'nullable|string|max:255',
            'presencial_contact_phone' => 'nullable|string|max:50',
            'presencial_contact_email' => 'nullable|email|max:255',
            'presencial_contact_notes' => 'nullable|string|max:2000',
        ]);

        $this->prizePaymentService->updatePresencialContact($setting, $data, (int) $user->id);

        return back()->with('success', 'Contacto presencial actualizado.');
    }
}
