{{-- Switches comerciales (Switch 1 y 2). Solo visible para Administración y Super Admin. --}}
@php
    $readonly = $readonly ?? true;
    $paysManagement = (bool) ($entity->entity_pays_management_fee ?? false);
    $paysPrint = (bool) ($entity->entity_pays_print_fee ?? false);
@endphp

<div class="form-card mb-3 bs">
    <h4 class="mb-0 mt-1">Configuración comercial</h4>
    <small><i>Define quién asume los costes de gestión e impresión de esta entidad.</i></small>

    <div class="form-check form-switch mt-3 mb-2">
        <input type="hidden" name="entity_pays_management_fee" value="0">
        <input class="form-check-input bg-dark"
               type="checkbox"
               role="switch"
               id="entity_pays_management_fee"
               name="entity_pays_management_fee"
               value="1"
               @checked($paysManagement)
               @if($readonly) disabled @endif>
        <label class="form-check-label" for="entity_pays_management_fee">
            <b>Cuota de gestión PARTILOT</b>
            <br>
            <small class="text-muted">
                @if($paysManagement)
                    ON — La <strong>Entidad</strong> paga la cuota de gestión.
                @else
                    OFF — La <strong>Administración</strong> paga la cuota de gestión.
                @endif
            </small>
        </label>
    </div>

    <div class="form-check form-switch mt-2 mb-2">
        <input type="hidden" name="entity_pays_print_fee" value="0">
        <input class="form-check-input bg-dark"
               type="checkbox"
               role="switch"
               id="entity_pays_print_fee"
               name="entity_pays_print_fee"
               value="1"
               @checked($paysPrint)
               @if($readonly) disabled @endif>
        <label class="form-check-label" for="entity_pays_print_fee">
            <b>Quién diseña e imprime</b>
            <br>
            <small class="text-muted">
                @if($paysPrint)
                    ON — La <strong>Entidad</strong> diseña (y paga impresión en imprenta PARTILOT si aplica).
                @else
                    OFF — La <strong>Administración</strong> diseña; la entidad solo aprueba el diseño.
                @endif
            </small>
        </label>
    </div>
</div>
