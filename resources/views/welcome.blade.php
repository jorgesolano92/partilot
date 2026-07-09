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

    .content-page .content .container-fluid > .row > [class*="col-"] > .metric-card.card,
    .content-page .content .container-fluid > .row > [class*="col-"] > .panel-card.card {
        border-bottom: 1px solid #d9dde8 !important;
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
    }
    .dashboard-panel .users-table td {
        border-bottom: 1px solid #eef1f6;
        color: #3b4356;
        padding: 8px 0;
    }
    .dashboard-panel .users-table tr:last-child td {
        border-bottom: 0;
    }
    .dashboard-panel .panel-empty {
        min-height: 170px;
        border: 1px dashed #e6e9f2;
        border-radius: 12px;
        background: #fafbfd;
    }
    .content-page .footer {
        display: none !important;
    }

    /* El layout global estira la última fila al viewport; en el panel no aplica */
    .dashboard-panel > .row:last-child,
    .dashboard-panel > .row:last-child > [class*="col-"],
    .dashboard-panel > .row:last-child > [class*="col-"] > .card {
        flex: none !important;
        display: block !important;
        min-height: auto !important;
        height: auto !important;
    }
    .dashboard-panel > .row:last-child > [class*="col-"] > .card > .card-body {
        flex: none !important;
        min-height: auto !important;
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

    <div class="row g-3 mt-1 panel-row-paired">
        <div class="col-xl-{{ ($dashboard['show_users_panel'] ?? false) || ($dashboard['show_sellers_panel'] ?? false) ? '7' : '12' }}">
            <div class="panel-card card">
                <div class="card-body">
                    <div class="panel-head">
                        <div>
                            <h5 class="panel-title">Entidades</h5>
                            <p class="panel-subtitle">Ultimas entidades registradas en PARTILOT</p>
                        </div>
                        <a href="{{ url('entities') }}" class="panel-link">Ver mas</a>
                    </div>
                    @if($dashboard['recent_entities']->isEmpty())
                        <div class="panel-empty"></div>
                    @else
                        <table class="table users-table">
                            <tbody>
                                @foreach($dashboard['recent_entities'] as $entity)
                                    <tr>
                                        <td><a href="{{ url('entities/view', $entity->id) }}" class="text-dark text-decoration-none">#EN{{ str_pad($entity->id, 4, '0', STR_PAD_LEFT) }}</a></td>
                                        <td>{{ $entity->name ?? 'Sin nombre' }}</td>
                                        <td>{{ trim(($entity->province ?? 'Sin provincia') . ' / ' . ($entity->city ?? 'Sin localidad'), ' /') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>

        @if($dashboard['show_users_panel'] ?? false)
            <div class="col-xl-5">
                <div class="panel-card card">
                    <div class="card-body">
                        <div class="panel-head">
                            <div>
                                <h5 class="panel-title">Usuarios</h5>
                                <p class="panel-subtitle">Ultimos usuarios registrados en PARTILOT</p>
                            </div>
                            <a href="{{ url('users') }}" class="panel-link">Ver mas</a>
                        </div>
                        @if($dashboard['recent_users']->isEmpty())
                            <div class="panel-empty"></div>
                        @else
                            <table class="table users-table">
                                <tbody>
                                    @foreach($dashboard['recent_users'] as $user)
                                        <tr>
                                            <td><a href="{{ route('users.show', $user->id) }}" class="text-dark text-decoration-none">#US{{ str_pad($user->id, 4, '0', STR_PAD_LEFT) }}</a></td>
                                            <td>{{ trim(($user->name ?? '') . ' ' . ($user->last_name ?? '') . ' ' . ($user->last_name2 ?? '')) ?: 'Sin nombre' }}</td>
                                            <td>{{ $user->email ?? 'Sin email' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                </div>
            </div>
        @elseif($dashboard['show_sellers_panel'] ?? false)
            <div class="col-xl-5">
                <div class="panel-card card">
                    <div class="card-body">
                        <div class="panel-head">
                            <div>
                                <h5 class="panel-title">Vendedores</h5>
                                <p class="panel-subtitle">Ultimos vendedores registrados en PARTILOT</p>
                            </div>
                            <a href="{{ url('sellers') }}" class="panel-link">Ver mas</a>
                        </div>
                        @if($dashboard['recent_sellers']->isEmpty())
                            <div class="panel-empty"></div>
                        @else
                            <table class="table users-table">
                                <tbody>
                                    @foreach($dashboard['recent_sellers'] as $seller)
                                        <tr>
                                            <td><a href="{{ route('sellers.show', $seller->id) }}" class="text-dark text-decoration-none">#VE{{ str_pad($seller->id, 4, '0', STR_PAD_LEFT) }}</a></td>
                                            <td>{{ $seller->user ? trim($seller->user->name . ' ' . $seller->user->last_name) : ($seller->name ?? 'Sin nombre') }}</td>
                                            <td>{{ $seller->user?->email ?? ($seller->email ?? 'Sin email') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </div>

    @if($dashboard['show_administrations_panel'] ?? false)
    <div class="row g-3 mt-1">
        <div class="col-12">
            <div class="panel-card card panel-card--auto" style="height: 260px !important;">
                <div class="card-body">
                    <div class="panel-head">
                        <div>
                            <h5 class="panel-title">Administraciones</h5>
                            <p class="panel-subtitle">Ultimas administraciones registradas en PARTILOT</p>
                        </div>
                        <a href="{{ url('administrations') }}" class="panel-link">Ver mas</a>
                    </div>
                    @if($dashboard['recent_administrations']->isEmpty())
                        <div class="panel-empty"></div>
                    @else
                        <table class="table users-table">
                            <tbody>
                                @foreach($dashboard['recent_administrations'] as $administration)
                                    <tr>
                                        <td><a href="{{ url('administrations/view', $administration->id) }}" class="text-dark text-decoration-none">#AD{{ str_pad($administration->id, 5, '0', STR_PAD_LEFT) }}</a></td>
                                        <td>{{ $administration->name }}</td>
                                        <td>{{ trim(($administration->province ?? 'Sin provincia') . ' / ' . ($administration->city ?? 'Sin localidad'), ' /') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif
    
</div> <!-- container -->

@endsection