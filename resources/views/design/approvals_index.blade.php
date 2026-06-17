@extends('layouts.layout')

@section('title', 'Aprobaciones de diseño')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('design.index') }}">Diseño e Impresión</a></li>
                        <li class="breadcrumb-item active">Aprobaciones</li>
                    </ol>
                </div>
                <h4 class="page-title">Diseños pendientes de aprobación</h4>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card">
        <div class="card-body">
            @if($designs->isEmpty())
                <p class="text-muted mb-0">No hay diseños pendientes de aprobación.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <th>Diseño</th>
                                <th>Entidad</th>
                                <th>Set</th>
                                <th>Enviado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($designs as $design)
                                <tr>
                                    <td>#DS{{ str_pad($design->id, 5, '0', STR_PAD_LEFT) }}</td>
                                    <td>{{ $design->entity->name ?? '—' }}</td>
                                    <td>{{ $design->set->set_name ?? ('#'.$design->set_id) }}</td>
                                    <td>{{ optional($design->submitted_for_approval_at)->format('d/m/Y H:i') ?? '—' }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('design.approval.review', $design->id) }}" class="btn btn-sm btn-primary">
                                            Revisar
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
