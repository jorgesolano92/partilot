<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Diseño por invitación') | PARTILOT</title>
    <link rel="shortcut icon" href="{{ url('/') }}/logo.svg">
    <link href="{{ url('default') }}/assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="{{ url('default') }}/assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="{{ url('assets') }}/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="{{ url('assets/libs/pnotify/pnotify.css') }}" />
    <link rel="stylesheet" href="{{ url('assets/libs/pnotify/pnotify.buttons.css') }}" />
    <style>
        .ui-pnotify {
            opacity: 1 !important;
            z-index: 20000 !important;
        }
        .ui-pnotify.partilot-notify .ui-pnotify-container,
        .ui-pnotify .alert {
            opacity: 1 !important;
            background-image: none !important;
            box-shadow: 0 10px 22px rgba(0, 0, 0, 0.18) !important;
            border: 1px solid rgba(0, 0, 0, 0.08) !important;
            border-radius: 999px !important;
            width: min(460px, calc(100vw - 48px)) !important;
            min-width: 0 !important;
            max-width: calc(100vw - 48px) !important;
            padding: 14px 54px 14px 20px !important;
            font-size: 15px !important;
            font-weight: 600 !important;
        }
        .ui-pnotify.partilot-notify .ui-pnotify-closer,
        .ui-pnotify .ui-pnotify-closer {
            visibility: visible !important;
            opacity: 1 !important;
            right: 14px !important;
            top: 50% !important;
            transform: translateY(-50%) !important;
            border-radius: 999px !important;
            width: 24px !important;
            height: 24px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            cursor: pointer !important;
            border: 1px solid rgba(15, 118, 110, 0.35) !important;
            background: rgba(255, 255, 255, 0.65) !important;
        }
        .ui-pnotify.partilot-notify .ui-pnotify-closer > span,
        .ui-pnotify .ui-pnotify-closer > span {
            display: inline-block !important;
            width: 100% !important;
            height: 100% !important;
            position: relative !important;
            font-size: 0 !important;
            color: transparent !important;
        }
        .ui-pnotify.partilot-notify .ui-pnotify-closer > span::before,
        .ui-pnotify .ui-pnotify-closer > span::before {
            content: "×";
            font-size: 18px !important;
            line-height: 1 !important;
            font-weight: 700 !important;
            color: #0f766e !important;
            position: absolute !important;
            inset: 0 !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
        }
        .ui-pnotify .alert-success {
            background-color: #c7efe9 !important;
            color: #0f766e !important;
            border-color: #8fd7cd !important;
        }
        .ui-pnotify .alert-danger {
            background-color: #f8d7da !important;
            color: #842029 !important;
            border-color: #f1aeb5 !important;
        }
        .ui-pnotify .alert-warning,
        .ui-pnotify .alert-notice,
        .ui-pnotify .ui-pnotify-notice {
            background-color: #fff3cd !important;
            color: #7a5a00 !important;
            border-color: #ffe69c !important;
        }
    </style>
    <link rel="stylesheet" href="{{ url('assets/libs/jquery-ui/themes/base/jquery-ui.css') }}">
    <link rel="stylesheet" href="{{ url('style.css') }}">
    <link rel="stylesheet" href="https://cdn.ckeditor.com/ckeditor5/45.2.0/ckeditor5.css" crossorigin>
    @yield('styles')
</head>
<body class="auth-body">
    <div class="navbar navbar-expand-lg border-bottom bg-white">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center gap-2" href="#">
                <img src="{{ url('/') }}/logo.svg" alt="Partilot" height="32">
                <span class="text-dark fw-semibold">Partilot</span>
                <span class="badge bg-warning text-dark ms-2">Diseño por invitación</span>
            </a>
        </div>
    </div>
    <div id="wrapper" class="mt-0">
        <div class="content-page">
            <div class="content">
                <div class="container-fluid p-3">
                    @php
                        $flashSuccess = session()->pull('success');
                        $flashError = session()->pull('error');
                    @endphp
                    @yield('content')
                </div>
            </div>
        </div>
    </div>
    <script src="{{ url('default') }}/assets/js/vendor.min.js"></script>
    <script src="{{ url('assets/libs/pnotify/pnotify.js') }}"></script>
    <script src="{{ url('assets/libs/pnotify/pnotify.buttons.js') }}"></script>
    {{-- app.min.js no se carga aquí: espera DOM del layout completo (sidebar/topbar) y daría "reading 'href' of null" --}}
    <script src="{{ url('assets/libs/jquery-ui/jquery-ui.js') }}"></script>
    <script src="{{ url('ckeditor/ckeditor.js') }}"></script>
    <script src="{{ url('ckeditor/adapters/jquery.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof PNotify === 'undefined') return;

            PNotify.prototype.options.styling = 'bootstrap3';
            PNotify.prototype.options.delay = 5000;
            PNotify.prototype.options.opacity = 1;
            PNotify.prototype.options.animate = false;
            PNotify.prototype.options.stack = {
                dir1: 'down',
                dir2: 'left',
                firstpos1: 20,
                firstpos2: 20
            };

            const notices = [
                { type: 'success', text: @json($flashSuccess) },
                { type: 'error', text: @json($flashError) }
            ];

            notices.forEach(function (item) {
                if (!item.text) return;
                new PNotify({
                    type: item.type,
                    addclass: 'partilot-notify',
                    width: '460px',
                    text: item.text,
                    hide: true,
                    buttons: {
                        closer: true,
                        sticker: false,
                        closer_hover: false
                    }
                });
            });

            document.addEventListener('click', function (e) {
                const closer = e.target.closest('.ui-pnotify .ui-pnotify-closer');
                if (!closer) return;
                const notice = closer.closest('.ui-pnotify');
                if (notice) {
                    notice.remove();
                }
            });
        });
    </script>
    @include('partials.partilot-cookie-consent-state')
    @include('partials.legal-analytics-scripts')
    @include('partials.cookie-consent-banner')
    @yield('scripts')
</body>
</html>
