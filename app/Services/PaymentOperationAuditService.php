<?php

namespace App\Services;

use App\Models\PaymentOperationAuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class PaymentOperationAuditService
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function log(
        string $operationType,
        ?int $userId,
        ?float $amount = null,
        ?int $entityId = null,
        ?int $administrationId = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        array $context = [],
        ?Request $request = null
    ): void {
        if (! Schema::hasTable('payment_operation_audit_logs')) {
            return;
        }

        PaymentOperationAuditLog::create([
            'user_id' => $userId,
            'operation_type' => $operationType,
            'amount' => $amount,
            'entity_id' => $entityId,
            'administration_id' => $administrationId,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? substr((string) $request->userAgent(), 0, 500) : null,
            'context' => $context !== [] ? $context : null,
        ]);
    }
}
