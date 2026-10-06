<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartilotBillingSetting extends Model
{
    protected $fillable = [
        'company_name',
        'nif_cif',
        'address',
        'postal_code',
        'province',
        'city',
        'phone',
        'email',
        'fee_per_participation_1000',
        'fee_per_participation_5000',
        'fee_per_participation_10000',
        'fee_administration_per_participation',
        'payment_management_commission',
        'bank_account',
        'sepa_creditor_id',
        'stripe_publishable_key',
        'stripe_secret_key',
        'stripe_webhook_secret',
    ];

    protected $hidden = [
        'stripe_secret_key',
        'stripe_webhook_secret',
    ];

    protected $casts = [
        'fee_per_participation_1000' => 'decimal:4',
        'fee_per_participation_5000' => 'decimal:4',
        'fee_per_participation_10000' => 'decimal:4',
        'fee_administration_per_participation' => 'decimal:4',
        'payment_management_commission' => 'decimal:4',
    ];

    public static function current(): self
    {
        $settings = static::query()->orderBy('id')->first();

        if ($settings) {
            return $settings;
        }

        return static::query()->create([]);
    }

    public function hasStripeConfigured(): bool
    {
        return $this->stripePublishableKey() !== '' && $this->stripeSecretKey() !== '';
    }

    public function stripePublishableKey(): string
    {
        return trim((string) ($this->stripe_publishable_key ?? ''));
    }

    public function stripeSecretKey(): string
    {
        return trim((string) ($this->stripe_secret_key ?? ''));
    }

    public function stripeWebhookSecret(): string
    {
        return trim((string) ($this->stripe_webhook_secret ?? ''));
    }

    public function creditorIban(): string
    {
        $raw = preg_replace('/\s+/', '', (string) ($this->bank_account ?? ''));
        if ($raw === '') {
            return '';
        }
        if (str_starts_with(strtoupper($raw), 'ES')) {
            return strtoupper($raw);
        }
        $digits = preg_replace('/\D/', '', $raw);

        return strlen($digits) === 22 ? 'ES'.$digits : '';
    }

    public function creditorSchemeId(): string
    {
        $configured = trim((string) ($this->sepa_creditor_id ?? ''));
        if ($configured !== '') {
            return $configured;
        }

        $nif = strtoupper(preg_replace('/\s+/', '', (string) ($this->nif_cif ?? '')));
        if ($nif === '') {
            return '';
        }

        return 'ES'.$nif.'001';
    }
}
