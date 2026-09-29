@php
    $activityRows = $activityRows ?? ($logActivityRows ?? []);
@endphp

<div class="form-card bs mt-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
        <div>
            <h5 class="mb-0">{{ $activityTitle ?? 'Actividad' }}</h5>
            <small class="text-muted">{{ $activitySubtitle ?? 'Eventos de auditoría registrados en el sistema.' }}</small>
        </div>
    </div>

    @if(empty($activityRows))
        <div class="text-center py-4 text-muted">
            <p class="mb-0">No hay actividad registrada para este ámbito.</p>
            <small>Solo se muestran aceptaciones legales y cambios de configuración auditados (no pushes de prueba).</small>
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-hover table-centered mb-0 w-100">
                <thead class="table-light">
                    <tr>
                        <th>Fecha</th>
                        <th>Hora</th>
                        <th>Usuario</th>
                        <th>Rol</th>
                        <th>Acción</th>
                        <th>Objeto</th>
                        <th>Resultado</th>
                        <th>IP</th>
                        <th>Dispositivo</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($activityRows as $r)
                        <tr>
                            <td>{{ $r['fecha'] ?? '—' }}</td>
                            <td>{{ $r['hora'] ?? '—' }}</td>
                            <td>{{ $r['usuario'] ?? '—' }}</td>
                            <td>{{ $r['rol'] ?? '—' }}</td>
                            <td>{{ $r['accion'] ?? '—' }}</td>
                            <td>{{ $r['objeto'] ?? '—' }}</td>
                            <td>{{ $r['detalle'] ?? '—' }}</td>
                            <td>{{ $r['ip'] ?? '—' }}</td>
                            <td>{{ $r['dispositivo'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
