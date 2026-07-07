{{-- Configuración comercial de entidad (creación / edición / ficha). --}}
@php
    $readonly = $readonly ?? true;
    $formId = $formId ?? null;
    $formAttr = $formId ? ' form="'.$formId.'"' : '';

    $isNonProfit = (bool) ($entity->is_non_profit ?? $defaults['is_non_profit'] ?? true);
    $paysManagement = (bool) ($entity->entity_pays_management_fee ?? $defaults['entity_pays_management_fee'] ?? false);
    $paysPrint = (bool) ($entity->entity_pays_print_fee ?? $defaults['entity_pays_print_fee'] ?? false);
@endphp

<div class="form-card mb-3 bs entity-commercial-settings-card">
    <h4 class="mb-0 mt-1">Configuración comercial</h4>
    <small><i>Define el tipo de entidad y quién asume los costes de gestión e impresión.</i></small>

    <div class="form-check form-switch mt-3 mb-2">
        <input type="hidden" name="is_non_profit" value="0" @if($formId) form="{{ $formId }}" @endif>
        <input class="form-check-input bg-dark entity-commercial-switch"
               type="checkbox"
               role="switch"
               id="is_non_profit"
               name="is_non_profit"
               value="1"
               data-hint-target="is_non_profit_hint"
               data-hint-on="ON — Entidad <strong>sin ánimo de lucro</strong>."
               data-hint-off="OFF — Entidad <strong>con ánimo de lucro</strong>."
               @checked($isNonProfit)
               @if($readonly) disabled @endif
               @if($formId) form="{{ $formId }}" @endif>
        <label class="form-check-label" for="is_non_profit">
            <b>Entidad sin fin lucrativo</b>
            <br>
            <small class="text-muted" id="is_non_profit_hint">
                @if($isNonProfit)
                    ON — Entidad <strong>sin ánimo de lucro</strong>.
                @else
                    OFF — Entidad <strong>con ánimo de lucro</strong>.
                @endif
            </small>
        </label>
    </div>

    <div class="form-check form-switch mt-2 mb-2">
        <input type="hidden" name="entity_pays_management_fee" value="0" @if($formId) form="{{ $formId }}" @endif>
        <input class="form-check-input bg-dark entity-commercial-switch"
               type="checkbox"
               role="switch"
               id="entity_pays_management_fee"
               name="entity_pays_management_fee"
               value="1"
               data-hint-target="entity_pays_management_fee_hint"
               data-hint-on="ON — La <strong>Entidad</strong> paga la cuota de gestión."
               data-hint-off="OFF — La <strong>Administración</strong> paga la cuota de gestión."
               @checked($paysManagement)
               @if($readonly) disabled @endif
               @if($formId) form="{{ $formId }}" @endif>
        <label class="form-check-label" for="entity_pays_management_fee">
            <b>Cuota de gestión PARTILOT</b>
            <br>
            <small class="text-muted" id="entity_pays_management_fee_hint">
                @if($paysManagement)
                    ON — La <strong>Entidad</strong> paga la cuota de gestión.
                @else
                    OFF — La <strong>Administración</strong> paga la cuota de gestión.
                @endif
            </small>
        </label>
    </div>

    <div class="form-check form-switch mt-2 mb-2">
        <input type="hidden" name="entity_pays_print_fee" value="0" @if($formId) form="{{ $formId }}" @endif>
        <input class="form-check-input bg-dark entity-commercial-switch"
               type="checkbox"
               role="switch"
               id="entity_pays_print_fee"
               name="entity_pays_print_fee"
               value="1"
               data-hint-target="entity_pays_print_fee_hint"
               data-hint-on="ON — La <strong>Entidad</strong> diseña (y paga impresión en imprenta PARTILOT si aplica)."
               data-hint-off="OFF — La <strong>Administración</strong> diseña; la entidad solo aprueba el diseño."
               @checked($paysPrint)
               @if($readonly) disabled @endif
               @if($formId) form="{{ $formId }}" @endif>
        <label class="form-check-label" for="entity_pays_print_fee">
            <b>Quién diseña e imprime</b>
            <br>
            <small class="text-muted" id="entity_pays_print_fee_hint">
                @if($paysPrint)
                    ON — La <strong>Entidad</strong> diseña (y paga impresión en imprenta PARTILOT si aplica).
                @else
                    OFF — La <strong>Administración</strong> diseña; la entidad solo aprueba el diseño.
                @endif
            </small>
        </label>
    </div>
</div>
