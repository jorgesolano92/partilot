@if(!empty($lotteryDeadlineAdminDecisionAlerts))
<div class="modal fade" id="lotteryDeadlineAdminDecisionModal" tabindex="-1" aria-labelledby="lotteryDeadlineAdminDecisionModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="lotteryDeadlineAdminDecisionModalLabel">
                    <i class="mdi mdi-gavel text-danger me-1"></i>
                    Decisión requerida — último día de devolución
                </h5>
            </div>
            <div class="modal-body pt-2">
                @foreach($lotteryDeadlineAdminDecisionAlerts as $alert)
                    <div class="alert alert-danger mb-3 lottery-deadline-admin-decision-item"
                         data-entity-id="{{ $alert['entity_id'] }}"
                         data-lottery-id="{{ $alert['lottery_id'] }}">
                        <p class="mb-2">
                            Hoy ({{ $alert['deadline_label'] }}) vence el plazo de devolución del sorteo
                            <strong>{{ $alert['lottery_name'] }}</strong> para la entidad
                            <strong>{{ $alert['entity_name'] }}</strong>.
                        </p>
                        <ul class="mb-2 small">
                            @if(($alert['pending_count'] ?? 0) > 0)
                                <li>{{ $alert['pending_count'] }} participaciones pendientes de devolución</li>
                            @endif
                            @if(($alert['seller_pending_amount'] ?? 0) > 0)
                                <li>Deuda de liquidación de vendedores: {{ number_format($alert['seller_pending_amount'], 2, ',', '.') }} €</li>
                            @endif
                        </ul>
                        <p class="mb-2 small text-muted">
                            Debes registrar una decisión para esta entidad. «Asumir deuda» mantiene las participaciones activas;
                            «Anular participaciones» registrará la anulación (procesamiento en fase posterior).
                        </p>
                        <div class="d-flex flex-wrap gap-2 justify-content-end">
                            <button type="button" class="btn btn-outline-danger btn-sm rounded-pill px-3 lottery-deadline-admin-annul-btn">
                                Anular participaciones
                            </button>
                            <button type="button" class="btn btn-dark btn-sm rounded-pill px-3 lottery-deadline-admin-assume-btn">
                                Asumir deuda
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endif
