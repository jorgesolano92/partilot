<div class="form-card bs">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if((int) $step === 1 || !$entityId)
        @if(empty($configurationEntityScoped))
        <h4 class="mb-0 mt-1">Selección Entidad</h4>
        <small><i>El listado incluye las donaciones cuya primera participación asociada corresponde a la entidad elegida.</i></small>

        <form method="get" action="{{ url('/configuration') }}" id="form-filtros-cr">
            <input type="hidden" name="section" value="codigos-recarga">
            <input type="hidden" name="step" value="1">
            <div class="row mt-3 mb-3 g-2">
                <div class="col-md-3">
                    <label class="form-label small">Provincia</label>
                    <select name="provincia" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">Todas</option>
                        @foreach($provincias as $p)
                            <option value="{{ $p }}" {{ request('provincia') == $p ? 'selected' : '' }}>{{ $p }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Localidad</label>
                    <select name="localidad" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">Todas</option>
                        @foreach($localidades as $c)
                            <option value="{{ $c }}" {{ request('localidad') == $c ? 'selected' : '' }}>{{ $c }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Búsqueda</label>
                    <div class="d-flex gap-1">
                        <input type="search" name="busqueda" class="form-control form-control-sm" placeholder="Búsqueda" value="{{ request('busqueda') }}">
                        <button type="submit" class="btn btn-sm btn-light"><i class="fe-search"></i></button>
                    </div>
                </div>
            </div>
        </form>

        <div class="table-responsive" style="min-height: 250px;">
            <table id="tabla-cr-entidades" class="table table-striped table-centered nowrap w-100">
                <thead class="table-light">
                    <tr>
                        <th>Orden ID</th>
                        <th>Entidad</th>
                        <th>Provincia</th>
                        <th>Localidad</th>
                        <th>Administración</th>
                        <th>Status</th>
                        <th class="d-none">Seleccionar</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($entities as $ent)
                        @php $isActive = $ent->status == 1; @endphp
                        <tr class="selectable-row {{ $isActive ? '' : 'entity-inactive' }}" style="cursor: {{ $isActive ? 'pointer' : 'not-allowed' }};" data-entity-id="{{ $ent->id }}" data-entity-status="{{ $ent->status }}">
                            <td>#EN{{ str_pad($ent->id, 4, '0', STR_PAD_LEFT) }}</td>
                            <td>{{ $ent->name }}</td>
                            <td>{{ $ent->province ?? '—' }}</td>
                            <td>{{ $ent->city ?? '—' }}</td>
                            <td>{{ $ent->administration->name ?? '—' }}</td>
                            <td>
                                <span class="badge bg-{{ $ent->status == 1 ? 'success' : ($ent->status == 0 ? 'danger' : 'secondary') }}">
                                    {{ $ent->status_text }}
                                </span>
                            </td>
                            <td class="d-none">
                                <label class="mb-0">
                                    <input type="radio" name="entity_id_cr" value="{{ $ent->id }}" class="form-check-input" {{ $isActive ? '' : 'disabled' }}> Seleccionar
                                </label>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted">No hay entidades que coincidan con el filtro.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="row mt-3">
            <div class="col-12 text-end">
                <input type="hidden" id="selected-entity-id-cr" value="">
                <button type="button" id="btn-siguiente-cr" style="border-radius: 30px; min-width: 160px; background-color: #e78307; color: #333; padding: 8px 16px; font-weight: bolder;" class="btn btn-md btn-light" disabled>
                    Siguiente <i class="ri-arrow-right-line ms-1"></i>
                </button>
            </div>
        </div>
        @endif
    @endif

    @if((int) $step === 2 && $entity)
        <div class="d-flex align-items-center gap-3 mb-3">
            <div class="rounded-circle bg-light d-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                <i class="fe-gift font-24 text-muted"></i>
            </div>
            <div>
                <h5 class="mb-0">{{ $entity->name }}</h5>
                <small class="text-muted">{{ $entity->province ?? $entity->city ?? '—' }}</small>
            </div>
        </div>

        <div class="d-flex flex-wrap justify-content-between align-items-end gap-2">
            <div>
                <h4 class="mb-0 mt-1">Donaciones y códigos de recarga</h4>
                <small class="text-muted">Donaciones a esta entidad.</small>
            </div>
            <form method="GET" action="{{ route('codigos-recarga.modelo-182', $entity->id) }}" class="d-flex align-items-center gap-2">
                <select name="year" class="form-select form-select-sm" style="border-radius: 30px; width: auto;">
                    @for($y = now()->year; $y >= now()->year - 4; $y--)
                        <option value="{{ $y }}" @selected($y === now()->year - 1)>{{ $y }}</option>
                    @endfor
                </select>
                <button type="submit" class="btn btn-sm btn-dark" style="border-radius: 30px;">
                    <i class="ri-download-2-line me-1"></i> Exportar Modelo 182 (CSV)
                </button>
            </form>
        </div>

        <div class="table-responsive mt-3">
            <table class="table table-hover table-centered mb-0" id="tabla-cr-donaciones">
                <thead class="table-light">
                    <tr>
                        <th>Fecha</th>
                        <th>Donante</th>
                        <th>NIF</th>
                        <th class="text-end">Importe donación</th>
                        <th class="text-end">Importe código</th>
                        <th>Código recarga</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($participationDonations as $d)
                        @php
                            $nombreCompleto = trim(($d->nombre ?? '').' '.($d->apellidos ?? ''));
                            if ($d->anonima && $nombreCompleto === '') {
                                $mostrarNombre = 'Anónima';
                            } elseif ($nombreCompleto !== '') {
                                $mostrarNombre = $nombreCompleto;
                            } else {
                                $mostrarNombre = '—';
                            }
                        @endphp
                        <tr>
                            <td data-order="{{ $d->donated_at?->timestamp ?? 0 }}">{{ $d->donated_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td>{{ $mostrarNombre }}</td>
                            <td>{{ $d->nif ? $d->nif : '—' }}</td>
                            <td class="text-end">{{ number_format((float) $d->importe_donacion, 2, ',', '.') }} €</td>
                            <td class="text-end">{{ number_format((float) $d->importe_codigo, 2, ',', '.') }} €</td>
                            <td><code class="user-select-all">{{ $d->codigo_recarga ?? '—' }}</code></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">No hay donaciones registradas para esta entidad con este criterio.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-end mt-3 pt-3 border-top">
            @if(empty($configurationEntityScoped))
            <a href="{{ url('/configuration?section=codigos-recarga&step=1') }}" class="btn btn-md btn-dark" style="border-radius: 30px;">
                <i class="ri-arrow-left-line me-1"></i> Cambiar entidad
            </a>
            @endif
        </div>
    @endif
</div>
