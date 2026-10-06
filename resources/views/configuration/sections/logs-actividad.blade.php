@php
    $url = function (array $q) {
        return route('configuration.index', array_merge(['section' => 'logs-actividad'], $q));
    };
    $tabOnly = fn (string $tab) => $url(['log_tab' => $tab]);
@endphp

<style>
.logs-wizard-bar .form-wizard-element {
    text-decoration: none;
    color: inherit;
    cursor: pointer;
}
.logs-wizard-bar .form-wizard-element:hover:not(.active) {
    opacity: 0.85;
}
.logs-context-card {
    border-radius: 12px;
    border: 1px solid #e9ecef;
    background: #fafbfc;
    padding: 12px 16px;
    min-width: 200px;
}
</style>

<div class="form-card bs">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
        <div>
            <h4 class="mb-0">Logs de Actividad</h4>
            <small class="text-muted">Consulta por ámbito. Solo eventos de auditoría reales (aceptaciones legales y cambios de configuración).</small>
        </div>
    </div>

    {{-- Pills estilo design/format (form-wizard-element) --}}
    <h4 class="header-title mb-0">
        <div class="d-flex p-2 flex-wrap logs-wizard-bar" style="align-items: center; justify-content: center; gap: 8px;">
            <a href="{{ $tabOnly('partilot') }}" class="form-wizard-element {{ $logTab === 'partilot' ? 'active' : '' }}" style="width: 190px;">
                <span style="top: -4px; margin-right: 8px;">1</span>
                <label class="mb-0">Actividad de<br>Partilot</label>
            </a>
            <a href="{{ $tabOnly('administracion') }}" class="form-wizard-element {{ $logTab === 'administracion' ? 'active' : '' }}" style="width: 190px;">
                <span style="top: -4px; margin-right: 8px;">2</span>
                <label class="mb-0">Actividad<br>Administración</label>
            </a>
            <a href="{{ $tabOnly('entidades') }}" class="form-wizard-element {{ $logTab === 'entidades' ? 'active' : '' }}" style="width: 190px;">
                <span style="top: -4px; margin-right: 8px;">3</span>
                <label class="mb-0">Actividad<br>Entidades</label>
            </a>
            <a href="{{ $tabOnly('vendedores') }}" class="form-wizard-element {{ $logTab === 'vendedores' ? 'active' : '' }}" style="width: 190px;">
                <span style="top: -4px; margin-right: 8px;">4</span>
                <label class="mb-0">Actividad<br>Vendedores</label>
            </a>
            <a href="{{ $tabOnly('usuarios') }}" class="form-wizard-element {{ $logTab === 'usuarios' ? 'active' : '' }}" style="width: 190px;">
                <span style="top: -4px; margin-right: 8px;">5</span>
                <label class="mb-0">Actividad<br>Usuarios</label>
            </a>
        </div>
    </h4>

    {{-- ========== 1 Partilot ========== --}}
    @if($logTab === 'partilot')
        <div class="form-card bs mt-3">
            <h5 class="mb-0 mt-1">Actividad global del panel</h5>
            <small><i>Actividad global auditada del panel Partilot.</i></small>
        </div>
        @include('configuration.sections.logs-actividad-activity', [
            'activityTitle' => 'Actividad administrador',
            'activitySubtitle' => 'Aceptaciones legales y cambios de configuración registrados.',
        ])
    @endif

    {{-- ========== 2 Administración ========== --}}
    @if($logTab === 'administracion')
        @if(!$selectedLogAdministration)
            <div class="form-card bs mt-3">
                <h5 class="mb-0 mt-1">Selección Administración</h5>
                <small><i>Selecciona una administración de lotería para filtrar el log (listado real).</i></small>

                <form method="get" action="{{ route('configuration.index') }}" class="mt-3 mb-3">
                    <input type="hidden" name="section" value="logs-actividad">
                    <input type="hidden" name="log_tab" value="administracion">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label small">Búsqueda</label>
                            <div class="d-flex gap-1">
                                <input type="search" name="busqueda_adm" class="form-control form-control-sm" placeholder="Nombre, provincia…" value="{{ request('busqueda_adm') }}">
                                <button type="submit" class="btn btn-sm btn-light"><i class="fe-search"></i></button>
                            </div>
                        </div>
                    </div>
                </form>

                @php
                    $admList = $logsAdministrations;
                    if (request()->filled('busqueda_adm')) {
                        $s = request('busqueda_adm');
                        $admList = $admList->filter(function ($a) use ($s) {
                            return str_contains(strtolower($a->name.' '.$a->province.' '.$a->city.' '.$a->email), strtolower($s));
                        });
                    }
                @endphp

                <div class="table-responsive" style="min-height: 180px;">
                    <table class="table table-striped table-centered nowrap w-100 mb-0" id="logs-tabla-administraciones">
                        <thead class="table-light">
                            <tr>
                                <th>Orden ID</th>
                                <th>Nombre</th>
                                <th>Provincia</th>
                                <th>Localidad</th>
                                <th>Email</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($admList as $adm)
                                <tr class="logs-select-row" style="cursor:pointer;" data-select-id="{{ $adm->id }}" data-select-type="administration">
                                    <td>#AD{{ str_pad($adm->id, 4, '0', STR_PAD_LEFT) }}</td>
                                    <td>{{ $adm->name }}</td>
                                    <td>{{ $adm->province ?? '—' }}</td>
                                    <td>{{ $adm->city ?? '—' }}</td>
                                    <td>{{ $adm->email ?? '—' }}</td>
                                    <td><span class="badge bg-{{ $adm->status_class }}">{{ $adm->status_text }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted">No hay administraciones visibles para tu usuario.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-end mt-3 pt-2 border-top">
                    <input type="hidden" id="logs-selected-admin-id" value="">
                    <button type="button" class="btn btn-md btn-light" disabled id="logs-btn-admin-next" style="border-radius: 30px; min-width: 160px; background-color: #e78307; color: #333; padding: 8px 16px; font-weight: bolder;">
                        Seleccionar <i class="ri-arrow-right-line ms-1"></i>
                    </button>
                </div>
            </div>
        @else
            <div class="d-flex flex-wrap gap-2 mt-3 mb-2 align-items-center">
                <div class="logs-context-card d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center" style="width:44px;height:44px;"><i class="ri-store-2-line text-muted"></i></div>
                    <div>
                        <div class="fw-semibold">{{ $selectedLogAdministration->name }}</div>
                        <small class="text-muted">{{ $selectedLogAdministration->province ?? '—' }} · {{ $selectedLogAdministration->city ?? '—' }}</small>
                    </div>
                </div>
                <a href="{{ $url(['log_tab' => 'administracion']) }}" class="btn btn-sm btn-outline-secondary">Cambiar administración</a>
            </div>
            @include('configuration.sections.logs-actividad-activity', [
                'activityTitle' => 'Actividad administración',
                'activitySubtitle' => 'Aceptaciones legales y cambios auditados de esta administración.',
            ])
        @endif
    @endif

    {{-- ========== 3 Entidades (entidad → gestor → actividad) ========== --}}
    @if($logTab === 'entidades')
        @if(!$selectedLogEntity)
            <div class="form-card bs mt-3">
                <h5 class="mb-0 mt-1">Selección Entidad</h5>
                <small><i>Selecciona la entidad (datos reales).</i></small>

                <form method="get" action="{{ route('configuration.index') }}" id="logs-form-filtros-ent" class="mt-3 mb-3">
                    <input type="hidden" name="section" value="logs-actividad">
                    <input type="hidden" name="log_tab" value="entidades">
                    <div class="row g-2">
                        <div class="col-md-3">
                            <label class="form-label small">Provincia</label>
                            <select name="provincia" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="">Todas</option>
                                @foreach($logsProvincias as $p)
                                    <option value="{{ $p }}" {{ request('provincia') == $p ? 'selected' : '' }}>{{ $p }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Localidad</label>
                            <select name="localidad" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="">Todas</option>
                                @foreach($logsLocalidades as $c)
                                    <option value="{{ $c }}" {{ request('localidad') == $c ? 'selected' : '' }}>{{ $c }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Busqueda</label>
                            <div class="d-flex gap-1">
                                <input type="search" name="busqueda" class="form-control form-control-sm" value="{{ request('busqueda') }}">
                                <button type="submit" class="btn btn-sm btn-light"><i class="fe-search"></i></button>
                            </div>
                        </div>
                    </div>
                </form>

                <div class="table-responsive" style="min-height: 200px;">
                    <table class="table table-striped table-centered nowrap w-100 mb-0" id="logs-tabla-entidades">
                        <thead class="table-light">
                            <tr>
                                <th>Orden ID</th>
                                <th>Entidad</th>
                                <th>Provincia</th>
                                <th>Localidad</th>
                                <th>Administración</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($logsEntities as $ent)
                                @php $isActive = $ent->status == 1; @endphp
                                <tr class="logs-select-row {{ $isActive ? '' : 'opacity-50' }}" style="cursor: {{ $isActive ? 'pointer' : 'not-allowed' }};" data-select-id="{{ $ent->id }}" data-select-type="entity" data-entity-active="{{ $isActive ? 1 : 0 }}">
                                    <td>#EN{{ str_pad($ent->id, 4, '0', STR_PAD_LEFT) }}</td>
                                    <td>{{ $ent->name }}</td>
                                    <td>{{ $ent->province ?? '—' }}</td>
                                    <td>{{ $ent->city ?? '—' }}</td>
                                    <td>{{ $ent->administration->name ?? '—' }}</td>
                                    <td><span class="badge bg-{{ $ent->status == 1 ? 'success' : ($ent->status == 0 ? 'danger' : 'secondary') }}">{{ $ent->status_text }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted">No hay entidades.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-end mt-3 pt-2 border-top">
                    <input type="hidden" id="logs-selected-entity-id" value="">
                    <button type="button" class="btn btn-md btn-light" disabled id="logs-btn-entity-next" style="border-radius: 30px; min-width: 160px; background-color: #e78307; color: #333; padding: 8px 16px; font-weight: bolder;">
                        Seleccionar <i class="ri-arrow-right-line ms-1"></i>
                    </button>
                </div>
            </div>
        @elseif(!$selectedLogManager)
            <div class="d-flex flex-wrap gap-2 mt-3 mb-2 align-items-center">
                <div class="logs-context-card d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center" style="width:44px;height:44px;"><i class="ri-building-line text-muted"></i></div>
                    <div>
                        <div class="fw-semibold">{{ $selectedLogEntity->name }}</div>
                        <small class="text-muted">{{ $selectedLogEntity->province ?? $selectedLogEntity->city ?? '—' }}</small>
                    </div>
                </div>
                <a href="{{ $url(['log_tab' => 'entidades']) }}" class="btn btn-sm btn-outline-secondary">Cambiar entidad</a>
            </div>

            <div class="form-card bs mt-2">
                <h5 class="mb-0 mt-1">Selección gestor</h5>
                <small><i>Elige el gestor asociado a la entidad.</i></small>

                <div class="table-responsive mt-3">
                    <table class="table table-striped table-centered nowrap w-100 mb-0" id="logs-tabla-gestores">
                        <thead class="table-light">
                            <tr>
                                <th>Gestor</th>
                                <th>Email</th>
                                <th>Principal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($logsManagers as $man)
                                <tr class="logs-select-row" style="cursor:pointer;" data-select-id="{{ $man->id }}" data-select-type="manager">
                                    <td>{{ $man->user->name ?? '—' }}</td>
                                    <td>{{ $man->user->email ?? '—' }}</td>
                                    <td>@if($man->is_primary)<span class="badge bg-primary">Sí</span>@else<span class="text-muted">—</span>@endif</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-muted text-center">No hay gestores para esta entidad.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-end mt-3 pt-2 border-top">
                    <input type="hidden" id="logs-selected-manager-id" value="">
                    <button type="button" class="btn btn-md btn-light" disabled id="logs-btn-manager-next" data-entity-id="{{ $selectedLogEntity->id }}" style="border-radius: 30px; min-width: 160px; background-color: #e78307; color: #333; padding: 8px 16px; font-weight: bolder;">
                        Seleccionar <i class="ri-arrow-right-line ms-1"></i>
                    </button>
                </div>
            </div>
        @else
            <div class="d-flex flex-wrap gap-2 mt-3 mb-2 align-items-center">
                <div class="logs-context-card d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center" style="width:44px;height:44px;"><i class="ri-building-line text-muted"></i></div>
                    <div>
                        <div class="fw-semibold">{{ $selectedLogEntity->name }}</div>
                        <small class="text-muted">{{ $selectedLogEntity->province ?? '—' }}</small>
                    </div>
                </div>
                <div class="logs-context-card d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center" style="width:44px;height:44px;"><i class="ri-user-line text-muted"></i></div>
                    <div>
                        <div class="fw-semibold">{{ $selectedLogManager->user->name ?? 'Gestor' }}</div>
                        <small class="text-muted">{{ $selectedLogManager->user->email ?? '' }}</small>
                    </div>
                </div>
                <a href="{{ $url(['log_tab' => 'entidades', 'entity_id' => $selectedLogEntity->id]) }}" class="btn btn-sm btn-outline-secondary">Cambiar gestor</a>
                <a href="{{ $url(['log_tab' => 'entidades']) }}" class="btn btn-sm btn-link">Otra entidad</a>
            </div>
            @include('configuration.sections.logs-actividad-activity', [
                'activityTitle' => 'Actividad gestor',
                'activitySubtitle' => 'Eventos auditados del gestor en la entidad seleccionada.',
            ])
        @endif
    @endif

    {{-- ========== 4 Vendedores ========== --}}
    @if($logTab === 'vendedores')
        @if(!$selectedLogEntity)
            <div class="form-card bs mt-3">
                <h5 class="mb-0 mt-1">Selección Entidad</h5>
                <small><i>Primero elige la entidad del vendedor.</i></small>

                <form method="get" action="{{ route('configuration.index') }}" class="mt-3 mb-3">
                    <input type="hidden" name="section" value="logs-actividad">
                    <input type="hidden" name="log_tab" value="vendedores">
                    <div class="row g-2">
                        <div class="col-md-3">
                            <label class="form-label small">Provincia</label>
                            <select name="provincia" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="">Todas</option>
                                @foreach($logsProvincias as $p)
                                    <option value="{{ $p }}" {{ request('provincia') == $p ? 'selected' : '' }}>{{ $p }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Localidad</label>
                            <select name="localidad" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="">Todas</option>
                                @foreach($logsLocalidades as $c)
                                    <option value="{{ $c }}" {{ request('localidad') == $c ? 'selected' : '' }}>{{ $c }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Busqueda</label>
                            <div class="d-flex gap-1">
                                <input type="search" name="busqueda" class="form-control form-control-sm" value="{{ request('busqueda') }}">
                                <button type="submit" class="btn btn-sm btn-light"><i class="fe-search"></i></button>
                            </div>
                        </div>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-striped table-centered nowrap w-100 mb-0" id="logs-tabla-entidades-ven">
                        <thead class="table-light">
                            <tr>
                                <th>Orden ID</th>
                                <th>Entidad</th>
                                <th>Provincia</th>
                                <th>Localidad</th>
                                <th>Administración</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($logsEntities as $ent)
                                @php $isActive = $ent->status == 1; @endphp
                                <tr class="logs-select-row" style="cursor: {{ $isActive ? 'pointer' : 'not-allowed' }};" data-select-id="{{ $ent->id }}" data-select-type="entity_v" data-entity-active="{{ $isActive ? 1 : 0 }}">
                                    <td>#EN{{ str_pad($ent->id, 4, '0', STR_PAD_LEFT) }}</td>
                                    <td>{{ $ent->name }}</td>
                                    <td>{{ $ent->province ?? '—' }}</td>
                                    <td>{{ $ent->city ?? '—' }}</td>
                                    <td>{{ $ent->administration->name ?? '—' }}</td>
                                    <td><span class="badge bg-{{ $ent->status == 1 ? 'success' : ($ent->status == 0 ? 'danger' : 'secondary') }}">{{ $ent->status_text }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted">No hay entidades.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-end mt-3 pt-2 border-top">
                    <input type="hidden" id="logs-selected-entity-v-id" value="">
                    <button type="button" class="btn btn-md btn-light" disabled id="logs-btn-entity-v-next" style="border-radius: 30px; min-width: 160px; background-color: #e78307; color: #333; padding: 8px 16px; font-weight: bolder;">
                        Seleccionar <i class="ri-arrow-right-line ms-1"></i>
                    </button>
                </div>
            </div>
        @elseif(!$selectedLogSeller)
            <div class="d-flex flex-wrap gap-2 mt-3 mb-2 align-items-center">
                <div class="logs-context-card d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center" style="width:44px;height:44px;"><i class="ri-building-line text-muted"></i></div>
                    <div>
                        <div class="fw-semibold">{{ $selectedLogEntity->name }}</div>
                        <small class="text-muted">{{ $selectedLogEntity->province ?? '—' }}</small>
                    </div>
                </div>
                <a href="{{ $url(['log_tab' => 'vendedores']) }}" class="btn btn-sm btn-outline-secondary">Cambiar entidad</a>
            </div>

            <div class="form-card bs mt-2">
                <h5 class="mb-0 mt-1">Selección vendedor</h5>
                <small><i>Vendedores vinculados a la entidad.</i></small>

                <div class="table-responsive mt-3">
                    <table class="table table-hover table-centered mb-0 w-100" id="logs-tabla-vendedores">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Email</th>
                                <th>Tipo</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($logsSellers as $sel)
                                <tr class="logs-select-row" style="cursor:pointer;" data-select-id="{{ $sel->id }}" data-select-type="seller">
                                    <td>#VE{{ str_pad($sel->id, 4, '0', STR_PAD_LEFT) }}</td>
                                    <td>{{ trim($sel->name.' '.$sel->last_name.' '.$sel->last_name2) }}</td>
                                    <td>{{ $sel->email }}</td>
                                    <td><span class="badge {{ ($sel->seller_type ?? '') === 'externo' ? 'bg-secondary' : 'bg-warning text-dark' }}">{{ ($sel->seller_type ?? '') === 'externo' ? 'Externo' : 'Partilot' }}</span></td>
                                    <td><span class="badge bg-{{ $sel->status_class }}">{{ $sel->status_text }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted">No hay vendedores en esta entidad.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-end mt-3 pt-2 border-top">
                    <input type="hidden" id="logs-selected-seller-id" value="">
                    <button type="button" class="btn btn-md btn-light" disabled id="logs-btn-seller-next" data-entity-id="{{ $selectedLogEntity->id }}" style="border-radius: 30px; min-width: 160px; background-color: #e78307; color: #333; padding: 8px 16px; font-weight: bolder;">
                        Seleccionar <i class="ri-arrow-right-line ms-1"></i>
                    </button>
                </div>
            </div>
        @else
            <div class="d-flex flex-wrap gap-2 mt-3 mb-2 align-items-center">
                <div class="logs-context-card d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center" style="width:44px;height:44px;"><i class="ri-building-line text-muted"></i></div>
                    <div>
                        <div class="fw-semibold">{{ $selectedLogEntity->name }}</div>
                        <small class="text-muted">{{ $selectedLogEntity->province ?? '—' }}</small>
                    </div>
                </div>
                <div class="logs-context-card d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center" style="width:44px;height:44px;"><i class="ri-user-star-line text-muted"></i></div>
                    <div>
                        <div class="fw-semibold">{{ trim($selectedLogSeller->name.' '.$selectedLogSeller->last_name) }}</div>
                        <small class="text-muted">{{ $selectedLogSeller->email }}</small>
                    </div>
                </div>
                <a href="{{ $url(['log_tab' => 'vendedores', 'entity_id' => $selectedLogEntity->id]) }}" class="btn btn-sm btn-outline-secondary">Cambiar vendedor</a>
                <a href="{{ $url(['log_tab' => 'vendedores']) }}" class="btn btn-sm btn-link">Otra entidad</a>
            </div>
            @include('configuration.sections.logs-actividad-activity', [
                'activityTitle' => 'Actividad vendedor',
                'activitySubtitle' => 'Eventos auditados del vendedor en la entidad seleccionada.',
            ])
        @endif
    @endif

    {{-- ========== 5 Usuarios ========== --}}
    @if($logTab === 'usuarios')
        @if(!$selectedLogUser)
            <div class="form-card bs mt-3">
                <h5 class="mb-0 mt-1">Selección usuario</h5>
                <small><i>Usuarios del sistema sin cuenta panel de administración/entidad ni super administrador (primeros 500 por nombre).</i></small>

                <form method="get" action="{{ route('configuration.index') }}" class="mt-3 mb-3">
                    <input type="hidden" name="section" value="logs-actividad">
                    <input type="hidden" name="log_tab" value="usuarios">
                    <div class="row g-2">
                        <div class="col-md-4 ms-auto">
                            <label class="form-label small">Busqueda</label>
                            <div class="d-flex gap-1">
                                <input type="search" name="busqueda_usu" class="form-control form-control-sm" placeholder="Nombre o email" value="{{ request('busqueda_usu') }}">
                                <button type="submit" class="btn btn-sm btn-light"><i class="fe-search"></i></button>
                            </div>
                        </div>
                    </div>
                </form>

                @php
                    $uList = $logsUsersPicker;
                    if (request()->filled('busqueda_usu')) {
                        $s = request('busqueda_usu');
                        $uList = $uList->filter(function ($u) use ($s) {
                            return str_contains(strtolower($u->name.' '.$u->email), strtolower($s));
                        });
                    }
                @endphp

                <div class="table-responsive">
                    <table class="table table-striped table-centered nowrap w-100 mb-0" id="logs-tabla-usuarios">
                        <thead class="table-light">
                            <tr>
                                <th>Orden ID</th>
                                <th>Usuario</th>
                                <th>Email</th>
                                <th>Teléfono</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($uList as $u)
                                <tr class="logs-select-row" style="cursor:pointer;" data-select-id="{{ $u->id }}" data-select-type="usuario">
                                    <td>#US{{ str_pad($u->id, 4, '0', STR_PAD_LEFT) }}</td>
                                    <td>{{ $u->name }}</td>
                                    <td>{{ $u->email }}</td>
                                    <td>{{ $u->phone ?? '—' }}</td>
                                    <td>
                                        @if($u->status)
                                            <span class="badge bg-success">Activo</span>
                                        @else
                                            <span class="badge bg-secondary">Inactivo</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted">No hay usuarios que coincidan.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-end mt-3 pt-2 border-top">
                    <input type="hidden" id="logs-selected-user-id" value="">
                    <button type="button" class="btn btn-md btn-light" disabled id="logs-btn-user-next" style="border-radius: 30px; min-width: 160px; background-color: #e78307; color: #333; padding: 8px 16px; font-weight: bolder;">
                        Seleccionar <i class="ri-arrow-right-line ms-1"></i>
                    </button>
                </div>
            </div>
        @else
            <div class="d-flex flex-wrap gap-2 mt-3 mb-2 align-items-center">
                <div class="logs-context-card d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center" style="width:44px;height:44px;"><i class="ri-user-line text-muted"></i></div>
                    <div>
                        <div class="fw-semibold">{{ $selectedLogUser->name }}</div>
                        <small class="text-muted">{{ $selectedLogUser->email }}</small>
                    </div>
                </div>
                <a href="{{ $url(['log_tab' => 'usuarios']) }}" class="btn btn-sm btn-outline-secondary">Cambiar usuario</a>
            </div>
            @include('configuration.sections.logs-actividad-activity', [
                'activityTitle' => 'Actividad usuario',
                'activitySubtitle' => 'Aceptaciones legales y operaciones auditadas del usuario.',
            ])
        @endif
    @endif
</div>
