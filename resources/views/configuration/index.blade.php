@extends('layouts.layout')

@section('title', 'Ajustes')

@section('content')

<style>
    #configuration-content .alert {
        display: block !important;
    }
</style>

<div class="container-fluid">
    @php
        $configIcons = [
            'datos-partilot' => 'config/1.png',
            'config-factura-auto' => 'config/2.png',
            'facturacion-cobros' => 'config/3.png',
            'logs-actividad' => 'config/4.png',
            'logs-emails' => 'config/5.png',
            'logs-notificaciones' => 'config/6.png',
            'imprenta' => 'config/7.png',
            'ordenes-imprenta' => 'config/8.png',
            'ordenes-pago-entidades' => 'config/9.png',
            'codigos-recarga' => 'config/10.png',
            'notificaciones' => 'config/11.png',
        ];
    @endphp
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{url('/dashboard')}}">Panel</a></li>
                        <li class="breadcrumb-item active">Ajustes</li>
                    </ol>
                </div>
                <h4 class="page-title">Ajustes</h4>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Menú lateral izquierdo (mismo diseño que entities/add) -->
        <div class="col-md-3 col-lg-3 configuration-menu-wrapper">
            <div class="form-card bs mb-3" style="background-color: #fff;">
                @php($onlyPaymentsSection = auth()->user()?->isEntityManagerWithoutPanelAccount())
                @php($isEntityPanelSettings = auth()->user()?->isEntityPanelAccount())
                @php($isAdministrationPanelSettings = auth()->user()?->isAdministrationPanelAccount())
                @if($onlyPaymentsSection)
                    @if($section == 'ordenes-pago-entidades')
                        <div class="form-wizard-element active">
                            <img src="{{ url($configIcons['ordenes-pago-entidades']) }}" alt="">
                            <label>Ordenes Pago Entidades</label>
                        </div>
                    @else
                        <a href="{{ url('/configuration?section=ordenes-pago-entidades') }}" class="form-wizard-element text-decoration-none" style="color: inherit;">
                            <img src="{{ url($configIcons['ordenes-pago-entidades']) }}" alt="">
                            <label>Ordenes Pago Entidades</label>
                        </a>
                    @endif
                    @if($section == 'codigos-recarga')
                        <div class="form-wizard-element active">
                            <img src="{{ url($configIcons['codigos-recarga']) }}" alt="">
                            <label>Códigos Recarga</label>
                        </div>
                    @else
                        <a href="{{ url('/configuration?section=codigos-recarga') }}" class="form-wizard-element text-decoration-none" style="color: inherit;">
                            <img src="{{ url($configIcons['codigos-recarga']) }}" alt="">
                            <label>Códigos Recarga</label>
                        </a>
                    @endif
                @elseif($isEntityPanelSettings)
                    @if($section == 'datos-entidad')
                        <div class="form-wizard-element active">
                            <img src="{{ url($configIcons['datos-partilot']) }}" alt="">
                            <label>Mis datos</label>
                        </div>
                    @else
                        <a href="{{ url('/configuration?section=datos-entidad') }}" class="form-wizard-element text-decoration-none" style="color: inherit;">
                            <img src="{{ url($configIcons['datos-partilot']) }}" alt="">
                            <label>Mis datos</label>
                        </a>
                    @endif

                    <div class="configuration-menu-sep"></div>

                    @if($section == 'facturacion-cobros')
                        <div class="form-wizard-element active">
                            <img src="{{ url($configIcons['facturacion-cobros']) }}" alt="">
                            <label>Facturación y Cobros</label>
                        </div>
                    @else
                        <a href="{{ url('/configuration?section=facturacion-cobros') }}" class="form-wizard-element text-decoration-none" style="color: inherit;">
                            <img src="{{ url($configIcons['facturacion-cobros']) }}" alt="">
                            <label>Facturación y Cobros</label>
                        </a>
                    @endif

                    <div class="configuration-menu-sep"></div>

                    @if($section == 'ordenes-pago-entidades')
                        <div class="form-wizard-element active">
                            <img src="{{ url($configIcons['ordenes-pago-entidades']) }}" alt="">
                            <label>Ordenes Pago</label>
                        </div>
                    @else
                        <a href="{{ url('/configuration?section=ordenes-pago-entidades') }}" class="form-wizard-element text-decoration-none" style="color: inherit;">
                            <img src="{{ url($configIcons['ordenes-pago-entidades']) }}" alt="">
                            <label>Ordenes Pago</label>
                        </a>
                    @endif

                    @if($section == 'codigos-recarga')
                        <div class="form-wizard-element active">
                            <img src="{{ url($configIcons['codigos-recarga']) }}" alt="">
                            <label>Códigos Recarga</label>
                        </div>
                    @else
                        <a href="{{ url('/configuration?section=codigos-recarga') }}" class="form-wizard-element text-decoration-none" style="color: inherit;">
                            <img src="{{ url($configIcons['codigos-recarga']) }}" alt="">
                            <label>Códigos Recarga</label>
                        </a>
                    @endif

                    <div class="configuration-menu-sep"></div>

                    @if($section == 'logs-emails')
                        <div class="form-wizard-element active">
                            <img src="{{ url($configIcons['logs-emails']) }}" alt="">
                            <label>Logs Emails</label>
                        </div>
                    @else
                        <a href="{{ url('/configuration?section=logs-emails') }}" class="form-wizard-element text-decoration-none" style="color: inherit;">
                            <img src="{{ url($configIcons['logs-emails']) }}" alt="">
                            <label>Logs Emails</label>
                        </a>
                    @endif

                    @if($section == 'logs-notificaciones')
                        <div class="form-wizard-element active">
                            <img src="{{ url($configIcons['logs-notificaciones']) }}" alt="">
                            <label>Logs Notificaciones</label>
                        </div>
                    @else
                        <a href="{{ url('/configuration?section=logs-notificaciones') }}" class="form-wizard-element text-decoration-none" style="color: inherit;">
                            <img src="{{ url($configIcons['logs-notificaciones']) }}" alt="">
                            <label>Logs Notificaciones</label>
                        </a>
                    @endif
                @elseif($isAdministrationPanelSettings)
                    @if($section == 'datos-administracion')
                        <div class="form-wizard-element active">
                            <img src="{{ url($configIcons['datos-partilot']) }}" alt="">
                            <label>Mis datos</label>
                        </div>
                    @else
                        <a href="{{ url('/configuration?section=datos-administracion') }}" class="form-wizard-element text-decoration-none" style="color: inherit;">
                            <img src="{{ url($configIcons['datos-partilot']) }}" alt="">
                            <label>Mis datos</label>
                        </a>
                    @endif

                    <div class="configuration-menu-sep"></div>

                    @if($section == 'facturacion-cobros')
                        <div class="form-wizard-element active">
                            <img src="{{ url($configIcons['facturacion-cobros']) }}" alt="">
                            <label>Facturación y Cobros</label>
                        </div>
                    @else
                        <a href="{{ url('/configuration?section=facturacion-cobros') }}" class="form-wizard-element text-decoration-none" style="color: inherit;">
                            <img src="{{ url($configIcons['facturacion-cobros']) }}" alt="">
                            <label>Facturación y Cobros</label>
                        </a>
                    @endif

                    <div class="configuration-menu-sep"></div>

                    @if($section == 'ordenes-pago-entidades')
                        <div class="form-wizard-element active">
                            <img src="{{ url($configIcons['ordenes-pago-entidades']) }}" alt="">
                            <label>Ordenes Pago</label>
                        </div>
                    @else
                        <a href="{{ url('/configuration?section=ordenes-pago-entidades') }}" class="form-wizard-element text-decoration-none" style="color: inherit;">
                            <img src="{{ url($configIcons['ordenes-pago-entidades']) }}" alt="">
                            <label>Ordenes Pago</label>
                        </a>
                    @endif

                    <div class="configuration-menu-sep"></div>

                    @if($section == 'logs-emails')
                        <div class="form-wizard-element active">
                            <img src="{{ url($configIcons['logs-emails']) }}" alt="">
                            <label>Logs Emails</label>
                        </div>
                    @else
                        <a href="{{ url('/configuration?section=logs-emails') }}" class="form-wizard-element text-decoration-none" style="color: inherit;">
                            <img src="{{ url($configIcons['logs-emails']) }}" alt="">
                            <label>Logs Emails</label>
                        </a>
                    @endif

                    @if($section == 'logs-notificaciones')
                        <div class="form-wizard-element active">
                            <img src="{{ url($configIcons['logs-notificaciones']) }}" alt="">
                            <label>Logs Notificaciones</label>
                        </div>
                    @else
                        <a href="{{ url('/configuration?section=logs-notificaciones') }}" class="form-wizard-element text-decoration-none" style="color: inherit;">
                            <img src="{{ url($configIcons['logs-notificaciones']) }}" alt="">
                            <label>Logs Notificaciones</label>
                        </a>
                    @endif
                @else
                @if($section == 'datos-partilot')
                    <div class="form-wizard-element active">
                        <img src="{{ url($configIcons['datos-partilot']) }}" alt="">
                        <label>Datos PARTILOT</label>
                    </div>
                @else
                    <a href="{{ url('/configuration?section=datos-partilot') }}" class="form-wizard-element text-decoration-none" style="color: inherit;">
                        <img src="{{ url($configIcons['datos-partilot']) }}" alt="">
                        <label>Datos PARTILOT</label>
                    </a>
                @endif

                <div class="configuration-menu-sep"></div>

                @if($section == 'config-factura-auto')
                    <div class="form-wizard-element active">
                        <img src="{{ url($configIcons['config-factura-auto']) }}" alt="">
                        <label>Config. Factura (Auto)</label>
                    </div>
                @else
                    <a href="{{ url('/configuration?section=config-factura-auto') }}" class="form-wizard-element text-decoration-none" style="color: inherit;">
                        <img src="{{ url($configIcons['config-factura-auto']) }}" alt="">
                        <label>Config. Factura (Auto)</label>
                    </a>
                @endif

                @if($section == 'facturacion-cobros')
                    <div class="form-wizard-element active">
                        <img src="{{ url($configIcons['facturacion-cobros']) }}" alt="">
                        <label>Facturación y Cobros</label>
                    </div>
                @else
                    <a href="{{ url('/configuration?section=facturacion-cobros') }}" class="form-wizard-element text-decoration-none" style="color: inherit;">
                        <img src="{{ url($configIcons['facturacion-cobros']) }}" alt="">
                        <label>Facturación y Cobros</label>
                    </a>
                @endif

                <div class="configuration-menu-sep"></div>

                @if($section == 'logs-actividad')
                    <div class="form-wizard-element active">
                        <img src="{{ url($configIcons['logs-actividad']) }}" alt="">
                        <label>Logs de Actividad</label>
                    </div>
                @else
                    <a href="{{ url('/configuration?section=logs-actividad') }}" class="form-wizard-element text-decoration-none" style="color: inherit;">
                        <img src="{{ url($configIcons['logs-actividad']) }}" alt="">
                        <label>Logs de Actividad</label>
                    </a>
                @endif

                @if($section == 'logs-emails')
                    <div class="form-wizard-element active">
                        <img src="{{ url($configIcons['logs-emails']) }}" alt="">
                        <label>Logs Emails</label>
                    </div>
                @else
                    <a href="{{ url('/configuration?section=logs-emails') }}" class="form-wizard-element text-decoration-none" style="color: inherit;">
                        <img src="{{ url($configIcons['logs-emails']) }}" alt="">
                        <label>Logs Emails</label>
                    </a>
                @endif

                @if($section == 'logs-notificaciones')
                    <div class="form-wizard-element active">
                        <img src="{{ url($configIcons['logs-notificaciones']) }}" alt="">
                        <label>Logs Notificaciones</label>
                    </div>
                @else
                    <a href="{{ url('/configuration?section=logs-notificaciones') }}" class="form-wizard-element text-decoration-none" style="color: inherit;">
                        <img src="{{ url($configIcons['logs-notificaciones']) }}" alt="">
                        <label>Logs Notificaciones</label>
                    </a>
                @endif

                <div class="configuration-menu-sep"></div>

                @if($section == 'imprenta')
                    <div class="form-wizard-element active">
                        <img src="{{ url($configIcons['imprenta']) }}" alt="">
                        <label>Imprentas</label>
                    </div>
                @else
                    <a href="{{ url('/configuration?section=imprenta') }}" class="form-wizard-element text-decoration-none" style="color: inherit;">
                        <img src="{{ url($configIcons['imprenta']) }}" alt="">
                        <label>Imprentas</label>
                    </a>
                @endif

                @if($section == 'ordenes-imprenta')
                    <div class="form-wizard-element active">
                        <img src="{{ url($configIcons['ordenes-imprenta']) }}" alt="">
                        <label>Ordenes Imprenta</label>
                    </div>
                @else
                    <a href="{{ url('/configuration?section=ordenes-imprenta') }}" class="form-wizard-element text-decoration-none" style="color: inherit;">
                        <img src="{{ url($configIcons['ordenes-imprenta']) }}" alt="">
                        <label>Ordenes Imprenta</label>
                    </a>
                @endif

                <div class="configuration-menu-sep"></div>

                @if($section == 'ordenes-pago-entidades')
                    <div class="form-wizard-element active">
                        <img src="{{ url($configIcons['ordenes-pago-entidades']) }}" alt="">
                        <label>Ordenes Pago Entidades</label>
                    </div>
                @else
                    <a href="{{ url('/configuration?section=ordenes-pago-entidades') }}" class="form-wizard-element text-decoration-none" style="color: inherit;">
                        <img src="{{ url($configIcons['ordenes-pago-entidades']) }}" alt="">
                        <label>Ordenes Pago Entidades</label>
                    </a>
                @endif

                @if($section == 'codigos-recarga')
                    <div class="form-wizard-element active">
                        <img src="{{ url($configIcons['codigos-recarga']) }}" alt="">
                        <label>Códigos Recarga</label>
                    </div>
                @else
                    <a href="{{ url('/configuration?section=codigos-recarga') }}" class="form-wizard-element text-decoration-none" style="color: inherit;">
                        <img src="{{ url($configIcons['codigos-recarga']) }}" alt="">
                        <label>Códigos Recarga</label>
                    </a>
                @endif

                <div class="configuration-menu-sep"></div>

                @if($section == 'notificaciones')
                    <div class="form-wizard-element active">
                        <img src="{{ url($configIcons['notificaciones']) }}" alt="">
                        <label>Notificaciones</label>
                    </div>
                @else
                    <a href="{{ url('/configuration?section=notificaciones') }}" class="form-wizard-element text-decoration-none" style="color: inherit;">
                        <img src="{{ url($configIcons['notificaciones']) }}" alt="">
                        <label>Notificaciones</label>
                    </a>
                @endif
                @endif
            </div>
        </div>

        <!-- Contenido central -->
        <div class="col-md-9 col-lg-9" id="configuration-content">
                    @if($section == 'datos-partilot')
                        @include('configuration.sections.datos-partilot')
                    @elseif($section == 'datos-entidad')
                        @include('configuration.sections.datos-entidad')
                    @elseif($section == 'datos-administracion')
                        @include('configuration.sections.datos-administracion')
                    @elseif($section == 'config-factura-auto')
                        @include('configuration.sections.config-factura-auto')
                    @elseif($section == 'facturacion-cobros')
                        @include('configuration.sections.facturacion-cobros')
                    @elseif($section == 'logs-actividad')
                        @include('configuration.sections.logs-actividad')
                    @elseif($section == 'logs-emails')
                        @include('configuration.sections.logs-emails')
                    @elseif($section == 'logs-notificaciones')
                        @include('configuration.sections.logs-notificaciones')
                    @elseif($section == 'imprenta')
                        @include('configuration.sections.imprenta')
                    @elseif($section == 'ordenes-imprenta')
                        @include('configuration.sections.ordenes-imprenta')
                    @elseif($section == 'ordenes-pago-entidades')
                        @include('configuration.sections.ordenes-pago-entidades')
                    @elseif($section == 'codigos-recarga')
                        @include('configuration.sections.codigos-recarga')
                    @elseif($section == 'notificaciones')
                        @include('configuration.sections.notificaciones')
                    @else
                        @include('configuration.sections.datos-partilot')
                    @endif
        </div>
    </div>
</div>

<style>
/* Separador entre grupos del menú de configuración */
.configuration-menu-sep {
    height: 1px;
    background-color: #dee2e6;
    margin: 12px 0;
}

/* Ajuste del menú de configuración: selección más oscura y redondeada como en la referencia */
.configuration-menu-wrapper .form-wizard-element {
    padding: 12px 16px !important;
    margin-bottom: 10px !important;
}

.configuration-menu-wrapper .form-wizard-element.active {
    background-color: #495057 !important;
    color: #fff !important;
    filter: none;
    padding: 14px 16px !important;
}

.configuration-menu-wrapper .form-wizard-element.active img {
    filter: brightness(0) invert(1);
}

/* Ocultar el span (número) en el menú de configuración */
.configuration-menu-wrapper .form-wizard-element span {
    display: none;
}

/* Ajustar tamaño del label para compensar la falta del número */
.configuration-menu-wrapper .form-wizard-element label {
    font-size: 14px;
    font-weight: 600;
}
</style>
@endsection

@section('scripts')
@if($section == 'ordenes-pago-entidades' && $step === 1)
<script>
(function() {
    var baseUrl = '{{ url("/configuration") }}';
    document.addEventListener('DOMContentLoaded', function() {
        var table = document.getElementById('tabla-ope-entidades');
        var btnSiguiente = document.getElementById('btn-siguiente-ope');
        var inputSelected = document.getElementById('selected-entity-id-ope');
        if (!table || !btnSiguiente || !inputSelected) return;

        function clearSelection() {
            var rows = table.querySelectorAll('tbody tr.selectable-row');
            rows.forEach(function(r) { r.style.backgroundColor = ''; });
        }
        document.addEventListener('click', function(e) {
            var row = e.target.closest('tr.selectable-row');
            if (!row || !table.contains(row)) return;
            if (row.classList.contains('entity-inactive')) return;
            var id = row.getAttribute('data-entity-id');
            if (!id) return;
            e.preventDefault();
            clearSelection();
            row.style.backgroundColor = '#e3f2fd';
            inputSelected.value = id;
            btnSiguiente.disabled = false;
        });
        btnSiguiente.addEventListener('click', function() {
            var id = inputSelected.value;
            if (id) window.location.href = baseUrl + '?section=ordenes-pago-entidades&step=2&entity_id=' + id;
        });
        if (typeof jQuery !== 'undefined' && jQuery.fn.DataTable && table.querySelector('tbody tr.selectable-row')) {
            jQuery(table).DataTable({
                ordering: true,
                searching: true,
                paging: true,
                pageLength: 30,
                lengthMenu: [[10, 25, 30, 50, -1], [10, 25, 30, 50, 'Todos']],
                language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' },
                columnDefs: [{ orderable: false, targets: -1 }]
            });
        }
    });
})();
</script>
@endif
@if($section == 'codigos-recarga' && $step === 1)
<script>
(function() {
    var baseUrl = '{{ url("/configuration") }}';
    document.addEventListener('DOMContentLoaded', function() {
        var table = document.getElementById('tabla-cr-entidades');
        var btnSiguiente = document.getElementById('btn-siguiente-cr');
        var inputSelected = document.getElementById('selected-entity-id-cr');
        if (!table || !btnSiguiente || !inputSelected) return;

        function clearSelection() {
            var rows = table.querySelectorAll('tbody tr.selectable-row');
            rows.forEach(function(r) { r.style.backgroundColor = ''; });
        }
        document.addEventListener('click', function(e) {
            var row = e.target.closest('tr.selectable-row');
            if (!row || !table.contains(row)) return;
            if (row.classList.contains('entity-inactive')) return;
            var id = row.getAttribute('data-entity-id');
            if (!id) return;
            e.preventDefault();
            clearSelection();
            row.style.backgroundColor = '#e3f2fd';
            inputSelected.value = id;
            btnSiguiente.disabled = false;
        });
        btnSiguiente.addEventListener('click', function() {
            var id = inputSelected.value;
            if (id) window.location.href = baseUrl + '?section=codigos-recarga&step=2&entity_id=' + id;
        });
        if (typeof jQuery !== 'undefined' && jQuery.fn.DataTable && table.querySelector('tbody tr.selectable-row')) {
            jQuery(table).DataTable({
                ordering: true,
                searching: true,
                paging: true,
                pageLength: 30,
                lengthMenu: [[10, 25, 30, 50, -1], [10, 25, 30, 50, 'Todos']],
                language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' },
                columnDefs: [{ orderable: false, targets: -1 }]
            });
        }
    });
})();
</script>
@endif
@if($section == 'codigos-recarga' && $step === 2 && isset($entity) && $entity)
<script>
document.addEventListener('DOMContentLoaded', function() {
    var t = document.getElementById('tabla-cr-donaciones');
    if (!t || typeof jQuery === 'undefined' || !jQuery.fn.DataTable) return;
    if (!t.querySelector('tbody tr td[colspan]') && t.querySelector('tbody tr')) {
        jQuery(t).DataTable({
            ordering: true,
            searching: true,
            paging: true,
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'Todos']],
            language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' },
            order: [[0, 'desc']]
        });
    }
});
</script>
@endif
@if($section == 'logs-actividad')
<script>
(function() {
    var baseUrl = '{{ url("/configuration") }}';
    function qs(params) {
        var q = new URLSearchParams(params).toString();
        return q ? ('?' + q) : '';
    }
    function highlightRows(container, row) {
        if (!container) return;
        container.querySelectorAll('tbody tr.logs-select-row').forEach(function(r) { r.style.backgroundColor = ''; });
        if (row) row.style.backgroundColor = '#e3f2fd';
    }
    function bindTableSelect(tableId, hiddenId, btnId, onNavigate) {
        var table = document.getElementById(tableId);
        var hid = document.getElementById(hiddenId);
        var btn = document.getElementById(btnId);
        if (!table || !hid || !btn) return;
        function onRowClick(row) {
            var id = row.getAttribute('data-select-id');
            var active = row.getAttribute('data-entity-active');
            if (active === '0') return;
            hid.value = id || '';
            btn.disabled = !id;
            highlightRows(table, row);
        }
        table.addEventListener('click', function(e) {
            var row = e.target.closest('tr.logs-select-row');
            if (!row || !table.contains(row)) return;
            onRowClick(row);
        });
        if (typeof jQuery !== 'undefined' && jQuery.fn.DataTable && table.querySelector('tbody tr')) {
            jQuery(table).DataTable({
                ordering: true,
                searching: true,
                paging: true,
                pageLength: 30,
                lengthMenu: [[10, 25, 30, 50, -1], [10, 25, 30, 50, 'Todos']],
                language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' }
            });
        }
        btn.addEventListener('click', function() {
            if (!hid.value) return;
            onNavigate(hid.value);
        });
    }
    document.addEventListener('DOMContentLoaded', function() {
        bindTableSelect('logs-tabla-administraciones', 'logs-selected-admin-id', 'logs-btn-admin-next', function(id) {
            window.location.href = baseUrl + qs({ section: 'logs-actividad', log_tab: 'administracion', administration_id: id });
        });
        bindTableSelect('logs-tabla-entidades', 'logs-selected-entity-id', 'logs-btn-entity-next', function(id) {
            window.location.href = baseUrl + qs({ section: 'logs-actividad', log_tab: 'entidades', entity_id: id });
        });
        bindTableSelect('logs-tabla-gestores', 'logs-selected-manager-id', 'logs-btn-manager-next', function(id) {
            var b = document.getElementById('logs-btn-manager-next');
            var eid = b ? b.getAttribute('data-entity-id') : '';
            window.location.href = baseUrl + qs({ section: 'logs-actividad', log_tab: 'entidades', entity_id: eid, manager_id: id });
        });
        bindTableSelect('logs-tabla-entidades-ven', 'logs-selected-entity-v-id', 'logs-btn-entity-v-next', function(id) {
            window.location.href = baseUrl + qs({ section: 'logs-actividad', log_tab: 'vendedores', entity_id: id });
        });
        bindTableSelect('logs-tabla-vendedores', 'logs-selected-seller-id', 'logs-btn-seller-next', function(id) {
            var b = document.getElementById('logs-btn-seller-next');
            var eid = b ? b.getAttribute('data-entity-id') : '';
            window.location.href = baseUrl + qs({ section: 'logs-actividad', log_tab: 'vendedores', entity_id: eid, seller_id: id });
        });
        bindTableSelect('logs-tabla-usuarios', 'logs-selected-user-id', 'logs-btn-user-next', function(id) {
            window.location.href = baseUrl + qs({ section: 'logs-actividad', log_tab: 'usuarios', target_user_id: id });
        });

        document.querySelectorAll('table.logs-tabla-actividad-mock').forEach(function(t) {
            if (typeof jQuery === 'undefined' || !jQuery.fn.DataTable) return;
            if (!t.querySelector('tbody tr')) return;
            jQuery(t).DataTable({
                ordering: true,
                searching: true,
                paging: true,
                pageLength: 30,
                lengthMenu: [[10, 25, 30, 50, -1], [10, 25, 30, 50, 'Todos']],
                language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' },
                order: [[0, 'desc']]
            });
        });
    });
})();
</script>
@endif

@if($section == 'ordenes-imprenta' && isset($printOrders) && $printOrders->isNotEmpty())
<script>
(function () {
    function initOrdenesImprentaDataTable() {
        if (typeof jQuery === 'undefined' || !jQuery.fn.DataTable) {
            return;
        }
        var $table = jQuery('#tabla-ordenes-imprenta');
        if (!$table.length || jQuery.fn.DataTable.isDataTable($table)) {
            return;
        }

        $table.DataTable({
            scrollX: true,
            scrollCollapse: true,
            autoWidth: false,
            order: [[8, 'desc']],
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'Todos']],
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
            },
            columnDefs: [
                { width: '110px', targets: 0 },
                { width: '160px', targets: [1, 2] },
                { width: '120px', targets: [3, 4] },
                { width: '150px', targets: [5, 6] },
                { width: '100px', targets: 7 },
                { width: '130px', targets: 8 },
                { width: '130px', targets: 9 },
                { orderable: false, targets: [9, 10] },
                { className: 'text-end', targets: 10 }
            ],
            initComplete: function () {
                this.api().columns.adjust();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initOrdenesImprentaDataTable);
    } else {
        initOrdenesImprentaDataTable();
    }
})();
</script>
@endif
@endsection
