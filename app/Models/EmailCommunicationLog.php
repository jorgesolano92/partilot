<?php

namespace App\Models;

use App\Support\CommunicationEmailTypeLabel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class EmailCommunicationLog extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_RE_SENT = 'resent';

    protected $table = 'email_communication_logs';

    protected $fillable = [
        'template_key',
        'message_type',
        'sender_type',
        'sender_user_id',
        'recipient_email',
        'recipient_role',
        'recipient_user_id',
        'mail_class',
        'mail_payload',
        'encrypted_secrets',
        'status',
        'sent_at',
        'resent_at',
        'cancelled_at',
        'last_attempt_at',
        'error_message',
        'context',
    ];

    protected $casts = [
        'mail_payload' => 'array',
        'context' => 'array',
        'sent_at' => 'datetime',
        'resent_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'last_attempt_at' => 'datetime',
    ];

    public function displayStatus(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pendiente',
            self::STATUS_SENT => 'Enviado',
            self::STATUS_CANCELLED => 'Cancelado',
            self::STATUS_RE_SENT => 'Reenviado',
            default => $this->status,
        };
    }

    public function displayStatusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'bg-warning text-dark',
            self::STATUS_SENT => 'bg-success',
            self::STATUS_RE_SENT => 'bg-primary',
            self::STATUS_CANCELLED => 'bg-danger',
            default => 'bg-secondary',
        };
    }

    public function displayEffectiveDate(): ?\Carbon\Carbon
    {
        if ($this->status === self::STATUS_SENT) return $this->sent_at;
        if ($this->status === self::STATUS_RE_SENT) return $this->resent_at;
        if ($this->status === self::STATUS_CANCELLED) return $this->cancelled_at;

        return $this->last_attempt_at ?? $this->created_at;
    }

    public function displayMessageType(): string
    {
        return CommunicationEmailTypeLabel::label($this->message_type, $this->template_key);
    }

    public function messageTypeKey(): ?string
    {
        $key = trim((string) ($this->message_type ?: $this->template_key));

        return $key !== '' ? $key : null;
    }

    /**
     * @return array<string, string>|null
     */
    public function decryptedSecrets(): ?array
    {
        if (empty($this->encrypted_secrets)) {
            return null;
        }

        try {
            $decoded = json_decode(Crypt::decryptString($this->encrypted_secrets), true);

            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable) {
            return null;
        }
    }
}

