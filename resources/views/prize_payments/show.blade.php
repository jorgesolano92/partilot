@extends('layouts.layout')

@section('title', 'Gestionar cobro de premios')

@section('content')
@php
    $defaultBlocked = \App\Models\EntityLotteryPrizeSetting::DEFAULT_BLOCKED_MESSAGE;
    $defaultUnlocked = \App\Models\EntityLotteryPrizeSetting::DEFAULT_UNLOCKED_MESSAGE;
@endphp
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('prize-payments.index') }}">Cobro de premios</a></li>
                        <li class="breadcrumb-item active">Gestionar</li>
                    </ol>
                </div>
                <h4 class="page-title">Cobro de premios — {{ $setting->entity->name ?? 'Entidad' }}</h4>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="row">
        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-body">
                    <h5 class="card-title">Resumen</h5>
                    <dl class="row mb-0 small">
                        <dt class="col-sm-5">Entidad</dt>
                        <dd class="col-sm-7">{{ $setting->entity->name ?? '—' }}</dd>
                        <dt class="col-sm-5">Administración</dt>
                        <dd class="col-sm-7">{{ $setting->entity->administration->name ?? '—' }}</dd>
                        <dt class="col-sm-5">Sorteo</dt>
                        <dd class="col-sm-7">{{ $setting->lottery->name ?? '—' }}</dd>
                        <dt class="col-sm-5">Modalidad</dt>
                        <dd class="col-sm-7">{{ $setting->modeLabel() }}</dd>
                        <dt class="col-sm-5">Digitales vendidas</dt>
                        <dd class="col-sm-7">{{ $setting->has_sold_digital_participations ? 'Sí' : 'No' }}</dd>
                        <dt class="col-sm-5">Importe requerido</dt>
                        <dd class="col-sm-7">{{ number_format($setting->funds_required_amount, 2, ',', '.') }}€</dd>
                        <dt class="col-sm-5">Ingreso confirmado</dt>
                        <dd class="col-sm-7">{{ number_format($setting->funds_deposited_amount, 2, ',', '.') }}€</dd>
                        <dt class="col-sm-5">Estado fondos</dt>
                        <dd class="col-sm-7">{{ $setting->fundsStatusLabel() }}</dd>
                        <dt class="col-sm-5">Contrato</dt>
                        <dd class="col-sm-7">
                            @if($setting->contract_status === 'pending')
                                Pendiente
                                @if($setting->contract_sent_at)
                                    <div class="small text-muted">Enviado {{ $setting->contract_sent_at->format('d/m/Y H:i') }}</div>
                                @endif
                            @elseif($setting->contract_status === 'signed')
                                Firmado {{ $setting->contract_signed_at?->format('d/m/Y H:i') }}
                                @if($setting->contract_signer_name)
                                    <div class="small text-muted">Por {{ $setting->contract_signer_name }}</div>
                                @endif
                            @else No requerido @endif
                        </dd>
                        <dt class="col-sm-5">Cobro</dt>
                        <dd class="col-sm-7">{{ $setting->activationSummary() }}</dd>
                    </dl>
                </div>
            </div>

            @if($setting->funds_status === 'pending')
                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Confirmar ingreso de fondos</h5>
                        <form method="POST" action="{{ route('prize-payments.confirm-funds', $setting->id) }}" onsubmit="return confirm('¿Confirmar que la entidad ha ingresado los fondos?');">
                            @csrf
                            <div class="mb-2">
                                <label class="form-label">Importe ingresado (€)</label>
                                <input type="number" step="0.01" min="0" name="funds_deposited_amount" class="form-control" value="{{ $setting->funds_required_amount }}">
                            </div>
                            <button type="submit" class="btn btn-success w-100">Confirmar ingreso</button>
                        </form>
                    </div>
                </div>
            @endif

            @if($setting->contract_status === 'pending')
                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Contrato</h5>
                        <p class="small text-muted">Envía el enlace de firma a la entidad o regístralo manualmente si ya lo firmó en papel.</p>
                        <form method="POST" action="{{ route('prize-payments.send-contract', $setting->id) }}" class="mb-2" onsubmit="return confirm('¿Enviar contrato por email a la entidad?');">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary w-100">Enviar contrato por email</button>
                        </form>
                        <form method="POST" action="{{ route('prize-payments.mark-contract-signed', $setting->id) }}" onsubmit="return confirm('¿Marcar contrato como firmado manualmente?');">
                            @csrf
                            <button type="submit" class="btn btn-outline-primary w-100">Marcar firmado (manual)</button>
                        </form>
                    </div>
                </div>
            @endif

            <div class="card mb-3 border-danger">
                <div class="card-body">
                    <h5 class="card-title text-danger">Override superadmin</h5>
                    <form method="POST" action="{{ route('prize-payments.change-mode', $setting->id) }}" class="mb-3">
                        @csrf
                        @method('PUT')
                        <label class="form-label">Cambiar modalidad</label>
                        <div class="input-group">
                            <select name="prize_payment_mode" class="form-select" id="prize-mode-select">
                                <option value="online" data-online-payer="partilot" @selected($setting->prize_payment_mode === 'online' && $setting->isOnlinePayerPartilot())>Online (PARTILOT)</option>
                                <option value="online" data-online-payer="entity" @selected($setting->prize_payment_mode === 'online' && $setting->isOnlinePayerEntity())>Online (entidad)</option>
                                <option value="presencial" @selected($setting->prize_payment_mode === 'presencial')>Presencial</option>
                            </select>
                            <input type="hidden" name="online_payer" id="prize-online-payer" value="{{ $setting->online_payer ?? 'partilot' }}">
                            <button type="submit" class="btn btn-outline-danger" onclick="return confirm('¿Cambiar modalidad? Se desactivarán los cobros hasta reactivarlos.');">Aplicar</button>
                        </div>
                        <script>
                            document.getElementById('prize-mode-select')?.addEventListener('change', function () {
                                const opt = this.options[this.selectedIndex];
                                const payer = opt.getAttribute('data-online-payer');
                                document.getElementById('prize-online-payer').value = payer || 'partilot';
                            });
                        </script>
                    </form>
                    <form method="POST" action="{{ route('prize-payments.block-payments', $setting->id) }}" onsubmit="return confirm('¿Bloquear todos los cobros para esta entidad y sorteo?');">
                        @csrf
                        <button type="submit" class="btn btn-danger w-100">Bloquear cobros</button>
                    </form>
                </div>
            </div>

            @if($setting->isModeOnline() && $setting->isOnlinePayerPartilot() && ! $setting->online_payments_enabled)
                <div class="card mb-3 border-warning">
                    <div class="card-body">
                        <h5 class="card-title">Activar cobro online</h5>
                        <form method="POST" action="{{ route('prize-payments.activate-online', $setting->id) }}" onsubmit="return confirm('¿Activar el cobro online para esta entidad? Se notificará a los usuarios.');">
                            @csrf
                            <div class="mb-2">
                                <label class="form-label">Mensaje al usuario (desbloqueado)</label>
                                <textarea name="unlocked_user_message" class="form-control" rows="3">{{ old('unlocked_user_message', $setting->unlocked_user_message ?: $defaultUnlocked) }}</textarea>
                                <small class="text-muted">Usa <code>{amount}</code> para el importe del premio.</small>
                            </div>
                            <button type="submit" class="btn btn-warning text-dark w-100" @disabled($setting->funds_status === 'pending' || ! $setting->contractIsSatisfied())>
                                Activar cobro online
                            </button>
                            @if($setting->funds_status === 'pending')
                                <p class="small text-danger mt-2 mb-0">Confirma el ingreso de fondos antes de activar.</p>
                            @elseif(! $setting->contractIsSatisfied())
                                <p class="small text-danger mt-2 mb-0">Registra la firma del contrato antes de activar.</p>
                            @endif
                        </form>
                    </div>
                </div>
            @endif

            @if($setting->isModePresencial() && ! $setting->presencial_payments_enabled)
                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Activar pago presencial (app gestor)</h5>
                        <form method="POST" action="{{ route('prize-payments.activate-presencial', $setting->id) }}" onsubmit="return confirm('¿Activar el pago presencial en app gestor?');">
                            @csrf
                            <button type="submit" class="btn btn-warning text-dark w-100" @disabled($setting->has_sold_digital_participations && ($setting->funds_status === 'pending' || ! $setting->contractIsSatisfied()))>
                                Activar pago presencial
                            </button>
                        </form>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-body">
                    <h5 class="card-title">Mensajes al usuario (solo PARTILOT)</h5>
                    <form method="POST" action="{{ route('prize-payments.update-messages', $setting->id) }}">
                        @csrf
                        @method('PUT')
                        <div class="mb-3">
                            <label class="form-label">Mensaje bloqueado (online)</label>
                            <textarea name="blocked_user_message" class="form-control" rows="3">{{ old('blocked_user_message', $setting->blocked_user_message ?: $defaultBlocked) }}</textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mensaje desbloqueado</label>
                            <textarea name="unlocked_user_message" class="form-control" rows="2">{{ old('unlocked_user_message', $setting->unlocked_user_message ?: $defaultUnlocked) }}</textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Guardar mensajes</button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Historial de auditoría</h5>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Evento</th>
                                    <th>Usuario</th>
                                    <th>Detalle</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($setting->activationLogs as $log)
                                    <tr>
                                        <td class="text-nowrap">{{ $log->created_at?->format('d/m/Y H:i') }}</td>
                                        <td>{{ $log->eventLabel() }}</td>
                                        <td class="text-nowrap">{{ $log->user?->name ?? '—' }}</td>
                                        <td class="small text-muted">{{ $log->payloadSummary() ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-muted">Sin eventos registrados.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
