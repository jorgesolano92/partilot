<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title>Contraseña provisional | PARTILOT</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="{{ url('/') }}/logo.svg">
    <link href="{{ url('default') }}/assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="{{ url('default') }}/assets/css/app.min.css" rel="stylesheet" type="text/css" />
</head>
<body class="auth-fluid-pages pb-0">
<div class="container py-5" style="max-width: 480px;">
    <div class="text-center mb-4">
        <img src="{{ url('/') }}/logo.svg" alt="PARTILOT" height="40">
        <h4 class="mt-3">Contraseña provisional</h4>
    </div>
    <div class="card">
        <div class="card-body p-4">
            <p class="text-muted small">Su cuenta usa una <strong>contraseña provisional</strong> enviada por correo. Por seguridad, debe establecer una nueva contraseña (mínimo 8 caracteres) antes de continuar.</p>
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <form method="post" action="{{ route('provisional-password.update') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Nueva contraseña</label>
                    <input type="password" name="password" id="new-password" class="form-control" required autocomplete="new-password" minlength="8">
                    <div class="progress mt-2" style="height: 6px;">
                        <div class="progress-bar" id="password-strength-bar" role="progressbar" style="width: 0%;"></div>
                    </div>
                    <small class="text-muted" id="password-strength-text">Combina mayúsculas, minúsculas, números y símbolos.</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirmar contraseña</label>
                    <input type="password" name="password_confirmation" class="form-control" required autocomplete="new-password" minlength="8">
                </div>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-dark" style="border-radius: 30px;">Cambiar contraseña</button>
                </div>
            </form>
            <form method="post" action="{{ route('logout') }}" class="mt-3 text-center">
                @csrf
                <button type="submit" class="btn btn-link text-muted small">Cerrar sesión</button>
            </form>
        </div>
    </div>
</div>
<script>
(function () {
    var input = document.getElementById('new-password');
    var bar = document.getElementById('password-strength-bar');
    var text = document.getElementById('password-strength-text');
    if (!input || !bar || !text) return;
    var levels = [
        { width: '0%', cls: '', label: 'Combina mayúsculas, minúsculas, números y símbolos.' },
        { width: '25%', cls: 'bg-danger', label: 'Muy débil' },
        { width: '50%', cls: 'bg-warning', label: 'Débil: añade números, mayúsculas o símbolos.' },
        { width: '75%', cls: 'bg-info', label: 'Aceptable' },
        { width: '100%', cls: 'bg-success', label: 'Robusta' }
    ];
    input.addEventListener('input', function () {
        var v = input.value;
        var score = 0;
        if (v.length > 0) {
            score = 1;
            var kinds = [/[a-z]/, /[A-Z]/, /\d/, /[^A-Za-z0-9]/].filter(function (re) { return re.test(v); }).length;
            if (v.length >= 8 && kinds >= 2) score = 2;
            if (v.length >= 8 && kinds >= 3) score = 3;
            if (v.length >= 12 && kinds === 4) score = 4;
        }
        var level = levels[score];
        bar.style.width = level.width;
        bar.className = 'progress-bar ' + level.cls;
        text.textContent = level.label;
    });
})();
</script>
</body>
</html>
