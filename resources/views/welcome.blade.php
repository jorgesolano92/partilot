@extends('layouts.layout')

@section('title','Panel')

@section('styles')
<style>
    .dashboard-panel {
        padding-bottom: 22px;
    }
    .dashboard-panel .page-title-box {
        margin-bottom: 12px;
    }
    .dashboard-panel .page-title {
        font-size: 2rem;
        font-weight: 700;
        color: #1f2430;
        margin: 0;
    }
    .dashboard-panel .metric-card.card,
    .dashboard-panel .panel-card.card {
        border: 1px solid #d9dde8 !important;
        border-radius: 18px !important;
        box-shadow: none;
        background: #fff;
    }

    .content-page .content .container-fluid.dashboard-panel > .row > [class*="col-"] > .metric-card.card,
    .content-page .content .container-fluid.dashboard-panel > .row > [class*="col-"] > .panel-card.card {
        border-bottom: 1px solid #d9dde8 !important;
    }
    .content-page .content .container-fluid.dashboard-panel > .row.panel-row-footer-join > [class*="col-"] > .panel-card.card,
    .content-page .content .container-fluid.dashboard-panel > .row.panel-row-footer-join > [class*="col-"] > .panel-card--footer-join {
        border-radius: 18px 18px 0 0 !important;
        border-bottom: 0 !important;
        margin-bottom: 0 !important;
    }
    .dashboard-panel .metric-card .card-body {
        padding: 12px 14px;
    }
    .dashboard-panel .metric-title {
        font-size: .75rem;
        font-weight: 700;
        color: #2f3545;
        text-transform: uppercase;
        margin-bottom: 2px;
    }
    .dashboard-panel .metric-value {
        font-size: 2.2rem;
        font-weight: 700;
        line-height: 1.05;
        color: #1f2430;
        margin-bottom: 4px;
    }
    .dashboard-panel .metric-note {
        font-size: .78rem;
        color: #6f778a;
        margin-bottom: 8px;
    }
    .dashboard-panel .metric-wave {
        height: 18px;
        border-radius: 999px;
        background: linear-gradient(90deg, rgba(252, 185, 65, .15) 0%, rgba(252, 185, 65, .7) 60%, rgba(252, 185, 65, .1) 100%);
    }
    .dashboard-panel .metric-wave.blue {
        background: linear-gradient(90deg, rgba(52, 124, 238, .15) 0%, rgba(52, 124, 238, .68) 60%, rgba(52, 124, 238, .12) 100%);
    }
    .dashboard-panel .metric-wave.red {
        background: linear-gradient(90deg, rgba(219, 72, 95, .15) 0%, rgba(219, 72, 95, .68) 60%, rgba(219, 72, 95, .12) 100%);
    }
    .dashboard-panel .metric-wave.green {
        background: linear-gradient(90deg, rgba(136, 186, 41, .15) 0%, rgba(136, 186, 41, .68) 60%, rgba(136, 186, 41, .12) 100%);
    }
    .dashboard-panel .panel-row-paired .panel-card {
        min-height: 260px;
        height: 260px !important;
    }
    .dashboard-panel .panel-row-paired .panel-card .card-body {
        height: 100%;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    .dashboard-panel .panel-table-wrap {
        flex: 1;
        min-height: 0;
        overflow: auto;
    }
    .dashboard-panel .panel-row-entity .panel-card {
        height: 260px !important;
        min-height: 260px !important;
    }
    .dashboard-panel .panel-row-entity .panel-card .card-body {
        height: 100%;
        overflow: auto;
    }
    .dashboard-panel .panel-row-entity .panel-empty {
        min-height: 120px;
    }
    .dashboard-panel .panel-card--auto {
        min-height: auto;
    }
    .dashboard-panel .panel-card .card-body {
        padding: 14px 16px;
    }
    .dashboard-panel .panel-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }
    .dashboard-panel .panel-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: #202635;
        margin: 0;
    }
    .dashboard-panel .panel-subtitle {
        font-size: .8rem;
        color: #7a8194;
        margin: 0;
    }
    .dashboard-panel .panel-link {
        font-size: .78rem;
        color: #4a5266;
        border: 1px solid #e0e4ef;
        border-radius: 999px;
        padding: 4px 10px;
        text-decoration: none;
        background: #fff;
    }
    .dashboard-panel .users-table {
        margin: 0;
        font-size: .83rem;
        table-layout: fixed;
        width: 100%;
    }
    .dashboard-panel .users-table th {
        font-size: .72rem;
        font-weight: 600;
        color: #7a8194;
        text-transform: uppercase;
        letter-spacing: .02em;
        border-bottom: 1px solid #eef1f6;
        padding: 0 0 8px;
    }
    .dashboard-panel .users-table td {
        border-bottom: 1px solid #eef1f6;
        color: #3b4356;
        padding: 8px 8px 8px 0;
        vertical-align: middle;
    }
    .dashboard-panel .users-table .col-id {
        width: 74px;
        white-space: nowrap;
    }
    .dashboard-panel .users-table .col-name {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .dashboard-panel .users-table .col-meta {
        width: 42%;
        text-align: right;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        color: #6f778a;
        font-size: .78rem;
        padding-right: 0;
    }
    .dashboard-panel .users-table tr:last-child td {
        border-bottom: 0;
    }
    .dashboard-panel .panel-empty {
        min-height: 170px;
        border: 1px dashed #e6e9f2;
        border-radius: 12px;
        background: #fafbfd;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #8a91a3;
        font-size: .84rem;
    }

    /*
     * Panel: unir el footer con la última card (Administraciones).
     * Sin esto el pie queda suelto o se oculta y no cierra el bloque.
     */
    .dashboard-panel {
        padding-bottom: 0 !important;
        flex: 1 1 auto !important;
        display: flex !important;
        flex-direction: column !important;
        min-height: 0 !important;
    }
    .dashboard-panel > .row.panel-row-footer-join {
        margin-bottom: 0 !important;
    }
    .content-page .content .container-fluid.dashboard-panel > .row.panel-row-footer-join > [class*="col-"] > .panel-card.card,
    .dashboard-panel > .row.panel-row-footer-join > [class*="col-"] > .panel-card.card {
        border-radius: 18px 18px 0 0 !important;
        border-bottom: 0 !important;
        margin-bottom: 0 !important;
    }
    /* Si no hay Administraciones, ocultar pie en Panel (sin pelear con inline display) */
    body:has(.dashboard-panel):not(:has(.panel-row-footer-join)) #wrapper .content-page footer.footer {
        display: none !important;
    }
    body:has(.dashboard-panel):has(.panel-row-footer-join) #wrapper .content-page footer.footer {
        display: flex !important;
        position: relative !important;
        bottom: auto !important;
        left: auto !important;
        right: auto !important;
        width: auto !important;
        height: 60px !important;
        align-items: center !important;
        flex-shrink: 0 !important;
        margin: -1px 15px 24px !important;
        padding: 0 1.5rem !important;
        border: 1px solid #d9dde8 !important;
        border-top: 0 !important;
        border-radius: 0 0 18px 18px !important;
        background: #fff !important;
        box-shadow: none !important;
    }

    /* El layout global estira la última fila al viewport; en el panel no aplica */
    .dashboard-panel > .row:last-child:not(.panel-row-entity),
    .dashboard-panel > .row:last-child:not(.panel-row-entity) > [class*="col-"],
    .dashboard-panel > .row:last-child:not(.panel-row-entity) > [class*="col-"] > .card {
        flex: none !important;
        display: block !important;
        min-height: auto !important;
        height: auto !important;
    }
    .dashboard-panel > .row:last-child:not(.panel-row-entity) > [class*="col-"] > .card > .card-body {
        flex: none !important;
        min-height: auto !important;
    }
    .dashboard-panel .panel-row-entity > [class*="col-"] > .panel-card {
        height: 260px !important;
        min-height: 260px !important;
    }
</style>
@endsection

@section('content')

<!-- Start Content-->
<div class="container-fluid dashboard-panel">
    
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <h4 class="page-title">Panel</h4>
            </div>
        </div>
    </div>     

    <div class="row g-3">
        @php
            $metricColClass = ($dashboard['show_users_metric'] ?? true) ? 'col-md-6 col-xl-3' : 'col-md-6 col-xl-4';
            $waveClasses = ['', 'blue', 'red', 'green'];
            $metricItems = array_values(array_filter([
                ($dashboard['show_users_metric'] ?? false) ? ['key' => 'users', 'title' => 'Total Usuarios'] : null,
                ['key' => 'entities', 'title' => 'Total Entidades'],
                ['key' => 'sellers', 'title' => 'Total Vendedores'],
                ['key' => 'participations', 'title' => 'Total Participaciones'],
            ]));
        @endphp

        @foreach($metricItems as $index => $metricItem)
            @php
                $metric = $dashboard['metrics'][$metricItem['key']];
                $waveClass = $waveClasses[$index % count($waveClasses)];
                if ($metric['change_positive'] === false) {
                    $waveClass = 'red';
                } elseif ($metric['change_positive'] === true && $index === 2) {
                    $waveClass = 'green';
                }
            @endphp
            <div class="{{ $metricColClass }}">
                <div class="metric-card card">
                    <div class="card-body">
                        <div class="metric-title">{{ $metricItem['title'] }}</div>
                        <div class="metric-value">{{ $metric['formatted'] }}</div>
                        <div class="metric-note">{{ $metric['change_label'] }}</div>
                        <div class="metric-wave {{ $waveClass }}"></div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-3 mt-1 panel-row-paired @if($dashboard['show_sellers_panel'] ?? false) panel-row-entity @endif">
        @php
            $entitiesPanelCol = ($dashboard['show_users_panel'] ?? false) || ($dashboard['show_sellers_panel'] ?? false)
                ? (($dashboard['show_sellers_panel'] ?? false) ? 'col-md-6' : 'col-xl-7')
                : 'col-12';
            $sidePanelCol = ($dashboard['show_sellers_panel'] ?? false) ? 'col-md-6' : 'col-xl-5';
        @endphp
        <div class="{{ $entitiesPanelCol }}">
            <div class="panel-card card">
                <div class="card-body">
                    <div class="panel-head">
                        <div>
                            <h5 class="panel-title">Entidades</h5>
                            <p class="panel-subtitle">Últimas entidades registradas en PARTILOT</p>
                        </div>
                        <a href="{{ url('entities') }}" class="panel-link">Ver más</a>
                    </div>
                    @if($dashboard['recent_entities']->isEmpty())
                        <div class="panel-empty">No hay entidades registradas</div>
                    @else
                        <div class="panel-table-wrap">
                            <table class="table users-table mb-0">
                                <thead>
                                    <tr>
                                        <th class="col-id">ID</th>
                                        <th class="col-name">Nombre</th>
                                        <th class="col-meta">Ubicación</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($dashboard['recent_entities'] as $entity)
                                        <tr>
                                            <td class="col-id"><a href="{{ url('entities/view', $entity->id) }}" class="text-dark text-decoration-none">#EN{{ str_pad($entity->id, 4, '0', STR_PAD_LEFT) }}</a></td>
                                            <td class="col-name" title="{{ $entity->name ?? 'Sin nombre' }}">{{ $entity->name ?? 'Sin nombre' }}</td>
                                            <td class="col-meta" title="{{ trim(($entity->province ?? 'Sin provincia') . ' / ' . ($entity->city ?? 'Sin localidad'), ' /') }}">{{ trim(($entity->province ?? 'Sin provincia') . ' / ' . ($entity->city ?? 'Sin localidad'), ' /') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        @if($dashboard['show_users_panel'] ?? false)
            <div class="{{ $sidePanelCol }}">
                <div class="panel-card card">
                    <div class="card-body">
                        <div class="panel-head">
                            <div>
                                <h5 class="panel-title">Usuarios</h5>
                                <p class="panel-subtitle">Últimos usuarios registrados en PARTILOT</p>
                            </div>
                            <a href="{{ url('users') }}" class="panel-link">Ver más</a>
                        </div>
                        @if($dashboard['recent_users']->isEmpty())
                            <div class="panel-empty">No hay usuarios registrados</div>
                        @else
                            <div class="panel-table-wrap">
                                <table class="table users-table mb-0">
                                    <thead>
                                        <tr>
                                            <th class="col-id">ID</th>
                                            <th class="col-name">Nombre</th>
                                            <th class="col-meta">Email</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($dashboard['recent_users'] as $user)
                                            <tr>
                                                <td class="col-id"><a href="{{ route('users.show', $user->id) }}" class="text-dark text-decoration-none">#US{{ str_pad($user->id, 4, '0', STR_PAD_LEFT) }}</a></td>
                                                <td class="col-name" title="{{ trim(($user->name ?? '') . ' ' . ($user->last_name ?? '') . ' ' . ($user->last_name2 ?? '')) ?: 'Sin nombre' }}">{{ trim(($user->name ?? '') . ' ' . ($user->last_name ?? '') . ' ' . ($user->last_name2 ?? '')) ?: 'Sin nombre' }}</td>
                                                <td class="col-meta" title="{{ $user->email ?? 'Sin email' }}">{{ $user->email ?? 'Sin email' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @elseif($dashboard['show_sellers_panel'] ?? false)
            <div class="{{ $sidePanelCol }}">
                <div class="panel-card card">
                    <div class="card-body">
                        <div class="panel-head">
                            <div>
                                <h5 class="panel-title">Vendedores</h5>
                                <p class="panel-subtitle">Últimos vendedores registrados en PARTILOT</p>
                            </div>
                            <a href="{{ url('sellers') }}" class="panel-link">Ver más</a>
                        </div>
                        @if($dashboard['recent_sellers']->isEmpty())
                            <div class="panel-empty">No hay vendedores registrados</div>
                        @else
                            <div class="panel-table-wrap">
                                <table class="table users-table mb-0">
                                    <thead>
                                        <tr>
                                            <th class="col-id">ID</th>
                                            <th class="col-name">Nombre</th>
                                            <th class="col-meta">Email</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($dashboard['recent_sellers'] as $seller)
                                            <tr>
                                                <td class="col-id"><a href="{{ route('sellers.show', $seller->id) }}" class="text-dark text-decoration-none">#VE{{ str_pad($seller->id, 4, '0', STR_PAD_LEFT) }}</a></td>
                                                <td class="col-name" title="{{ $seller->user ? trim($seller->user->name . ' ' . $seller->user->last_name) : ($seller->name ?? 'Sin nombre') }}">{{ $seller->user ? trim($seller->user->name . ' ' . $seller->user->last_name) : ($seller->name ?? 'Sin nombre') }}</td>
                                                <td class="col-meta" title="{{ $seller->user?->email ?? ($seller->email ?? 'Sin email') }}">{{ $seller->user?->email ?? ($seller->email ?? 'Sin email') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </div>

    @if($dashboard['show_administrations_panel'] ?? false)
    <div class="row g-3 mt-1 panel-row-footer-join">
        <div class="col-12">
            <div class="panel-card card panel-card--auto panel-card--footer-join">
                <div class="card-body">
                    <div class="panel-head">
                        <div>
                            <h5 class="panel-title">Administraciones</h5>
                            <p class="panel-subtitle">Últimas administraciones registradas en PARTILOT</p>
                        </div>
                        <a href="{{ url('administrations') }}" class="panel-link">Ver más</a>
                    </div>
                    @if($dashboard['recent_administrations']->isEmpty())
                        <div class="panel-empty">No hay administraciones registradas</div>
                    @else
                        <div class="panel-table-wrap">
                            <table class="table users-table mb-0">
                                <thead>
                                    <tr>
                                        <th class="col-id">ID</th>
                                        <th class="col-name">Nombre</th>
                                        <th class="col-meta">Ubicación</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($dashboard['recent_administrations'] as $administration)
                                        <tr>
                                            <td class="col-id"><a href="{{ url('administrations/view', $administration->id) }}" class="text-dark text-decoration-none">#AD{{ str_pad($administration->id, 5, '0', STR_PAD_LEFT) }}</a></td>
                                            <td class="col-name" title="{{ $administration->name }}">{{ $administration->name }}</td>
                                            <td class="col-meta" title="{{ trim(($administration->province ?? 'Sin provincia') . ' / ' . ($administration->city ?? 'Sin localidad'), ' /') }}">{{ trim(($administration->province ?? 'Sin provincia') . ' / ' . ($administration->city ?? 'Sin localidad'), ' /') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif
    
</div> <!-- container -->

@endsection