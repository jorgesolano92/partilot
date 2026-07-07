@if(!empty($configurationEntityScoped))
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">Logs Emails</h4>
        <span class="badge bg-light text-dark border">Solo su entidad</span>
    </div>

    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>Fecha</th>
                    <th>Destinatario</th>
                    <th>Plantilla</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                @forelse($entityEmailLogs ?? [] as $log)
                    <tr>
                        <td>{{ $log->sent_at?->format('d/m/Y H:i') ?? $log->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                        <td>{{ $log->recipient_email ?? '—' }}</td>
                        <td>
                            <span @if($log->messageTypeKey()) title="Key: {{ $log->messageTypeKey() }}" @endif>{{ $log->displayMessageType() }}</span>
                        </td>
                        <td><span class="badge bg-secondary">{{ $log->status ?? '—' }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-4">No hay emails registrados para su entidad.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@elseif(!empty($configurationAdministrationScoped))
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <h4 class="mb-0">Logs Emails</h4>
        @if(($settingsAdministrationEntities ?? collect())->count() > 0)
            <form method="GET" class="d-flex align-items-center gap-2">
                <input type="hidden" name="section" value="logs-emails">
                <label class="small text-muted mb-0">Entidad:</label>
                <select name="entity_id" class="form-select form-select-sm" style="min-width: 220px; border-radius: 20px;" onchange="this.form.submit()">
                    @foreach($settingsAdministrationEntities as $ent)
                        <option value="{{ $ent->id }}" {{ (int) ($settingsLogEntityId ?? 0) === (int) $ent->id ? 'selected' : '' }}>{{ $ent->name }}</option>
                    @endforeach
                </select>
            </form>
        @endif
    </div>

    @if(!($settingsLogEntityId ?? null))
        <div class="alert alert-info mb-0">
            <i class="fe-info me-2"></i>
            Seleccione una entidad para consultar los emails enviados.
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Fecha</th>
                        <th>Destinatario</th>
                        <th>Plantilla</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($entityEmailLogs ?? [] as $log)
                        <tr>
                            <td>{{ $log->sent_at?->format('d/m/Y H:i') ?? $log->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td>{{ $log->recipient_email ?? '—' }}</td>
                            <td>
                            <span @if($log->messageTypeKey()) title="Key: {{ $log->messageTypeKey() }}" @endif>{{ $log->displayMessageType() }}</span>
                        </td>
                            <td><span class="badge bg-secondary">{{ $log->status ?? '—' }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">No hay emails registrados para la entidad seleccionada.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
@else
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">Logs Emails</h4>
</div>

<div class="alert alert-info">
    <i class="fe-info me-2"></i>
    Esta sección está en desarrollo. Próximamente podrás ver los logs de emails.
</div>
@endif
