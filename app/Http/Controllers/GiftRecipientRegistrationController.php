<?php

namespace App\Http\Controllers;

use App\Mail\UserWelcomeMail;
use App\Models\ParticipationGift;
use App\Models\User;
use App\Rules\ValidCalendarDate;
use App\Services\CommunicationEmailService;
use App\Services\ParticipationGiftService;
use App\Services\PhoneVerificationService;
use App\Services\UserConsentService;
use App\Support\PasswordRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class GiftRecipientRegistrationController extends Controller
{
    public function show(string $token)
    {
        $gift = $this->findPendingGiftByToken($token);
        if (! $gift) {
            return view('auth.gift-register-expired');
        }

        if (User::whereRaw('LOWER(email) = ?', [strtolower((string) $gift->to_email)])->exists()) {
            return view('auth.gift-register-exists', compact('gift', 'token'));
        }

        $gift->loadMissing(['fromUser', 'participation.set.entity', 'participation.set.reserve.lottery']);

        return view('auth.gift-register', compact('gift', 'token'));
    }

    public function store(Request $request, string $token, ParticipationGiftService $giftService, PhoneVerificationService $phoneVerification)
    {
        $gift = $this->findPendingGiftByToken($token);
        if (! $gift) {
            return back()->withErrors(['token' => 'El enlace ha caducado o ya no es válido.']);
        }

        $email = strtolower((string) $gift->to_email);
        if (User::whereRaw('LOWER(email) = ?', [$email])->exists()) {
            return redirect()
                ->route('gift-recipient.register', ['token' => $token])
                ->with('info', 'Ya existe una cuenta con este correo. Inicia sesión en la app Partilot.');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'last_name2' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'birthday' => ValidCalendarDate::birthday(),
            'password' => PasswordRules::registration(),
            'aceptar_condiciones' => 'required|accepted',
            'sms_code' => [
                Rule::requiredIf(fn () => $phoneVerification->smsVerificationRequired($request->input('phone'))),
                'nullable',
                'string',
                'size:'.config('sms.code_length', 6),
            ],
        ], [
            'sms_code.required' => 'Si indicas teléfono, debes verificarlo con el código SMS.',
            'aceptar_condiciones.accepted' => 'Debes aceptar las condiciones de uso.',
        ]);

        try {
            $phone = $phoneVerification->resolveOptionalPhone($request->phone);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['phone' => $e->getMessage()]);
        }

        if ($phoneVerification->smsVerificationRequired($request->phone)) {
            if (! $phoneVerification->verifyCode($phone, (string) $request->sms_code)) {
                return back()->withInput()->withErrors(['sms_code' => 'Código SMS incorrecto o caducado.']);
            }
        }

        $user = User::create([
            'name' => $request->name,
            'last_name' => $request->last_name,
            'last_name2' => $request->last_name2,
            'email' => $email,
            'phone' => $phone,
            'birthday' => $request->birthday,
            'password' => Hash::make($request->password),
            'role' => User::ROLE_CLIENT,
            'status' => 1,
        ]);

        app(UserConsentService::class)->record(
            $user,
            \App\Models\UserConsent::TYPE_REGISTRATION_TERMS,
            $request,
            ['source' => 'gift_registration', 'gift_id' => $gift->id]
        );

        $gift->to_user_id = $user->id;
        $gift->save();

        $giftService->attachPendingGiftsToUser($user);
        $giftService->notifyGiftReceived($gift->fresh(['fromUser', 'participation.set.entity']));

        try {
            app(CommunicationEmailService::class)->sendAndLog(
                recipientEmail: $user->email,
                recipientRole: 'usuario',
                recipientUser: $user,
                messageType: 'user_welcome',
                templateKey: null,
                mailClass: UserWelcomeMail::class,
                mailPayload: ['user_id' => $user->id],
                context: ['source' => 'gift_registration'],
            );
        } catch (\Throwable $e) {
            // no bloquear registro
        }

        return view('auth.gift-register-success', compact('gift', 'user'));
    }

    protected function findPendingGiftByToken(string $token): ?ParticipationGift
    {
        return ParticipationGift::query()
            ->where('claim_token', $token)
            ->where('status', ParticipationGift::STATUS_PENDING)
            ->whereNull('to_user_id')
            ->first();
    }
}
