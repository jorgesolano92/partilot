<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title>Registro comprador | PARTILOT</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="{{ url('/') }}/logo.svg">
    <link href="{{ url('default') }}/assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="{{ url('default') }}/assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <style>
        .group-login {
            border: 2px solid silver;
            padding: 5px 0;
            border-radius: 30px;
            background: #fff;
            display: flex;
            align-items: center;
        }
        .group-login input { border: none !important; box-shadow: none !important; background: transparent !important; }
        .group-login input[readonly] { color: #6c757d; }
        .pending-summary {
            background: #fff8f0;
            border: 2px solid #F59200;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 8px;
        }
    </style>
</head>
<body class="auth-fluid-pages pb-0">
<div class="container py-4" style="max-width: 520px;">
    <div class="text-center mb-4">
        <img src="{{ url('/') }}/logo.svg" alt="PARTILOT" height="40">
        <h4 class="mt-3">Activa tus participaciones digitales</h4>
        <div class="pending-summary text-start">
            <p class="mb-2 fw-semibold" style="color:#212529;">
                Tienes <strong>{{ $pending->quantity }}</strong>
                {{ $pending->quantity === 1 ? 'participación digital pendiente' : 'participaciones digitales pendientes' }}
                de asignar a tu cuenta.
            </p>
            <p class="mb-1 small text-muted">
                <strong>Entidad:</strong> {{ $pending->entity->name ?? '—' }}
            </p>
            <p class="mb-1 small text-muted">
                <strong>Sorteo:</strong> {{ $pending->lottery->name ?? '—' }}
            </p>
            <p class="mb-0 small text-muted">
                <strong>Válido para vincular hasta:</strong> {{ $pending->valid_until->format('d/m/Y') }}
                ({{ config('digital_sale.wallet_validity_months_after_draw', 3) }} meses después del sorteo)
            </p>
        </div>
        <p class="text-muted small mb-0">
            Completa el registro con el formulario. Al crear tu cuenta, las participaciones se vincularán automáticamente.
        </p>
    </div>
    <div class="card">
        <div class="card-body p-4">
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif
            <form method="post" action="{{ route('digital-buyer.register.store', ['token' => $token]) }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <div class="group-login">
                        @if($pending->email)
                            <input type="email" class="form-control" value="{{ $pending->email }}" readonly>
                        @else
                            <input type="email" name="email" class="form-control" value="{{ old('email') }}" required maxlength="255">
                        @endif
                    </div>
                    @error('email')<p class="small text-danger mt-1 mb-0">{{ $message }}</p>@enderror
                    @if($pending->email)
                        <p class="small text-muted mt-1 mb-0">Debe coincidir con el correo de la reserva de venta.</p>
                    @endif
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nombre *</label>
                        <div class="group-login"><input type="text" name="name" class="form-control" required value="{{ old('name') }}"></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Primer apellido</label>
                        <div class="group-login"><input type="text" name="last_name" class="form-control" value="{{ old('last_name') }}"></div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Teléfono móvil <span class="text-muted fw-normal">(opcional)</span></label>
                    <div class="group-login"><input type="tel" name="phone" class="form-control" value="{{ old('phone') }}" placeholder="600000000"></div>
                    @if(config('sms.enabled'))
                        <p class="small text-muted mt-1">Si indicas teléfono, te enviaremos un SMS para confirmarlo.</p>
                        <div class="input-group mt-2">
                            <input type="text" name="sms_code" class="form-control" placeholder="Código SMS ({{ config('sms.code_length', 6) }} dígitos)" maxlength="{{ config('sms.code_length', 6) }}" inputmode="numeric" pattern="[0-9]*" value="{{ old('sms_code') }}">
                            <button type="button" class="btn btn-outline-secondary" id="btn-send-sms">Enviar código</button>
                        </div>
                        <p class="small text-muted mt-1" id="sms-cooldown-hint" style="display:none;"></p>
                    @endif
                </div>
                <div class="mb-3">
                    <label class="form-label">Fecha de nacimiento *</label>
                    <div class="group-login"><input type="date" name="birthday" class="form-control" required value="{{ old('birthday') }}"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Contraseña *</label>
                    <div class="group-login"><input type="password" name="password" class="form-control" required minlength="6"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Repetir contraseña *</label>
                    <div class="group-login"><input type="password" name="password_confirmation" class="form-control" required minlength="6"></div>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="aceptar_condiciones" value="1" id="cond" required {{ old('aceptar_condiciones') ? 'checked' : '' }}>
                    <label class="form-check-label" for="cond">Acepto las condiciones de uso</label>
                </div>
                <button type="submit" class="btn w-100 text-white" style="background:#F59200;border-radius:30px;font-weight:bold;">Crear cuenta y recibir participaciones</button>
            </form>
        </div>
    </div>
</div>
@if(config('sms.enabled'))
<script>
(function () {
    const btn = document.getElementById('btn-send-sms');
    const hint = document.getElementById('sms-cooldown-hint');
    const cooldownSec = {{ (int) config('sms.resend_cooldown_seconds', 60) }};
    let timer = null;
    function startCooldown() {
        let left = cooldownSec;
        btn.disabled = true;
        hint.style.display = 'block';
        hint.textContent = 'Puedes reenviar en ' + left + ' s';
        timer = setInterval(function () {
            left--;
            if (left <= 0) {
                clearInterval(timer);
                btn.disabled = false;
                hint.style.display = 'none';
                btn.textContent = 'Enviar código';
            } else {
                hint.textContent = 'Puedes reenviar en ' + left + ' s';
            }
        }, 1000);
    }
    btn?.addEventListener('click', function () {
        const phone = document.querySelector('input[name=phone]')?.value;
        if (!phone) { alert('Introduce el teléfono primero.'); return; }
        btn.disabled = true;
        btn.textContent = 'Enviando…';
        fetch('{{ route('digital-buyer.sms-code') }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
            body: JSON.stringify({ phone })
        }).then(r => r.json()).then(d => {
            alert(d.message || (d.success ? 'Código enviado. Revisa tu móvil.' : 'Error'));
            if (d.success) startCooldown();
            else { btn.disabled = false; btn.textContent = 'Enviar código'; }
        }).catch(() => {
            alert('Error de red');
            btn.disabled = false;
            btn.textContent = 'Enviar código';
        });
    });
})();
</script>
@endif
</body>
</html>
