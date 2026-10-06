@if(!empty($entityManagementFeeModalAlert))
<div class="modal fade" id="entityManagementFeeModal" tabindex="-1" aria-labelledby="entityManagementFeeModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="entityManagementFeeModalLabel">
                    <i class="ri-error-warning-line text-danger me-1"></i>
                    Cuota de gestión PARTILOT pendiente
                </h5>
            </div>
            <div class="modal-body pt-2">
                <p class="mb-3">
                    Debe abonar la <strong>cuota de gestión PARTILOT</strong> antes de continuar.
                    Hasta entonces, los pedidos enviados a imprenta permanecerán en espera.
                </p>
                <div class="border rounded p-3 bg-light small mb-0">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Entidad</span>
                        <strong>{{ $entityManagementFeeModalAlert['entity_name'] ?? '—' }}</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Set</span>
                        <strong>{{ $entityManagementFeeModalAlert['set_name'] ?? '—' }}</strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Importe pendiente</span>
                        <strong class="text-danger">{{ number_format((float) ($entityManagementFeeModalAlert['amount'] ?? 0), 2, ',', '.') }}€</strong>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <a href="{{ $entityManagementFeeModalAlert['pay_url'] }}" class="btn btn-warning text-dark fw-semibold rounded-pill px-4">
                    <i class="ri-bank-card-line me-1"></i> Pagar cuota de gestión
                </a>
            </div>
        </div>
    </div>
</div>
@endif
