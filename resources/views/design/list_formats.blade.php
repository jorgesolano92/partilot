@extends('layouts.layout')

@section('title', 'Elegir diseño existente')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('design.index') }}">Diseño e Impresión</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('design.chooseType') }}">Elegir tipo</a></li>
                        <li class="breadcrumb-item active">Diseños de la entidad</li>
                    </ol>
                </div>
                <h4 class="page-title">Usar diseño existente</h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted mb-3">Selecciona un diseño ya creado para seguir editándolo. Se usará el número de reserva del set actual (reserva/set seleccionado).</p>

                    @if(!empty($currentSetLock['locked']))
                        <div class="alert alert-warning">
                            <strong>Set bloqueado.</strong> {{ $currentSetLock['message'] ?? '' }}
                            Puedes abrir un diseño para revisión o descarga; los guardados en el editor no estarán permitidos.
                        </div>
                    @endif

                    @if($designs->isEmpty())
                        <p class="text-muted">No tienes ningún diseño guardado para esta entidad. <a href="{{ route('design.showChooseType') }}">Volver</a> y elige "Ir a diseñar" para crear uno nuevo.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover table-striped">
                                <thead>
                                    <tr>
                                        <th style="width: 90px;">Vista previa</th>
                                        <th>Id</th>
                                        <th>Nombre diseño</th>
                                        <th>Nombre set</th>
                                        <th>Nº reserva</th>
                                        <th>Fecha creación diseño</th>
                                        <th>Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($designs as $d)
                                        <tr>
                                            <td class="align-middle text-center">
                                                @if($d->snapshot_path)
                                                    <img src="{{ asset('storage/' . $d->snapshot_path) }}" alt="Vista previa diseño {{ $d->id }}" class="rounded border" style="max-width: 72px; max-height: 48px; object-fit: contain; background: #f5f5f5;">
                                                @else
                                                    <span class="text-muted small">Sin imagen</span>
                                                @endif
                                            </td>
                                            <td>{{ $d->id }}</td>
                                            <td>{{ $d->design_name ?: '—' }}</td>
                                            <td>
                                                @if($d->set)
                                                    {{ $d->set->set_name ?: 'Set ' . $d->set->id }}
                                                    <span class="text-muted small">(Set {{ $d->set->id }})</span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($d->set && $d->set->reserve)
                                                    @php
                                                        $nums = is_array($d->set->reserve->reservation_numbers)
                                                            ? $d->set->reserve->reservation_numbers
                                                            : [$d->set->reserve->reservation_numbers ?? ''];
                                                    @endphp
                                                    {{ implode(', ', array_filter($nums)) ?: '—' }}
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>{{ $d->created_at ? $d->created_at->format('d/m/Y H:i') : '—' }}</td>
                                            <td>
                                                <form action="{{ route('design.format') }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <input type="hidden" name="set_id" value="{{ $set->id }}">
                                                    <input type="hidden" name="design_id" value="{{ $d->id }}">
                                                    <button type="submit" class="btn btn-sm btn-primary">
                                                        <i class="ri-check-line"></i> {{ !empty($currentSetLock['locked']) ? 'Abrir (revisión)' : 'Seleccionar' }}
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    <div class="mt-4">
                        <a href="{{ route('design.showChooseType') }}" class="btn btn-dark rounded-pill">
                            <i class="ri-arrow-left-line me-1"></i> Atrás
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
