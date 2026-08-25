@php
    $onboardingMode = !empty($onboardingMode);
@endphp
<div class="modal fade" id="entityBillingSwitchesModal" tabindex="-1" aria-labelledby="entityBillingSwitchesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="entityBillingSwitchesModalLabel">Confirmar configuración comercial</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">
                    @if($onboardingMode)
                        Continuarás el alta con la siguiente configuración comercial:
                    @else
                        Se guardará la entidad con la siguiente configuración de pagos:
                    @endif
                </p>
                <ul class="list-unstyled mb-3">
                    <li class="mb-2">
                        <strong>Puede emitir certificado de donación:</strong>
                        <span id="billing-modal-donation-cert">—</span>
                    </li>
                    <li class="mb-2">
                        <strong>Cuota de gestión PARTILOT:</strong>
                        <span id="billing-modal-management-payer">—</span>
                    </li>
                    <li>
                        <strong>Diseño e impresión:</strong>
                        <span id="billing-modal-print-payer">—</span>
                    </li>
                </ul>
                @unless($onboardingMode)
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="billing-modal-hide-again">
                    <label class="form-check-label" for="billing-modal-hide-again">
                        No volver a mostrar este mensaje
                    </label>
                </div>
                @endunless
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning text-dark" id="billing-modal-confirm-btn">
                    {{ $onboardingMode ? 'Confirmar y continuar' : 'Confirmar y guardar' }}
                </button>
            </div>
        </div>
    </div>
</div>
