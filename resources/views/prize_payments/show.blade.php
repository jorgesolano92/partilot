@extends('layouts.layout')

@section('title', 'Gestionar cobro de premios')

@section('content')
@php
    $defaultBlocked = \App\Models\EntityLotteryPrizeSetting::DEFAULT_BLOCKED_MESSAGE;
    $defaultUnlocked = \App\Models\EntityLotteryPrizeSetting::DEFAULT_UNLOCKED_MESSAGE;
@endphp

<div class="container-fluid prize-payments-show">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('prize-payments.index') }}">Cobro de premios</a></li>
                        <li class="breadcrumb-item active">Gestionar</li>
                    </ol>
                </div>
                <h4 class="page-title">Cobro de premios</h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

                    <h4 class="header-title mb-0">{{ $setting->entity->name ?? 'Entidad' }} — {{ $setting->lottery->name ?? 'Sorteo' }}</h4>
                    <small class="text-muted"><i>{{ $setting->entity->administration->name ?? 'Administración' }} · {{ $setting->modeLabel() }}</i></small>

                    @if(session('success'))
                        <div class="alert alert-success mt-3 mb-0 py-2">{{ session('success') }}</div>
                    @endif
                    @if(session('error'))
                        <div class="alert alert-danger mt-3 mb-0 py-2">{{ session('error') }}</div>
                    @endif

                    <br>

                    <div class="row">
                        {{-- Columna izquierda: resumen y acciones --}}
                        <div class="col-md-5" style="position: relative;">

                            <div class="form-card bs mb-3">
                                <h4 class="mb-0 mt-1">Estado</h4>
                                <small><i>Resumen de la activación</i></small>
                                <ul class="list-unstyled small mb-0 mt-2">
                                    <li class="mb-1"><span class="text-muted">Importe requerido:</span> <strong>{{ number_format($setting->funds_required_amount, 2, ',', '.') }}€</strong></li>
                                    <li class="mb-1"><span class="text-muted">Ingreso confirmado:</span> <strong>{{ number_format($setting->funds_deposited_amount, 2, ',', '.') }}€</strong></li>
                                    <li class="mb-1"><span class="text-muted">Fondos:</span> <strong>{{ $setting->fundsStatusLabel() }}</strong></li>
                                    <li class="mb-1"><span class="text-muted">Contrato:</span>
                                        <strong>
                                            @if($setting->contract_status === 'pending') Pendiente
                                            @elseif($setting->contract_status === 'signed') Firmado
                                            @else No requerido @endif
                                        </strong>
                                    </li>
                                    <li class="mb-1"><span class="text-muted">Cobro:</span> <strong>{{ $setting->activationSummary() }}</strong></li>
                                    <li><span class="text-muted">Cobro online (digitales/digitalizadas):</span> <strong>{{ $setting->has_sold_digital_participations ? 'Sí' : 'No' }}</strong></li>
                                </ul>
                                @if($setting->contract_status === 'pending' && $setting->contract_sent_at)
                                    <p class="small text-muted mb-0 mt-2">Enviado {{ $setting->contract_sent_at->format('d/m/Y H:i') }}</p>
                                @elseif($setting->contract_status === 'signed')
                                    <p class="small text-muted mb-0 mt-2">
                                        {{ $setting->contract_signed_at?->format('d/m/Y H:i') }}
                                        @if($setting->contract_signer_name) · {{ $setting->contract_signer_name }} @endif
                                    </p>
                                @endif
                            </div>

                            @if($setting->funds_status === 'pending')
                                <div class="form-card mb-3">
                                    <h4 class="mb-0 mt-1">Ingreso de fondos</h4>
                                    <small><i>Confirma el ingreso de la entidad</i></small>
                                    <form method="POST" action="{{ route('prize-payments.confirm-funds', $setting->id) }}" class="mt-2" onsubmit="return confirm('¿Confirmar que la entidad ha ingresado los fondos?');">
                                        @csrf
                                        <div class="form-group mb-2">
                                            <label class="label-control">Importe ingresado (€)</label>
                                            <input type="number" step="0.01" min="0" name="funds_deposited_amount" class="form-control" value="{{ $setting->funds_required_amount }}" style="border-radius: 30px;">
                                        </div>
                                        <button type="submit" class="btn btn-success w-100" style="border-radius: 30px;">Confirmar ingreso</button>
                                    </form>
                                </div>
                            @endif

                            @if($setting->contract_status === 'pending')
                                <div class="form-card mb-3">
                                    <h4 class="mb-0 mt-1">Contrato</h4>
                                    <small><i>Firma digital o registro manual</i></small>
                                    <div class="d-grid gap-2 mt-2">
                                        <form method="POST" action="{{ route('prize-payments.send-contract', $setting->id) }}" onsubmit="return confirm('¿Enviar contrato por email a la entidad?');">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-secondary w-100" style="border-radius: 30px;">Enviar por email</button>
                                        </form>
                                        <form method="POST" action="{{ route('prize-payments.mark-contract-signed', $setting->id) }}" onsubmit="return confirm('¿Marcar contrato como firmado manualmente?');">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-primary w-100" style="border-radius: 30px;">Marcar firmado</button>
                                        </form>
                                    </div>
                                </div>
                            @endif

                            @if($setting->isModeOnline() && $setting->isOnlinePayerPartilot() && ! $setting->online_payments_enabled)
                                <div class="form-card mb-3">
                                    <h4 class="mb-0 mt-1">Activar online</h4>
                                    <small><i>Habilitar cobro en app usuario</i></small>
                                    <form method="POST" action="{{ route('prize-payments.activate-online', $setting->id) }}" class="mt-2" onsubmit="return confirm('¿Activar el cobro online para esta entidad? Se notificará a los usuarios.');">
                                        @csrf
                                        <div class="form-group mb-2">
                                            <label class="label-control">Mensaje desbloqueado</label>
                                            <textarea name="unlocked_user_message" class="form-control" rows="2" style="border-radius: 16px;">{{ old('unlocked_user_message', $setting->unlocked_user_message ?: $defaultUnlocked) }}</textarea>
                                        </div>
                                        <button type="submit" class="btn btn-warning text-dark w-100" style="border-radius: 30px;" @disabled($setting->funds_status === 'pending' || ! $setting->contractIsSatisfied())>
                                            Activar cobro online
                                        </button>
                                        @if($setting->funds_status === 'pending')
                                            <p class="small text-danger mt-2 mb-0">Confirma el ingreso antes de activar.</p>
                                        @elseif(! $setting->contractIsSatisfied())
                                            <p class="small text-danger mt-2 mb-0">Registra la firma del contrato antes de activar.</p>
                                        @endif
                                    </form>
                                </div>
                            @endif

                            @if($setting->isModePresencial() && ! $setting->presencial_payments_enabled)
                                <div class="form-card mb-3">
                                    <h4 class="mb-0 mt-1">Activar presencial</h4>
                                    <small><i>Pago en app gestor</i></small>
                                    @php
                                        $presencialBlockedByFunds = $setting->has_sold_digital_participations && $setting->funds_status === 'pending';
                                        $presencialBlockedByContract = $setting->has_sold_digital_participations && ! $setting->contractIsSatisfied();
                                    @endphp
                                    <form method="POST" action="{{ route('prize-payments.activate-presencial', $setting->id) }}" class="mt-2" onsubmit="return confirm('¿Activar el pago presencial en app gestor?');">
                                        @csrf
                                        <button type="submit" class="btn btn-warning text-dark w-100" style="border-radius: 30px;" @disabled($presencialBlockedByFunds || $presencialBlockedByContract)>
                                            Activar pago presencial
                                        </button>
                                    </form>
                                    @if($presencialBlockedByFunds)
                                        <p class="small text-danger mt-2 mb-0">Confirma el ingreso de fondos antes de activar.</p>
                                    @elseif($presencialBlockedByContract)
                                        <p class="small text-danger mt-2 mb-0">Registra la firma del contrato antes de activar.</p>
                                    @else
                                        <p class="small text-muted mt-2 mb-0">Habilita el cobro presencial en la app gestor de la entidad.</p>
                                    @endif
                                </div>
                            @endif

                            <div class="form-card mb-3" style="border: 1px solid #f5c2c7;">
                                <h4 class="mb-0 mt-1 text-danger">Override</h4>
                                <small><i>Solo superadministrador</i></small>
                                <form method="POST" action="{{ route('prize-payments.change-mode', $setting->id) }}" class="mt-2 mb-2">
                                    @csrf
                                    @method('PUT')
                                    <label class="label-control">Modalidad</label>
                                    <div class="input-group">
                                        <select name="prize_payment_mode" class="form-select" id="prize-mode-select" style="border-radius: 30px 0 0 30px;">
                                            <option value="online" data-online-payer="partilot" @selected($setting->prize_payment_mode === 'online' && $setting->isOnlinePayerPartilot())>Online (PARTILOT)</option>
                                            <option value="online" data-online-payer="entity" @selected($setting->prize_payment_mode === 'online' && $setting->isOnlinePayerEntity())>Online (entidad)</option>
                                            <option value="presencial" @selected($setting->prize_payment_mode === 'presencial')>Presencial</option>
                                        </select>
                                        <input type="hidden" name="online_payer" id="prize-online-payer" value="{{ $setting->online_payer ?? 'partilot' }}">
                                        <button type="submit" class="btn btn-outline-danger" style="border-radius: 0 30px 30px 0 !important;" onclick="return confirm('¿Cambiar modalidad? Se desactivarán los cobros hasta reactivarlos.');">Aplicar</button>
                                    </div>
                                </form>
                                <form method="POST" action="{{ route('prize-payments.block-payments', $setting->id) }}" onsubmit="return confirm('¿Bloquear todos los cobros para esta entidad y sorteo?');">
                                    @csrf
                                    <button type="submit" class="btn btn-danger w-100" style="border-radius: 30px;">Bloquear cobros</button>
                                </form>
                            </div>

                            <a href="{{ route('prize-payments.index') }}" style="border-radius: 30px; width: 200px; background-color: #333; color: #fff; padding: 8px; font-weight: bolder; position: relative; bottom: 16px;" class="btn btn-md btn-light mt-2">
                                <i style="top: 6px; left: 32%; font-size: 18px; position: absolute;" class="ri-arrow-left-circle-line"></i>
                                <span style="display: block; margin-left: 16px;">Atrás</span>
                            </a>
                        </div>

                        {{-- Columna derecha: mensajes e historial --}}
                        <div class="col-md-7">
                            <div class="form-card bs mb-3">
                                <h4 class="mb-0 mt-1">Mensajes al usuario</h4>
                                <small><i>Solo modalidad online gestionada por PARTILOT · usa <code>{amount}</code> para el importe</i></small>

                                <form method="POST" action="{{ route('prize-payments.update-messages', $setting->id) }}" class="mt-2 mb-0">
                                    @csrf
                                    @method('PUT')
                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <div class="form-group mb-0">
                                                <label class="label-control">Mensaje bloqueado</label>
                                                <textarea name="blocked_user_message" class="form-control" rows="4" style="border-radius: 16px;">{{ old('blocked_user_message', $setting->blocked_user_message ?: $defaultBlocked) }}</textarea>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group mb-0">
                                                <label class="label-control">Mensaje desbloqueado</label>
                                                <textarea name="unlocked_user_message" class="form-control" rows="4" style="border-radius: 16px;">{{ old('unlocked_user_message', $setting->unlocked_user_message ?: $defaultUnlocked) }}</textarea>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-end mt-2">
                                        <button type="submit" class="btn btn-primary" style="border-radius: 30px; min-width: 160px;">Guardar mensajes</button>
                                    </div>
                                </form>
                            </div>

                            <div class="form-card bs mb-0">
                                <h4 class="mb-0 mt-1">Historial de auditoría</h4>
                                <small><i>Registro de eventos de activación y cambios</i></small>

                                <div class="table-responsive mt-2">
                                    <table class="table table-sm table-hover mb-0">
                                        <thead class="table-light">
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
                                                <tr><td colspan="4" class="text-muted py-2">Sin eventos registrados.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.getElementById('prize-mode-select')?.addEventListener('change', function () {
        const opt = this.options[this.selectedIndex];
        const payer = opt.getAttribute('data-online-payer');
        document.getElementById('prize-online-payer').value = payer || 'partilot';
    });
</script>
@endsection
