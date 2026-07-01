<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title>{{ $invitation['screen_title'] ?? 'Invitación' }} | PARTILOT</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="{{ url('/') }}/logo.svg">
    <link href="{{ url('default') }}/assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <style>
        .role-card { max-width: 640px; margin: 0 auto; }
        .btn-role { min-height: 44px; border-radius: 999px; font-weight: 600; }
        .btn-role-secondary { background: #6c757d; border: none; color: #fff; }
        .btn-role-primary { background: #198754; border: none; color: #fff; }
    </style>
</head>
<body class="bg-light py-5">
<div class="role-card card shadow-sm">
    <div class="card-body p-4">
        <div class="text-center mb-3">
            <img src="{{ url('/') }}/logo.svg" alt="PARTILOT" height="36">
            <h4 class="mt-3">{{ $invitation['screen_title'] ?? 'Invitación pendiente' }}</h4>
        </div>

        @include('partials.role-invitation-legal', ['invitation' => $invitation])

        <form method="post" action="{{ $acceptUrl }}" class="mb-2">
            @csrf
            <input type="hidden" name="action" value="accept">
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="role_terms" name="role_terms" value="1" required>
                <label class="form-check-label" for="role_terms">
                    He leído las responsabilidades de este rol y deseo aceptar la invitación.
                </label>
            </div>
            <button type="submit" class="btn btn-role btn-role-primary w-100">{{ $invitation['accept_label'] ?? 'Aceptar' }}</button>
        </form>

        <form method="post" action="{{ $rejectUrl }}">
            @csrf
            <input type="hidden" name="action" value="reject">
            <button type="submit" class="btn btn-role btn-role-secondary w-100">{{ $invitation['reject_label'] ?? 'Rechazar' }}</button>
        </form>
    </div>
</div>
</body>
</html>
