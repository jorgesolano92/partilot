@extends('layouts.layout')

@section('title','Notificaciones')

@section('content')

<div class="container-fluid">

    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item active">Notificaciones</li>
                    </ol>
                </div>
                <h4 class="page-title">Notificaciones</h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="header-title mb-0">Mis avisos</h4>
                        @if($notifications->whereNull('read_at')->count() > 0)
                            <button type="button" class="btn btn-sm btn-outline-dark" id="inbox-mark-all" style="border-radius: 20px;">Marcar todas como leídas</button>
                        @endif
                    </div>

                    @if($notifications->isEmpty())
                        <div class="empty-tables">
                            <div>
                                <img src="{{url('icons_/participaciones.svg')}}" alt="" width="80px" style="margin-top: 10px;">
                            </div>
                            <h3 class="mb-0">No tienes notificaciones</h3>
                            <small>Aquí aparecerán los avisos de tu entidad o administración.</small>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-striped w-100">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Título</th>
                                        <th>Mensaje</th>
                                        <th>Entidad</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($notifications as $notification)
                                        <tr data-notification-id="{{ $notification->id }}">
                                            <td style="white-space: nowrap;">{{ $notification->created_at?->format('d/m/Y H:i') }}</td>
                                            <td><strong>{{ $notification->title }}</strong></td>
                                            <td>{{ $notification->message }}</td>
                                            <td>{{ $notification->entity?->name ?? '—' }}</td>
                                            <td>
                                                @if($notification->read_at)
                                                    <span class="badge bg-secondary">Leída</span>
                                                @else
                                                    <button type="button" class="btn btn-sm btn-link p-0 inbox-mark-read" data-id="{{ $notification->id }}">Marcar leída</button>
                                                @endif
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
    </div>

</div>

@endsection

@section('scripts')
<script>
(function () {
    var csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    function post(url) {
        return fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        });
    }

    document.querySelectorAll('.inbox-mark-read').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var url = @json(route('notifications.panel-inbox-read', ['id' => '__ID__'])).replace('__ID__', btn.dataset.id);
            btn.disabled = true;
            post(url).then(function (r) {
                if (r.ok) {
                    btn.outerHTML = '<span class="badge bg-secondary">Leída</span>';
                } else {
                    btn.disabled = false;
                }
            });
        });
    });

    var markAll = document.getElementById('inbox-mark-all');
    if (markAll) {
        markAll.addEventListener('click', function () {
            markAll.disabled = true;
            post(@json(route('notifications.panel-inbox-read-all'))).then(function (r) {
                if (r.ok) {
                    window.location.reload();
                } else {
                    markAll.disabled = false;
                }
            });
        });
    }
})();
</script>
@endsection
