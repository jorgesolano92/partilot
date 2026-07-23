<!DOCTYPE html>
<html lang="es" class="partilot-loading" data-topbar-color="light" data-layout-mode="detached" data-bs-theme="light">

    
<!-- Mirrored from coderthemes.com/ubold/layouts/default/index.html by HTTrack Website Copier/3.x [XR&CO'2014], Sun, 25 May 2025 15:56:35 GMT -->
<head>
        <meta charset="utf-8" />
        <style id="partilot-preloader-critical">
            html.partilot-loading,
            html.partilot-loading body {
                overflow: hidden;
            }
            html.partilot-loading #wrapper,
            html.partilot-loading .offcanvas,
            html.partilot-loading .modal {
                visibility: hidden;
            }
            #partilot-preloader {
                position: fixed;
                inset: 0;
                z-index: 99999;
                display: flex;
                align-items: center;
                justify-content: center;
                background: #f3f4f8;
                transition: opacity 0.35s ease, visibility 0.35s ease;
            }
            #partilot-preloader.partilot-preloader--hide {
                opacity: 0;
                visibility: hidden;
                pointer-events: none;
            }
            .partilot-preloader__inner {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 18px;
            }
            .partilot-preloader__logo {
                width: 120px;
                height: auto;
                display: block;
            }
            .partilot-preloader__spinner {
                width: 40px;
                height: 40px;
                border: 3px solid #d9dde8;
                border-top-color: #1f2430;
                border-radius: 50%;
                animation: partilot-preloader-spin 0.75s linear infinite;
            }
            .partilot-preloader__text {
                margin: 0;
                font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
                font-size: 0.9rem;
                font-weight: 600;
                color: #737b8f;
                letter-spacing: 0.02em;
            }
            @keyframes partilot-preloader-spin {
                to { transform: rotate(360deg); }
            }
        </style>
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title') | PARTILOT</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta content="A fully featured admin theme which can be used to build CRM, CMS, etc." name="description" />
        <meta content="Coderthemes" name="author" />

        <link href="{{url('assets')}}/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css" rel="stylesheet" type="text/css" />
        <link href="{{url('assets')}}/libs/datatables.net-responsive-bs5/css/responsive.bootstrap5.min.css" rel="stylesheet" type="text/css" />
        <link href="{{url('assets')}}/libs/datatables.net-buttons-bs5/css/buttons.bootstrap5.min.css" rel="stylesheet" type="text/css" />
        <link href="{{url('assets')}}/libs/datatables.net-select-bs5/css/select.bootstrap5.min.css" rel="stylesheet" type="text/css" />

        <!-- App favicon -->
        <link rel="shortcut icon" href="{{url('/')}}/logo.svg">

        <!-- Plugins css -->
        <link href="{{url('assets')}}/libs/flatpickr/flatpickr.min.css" rel="stylesheet" type="text/css" />
        <link href="{{url('assets')}}/libs/selectize/css/selectize.bootstrap3.css" rel="stylesheet" type="text/css" />
        
        <!-- Theme Config Js -->
        <script src="{{url('default')}}/assets/js/head.js"></script>

        <!-- Bootstrap css -->
        <link href="{{url('default')}}/assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" id="app-style" />

        <!-- App css -->
        <link href="{{url('default')}}/assets/css/app.min.css" rel="stylesheet" type="text/css" />

        <!-- Icons css -->
        <link href="{{url('assets')}}/css/icons.min.css" rel="stylesheet" type="text/css" />
        <link rel="stylesheet" href="{{ url('assets/libs/pnotify/pnotify.css') }}" />
        <link rel="stylesheet" href="{{ url('assets/libs/pnotify/pnotify.buttons.css') }}" />
        <style>
            .ui-pnotify {
                opacity: 1 !important;
                z-index: 20000 !important;
                top: 103px !important;
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
                padding: 14px 20px 14px 20px !important;
                font-size: 15px !important;
                font-weight: 600 !important;
            }
            .ui-pnotify.partilot-notify .ui-pnotify-closer,
            .ui-pnotify .ui-pnotify-closer {
                visibility: visible !important;
                opacity: 1 !important;
                right: 6px !important;
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
                position: absolute !important;
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
            .ui-pnotify .alert-info,
            .ui-pnotify .ui-pnotify-info {
                background-color: #cff4fc !important;
                color: #055160 !important;
                border-color: #9eeaf9 !important;
                opacity: 1 !important;
            }
            .ui-pnotify .ui-pnotify-container.brighttheme-success,
            .ui-pnotify.brighttheme-success .ui-pnotify-container {
                background-color: #c7efe9 !important;
                color: #0f766e !important;
                border-color: #8fd7cd !important;
            }
            .ui-pnotify .ui-pnotify-container.brighttheme-error,
            .ui-pnotify.brighttheme-error .ui-pnotify-container {
                background-color: #f8d7da !important;
                color: #842029 !important;
                border-color: #f1aeb5 !important;
            }
            .ui-pnotify .ui-pnotify-container.brighttheme-notice,
            .ui-pnotify.brighttheme-notice .ui-pnotify-container {
                background-color: #fff3cd !important;
                color: #7a5a00 !important;
                border-color: #ffe69c !important;
            }
            .ui-pnotify .ui-pnotify-container.brighttheme-info,
            .ui-pnotify.brighttheme-info .ui-pnotify-container {
                background-color: #cff4fc !important;
                color: #055160 !important;
                border-color: #9eeaf9 !important;
            }
            .ui-pnotify .ui-pnotify-title,
            .ui-pnotify .ui-pnotify-text {
                color: inherit !important;
            }
        </style>

        <link rel="stylesheet" href="{{ url('assets/libs/jquery-ui/themes/base/jquery-ui.css') }}">

        <link rel="stylesheet" href="{{url('style.css')}}">
        <link rel="stylesheet" href="{{ asset('assets/libs/ckeditor5/ckeditor5.css') }}">
        {{-- Último CSS de panel: card + footer unidos (gana a theme absolute footer) --}}
        <link href="{{url('assets')}}/css/partilot-ui-fixes.css?v={{ @filemtime(public_path('assets/css/partilot-ui-fixes.css')) ?: time() }}" rel="stylesheet" type="text/css" />

        <style>
            .container-fluid .alert {
                display: none;
            }

            .container-fluid .show-alerts .alert {
                display: block;
            }
            /* Formularios: mantener estilo pill en controles dentro de input-group */
            .content-page .content .group-form .form-control,
            .content-page .content .group-form .ts-wrapper.single .ts-control {
                border-radius: 30px !important;
            }
            .content-page .content .group-form .input-group-text + .form-control,
            .content-page .content .group-form .input-group-text + .ts-wrapper.single .ts-control {
                border-top-left-radius: 0 !important;
                border-bottom-left-radius: 0 !important;
            }
            .content-page .content .group-form .ts-wrapper.single.focus .ts-control,
            .content-page .content .group-form .ts-wrapper.single .ts-control:focus-within {
                outline: none !important;
                box-shadow: none !important;
            }
            .inline-fields {

                width: 100%;
                min-height: 36px !important;
                background-color: transparent;
                border: none;
                border-bottom: 1px solid silver;
                outline: none;
              }
            /* Logos redondos: mismo tamaño, imagen como background cover */
            .logo-round {
                border-radius: 50% !important;
                overflow: hidden;
                background-size: cover !important;
                background-position: center !important;
            }
            .logo-round-sm {
                width: 48px;
                height: 48px;
                min-width: 48px;
                min-height: 48px;
            }
            /* Estilos para el buscador de participaciones */
            #top-search-wrap,
            #top-search-wrap.app-search,
            .app-search#top-search-wrap {
                flex: 0 0 auto;
                width: 360px;
                min-width: 280px;
                max-width: 360px;
                position: relative !important;
                z-index: 2;
                overflow: visible !important;
            }
            #top-search-wrap form,
            .app-search#top-search-wrap form {
                width: 100%;
                overflow: visible !important;
            }
            #top-search {
                width: 100% !important;
                min-width: 0 !important;
                position: relative;
                z-index: 2;
            }
            #search-dropdown {
                width: 500px !important;
                min-width: 500px !important;
                max-width: 500px !important;
                max-height: 500px !important;
                overflow-y: auto !important;
                overflow-x: hidden !important;
                z-index: 10001 !important;
                background-color: #fff !important;
                border: 1px solid rgba(0,0,0,.15) !important;
                border-radius: 0.375rem !important;
                box-shadow: 0 0.5rem 1rem rgba(0,0,0,.15) !important;
                position: absolute !important;
                top: 100% !important;
                left: 0 !important;
                margin-top: 0.5rem !important;
                transform: translateZ(0) !important;
                will-change: transform !important;
            }
            /* Evitar overflow horizontal en los items del dropdown */
            #search-dropdown .dropdown-item,
            #search-dropdown .notification-list {
                overflow-x: hidden !important;
                word-wrap: break-word !important;
            }
            #search-dropdown .search-result-item {
                max-width: 100% !important;
                overflow: hidden !important;
            }
            #search-dropdown .text-truncate {
                max-width: 100% !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
                white-space: nowrap !important;
            }
            #top-search-wrap.show {
                z-index: 10000 !important;
            }
            #top-search-wrap.show #search-dropdown,
            #search-dropdown.show {
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
            }
            /* Asegurar que el topbar tenga z-index alto */
            .navbar-custom,
            .topbar-menu {
                position: relative;
                z-index: 1000;
            }
            /* Topbar global estilo Figma */
            .navbar-custom {
                width: 100%;
                margin-left: auto;
                margin-right: 0;
                background: #f3f4f8;
                border: 1px solid #e2e5ee;
                border-radius: 30px;
                box-shadow: none;
                overflow: visible;
            }
            .navbar-custom .topbar {
                background: transparent;
                min-height: 64px;
                padding: 8px 16px;
                overflow: visible;
                justify-content: flex-end;
            }
            .navbar-custom .topbar > .topbar-menu:first-child {
                display: none;
            }
            .navbar-custom .topbar > .topbar-menu:last-child {
                margin-left: auto;
                flex: 0 0 auto;
                width: auto;
                padding: 10px 14px;
                border: 1px solid #d9dde8;
                border-radius: 999px;
                background: #f8f9fd;
                gap: 8px;
                overflow: visible;
                margin-right: 20px;
                min-height: 56px;
            }
            .navbar-custom .topbar .nav-link {
                border-radius: 999px;
                height: 38px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }
            .navbar-custom .topbar .nav-user {
                border-radius: 999px;
                display: inline-flex;
                justify-content: center;
            }
            .navbar-custom .topbar .nav-link {
                width: 38px;
                padding: 0;
                border: 1px solid #cfd5e2;
                background: #ffffff;
            }
            .navbar-custom .topbar .nav-user {
                width: auto;
                min-height: 48px;
                height: auto !important;
                padding: 6px 8px 6px 4px !important;
                gap: 8px;
                border: 0;
                background: transparent;
                align-items: flex-start !important;
            }
            .navbar-custom .topbar .nav-user img {
                display: none;
            }
            .navbar-custom .topbar .nav-user .user-name {
                display: block;
                color: #1f2430;
                font-weight: 700;
                line-height: 1.05;
                font-size: 1.05rem;
            }
            .navbar-custom .topbar .nav-user .user-role {
                display: block;
                color: #737b8f;
                text-transform: uppercase;
                letter-spacing: .04em;
                font-size: .63rem;
                font-weight: 700;
                margin-top: 2px;
                line-height: 1;
            }
            .navbar-custom .topbar .nav-user .mdi-chevron-down {
                display: none;
            }
            .navbar-custom .topbar .dropdown-menu {
                z-index: 1100;
            }
            .navbar-custom #top-search-wrap {
                width: 360px;
                min-width: 280px;
                max-width: 360px;
                margin-right: 4px;
                overflow: visible !important;
            }
            .navbar-custom .app-search input.form-control {
                background: #eef1f7;
                border: 1px solid #e2e6f0;
                border-radius: 999px;
                height: 38px;
                font-size: .9rem;
                padding-left: 38px;
                padding-right: 12px;
                pointer-events: auto;
            }
            .navbar-custom .app-search .search-icon {
                left: 12px;
                right: auto;
                top: 4px;
                color: #8f97ac;
                pointer-events: none;
                z-index: 3;
            }
            .navbar-custom .app-search .search-icon:before {
                top: -5px;
                position: relative;
            }
            .topbar-btn-notifications .nav-link {
                position: relative;
            }

            /* Badges de estado: Activo / Inactivo / Bloqueado */
            .badge.bg-success:not(.rounded-circle) {
                background-color: #cfead2 !important;
                color: #0a9c2d !important;
                border: 1px solid #95c79b;
                border-radius: 8px !important;
                font-weight: 700;
                padding: 4px 8px !important;
                font-size: 10px !important;
                min-width: 80px !important;
            }
            .badge.bg-primary:not(.rounded-circle) {
                background-color: #cfe2ff !important;
                color: #0a58ca !important;
                border: 1px solid #9ec5fe;
                border-radius: 8px !important;
                font-weight: 700;
                padding: 4px 8px !important;
                font-size: 10px !important;
                min-width: 80px !important;
            }
            .badge.bg-secondary:not(.rounded-circle) {
                background-color: #e5e6e8 !important;
                color: #4b5056 !important;
                border: 1px solid #9aa0a6;
                border-radius: 8px !important;
                font-weight: 700;
                padding: 4px 8px !important;
                font-size: 10px !important;
                min-width: 80px !important;
            }
            .badge.bg-danger:not(.rounded-circle) {
                background-color: #f3e8e8 !important;
                color: #a10000 !important;
                border: 1px solid #c7a7a7;
                border-radius: 8px !important;
                font-weight: 700;
                padding: 4px 8px !important;
                font-size: 10px !important;
                min-width: 80px !important;
            }
            .badge.bg-info:not(.rounded-circle) {
                background-color: #d9eef5 !important;
                color: #0f5f7c !important;
                border: 1px solid #9dc1cf;
                border-radius: 8px !important;
                font-weight: 700;
                padding: 4px 8px !important;
                font-size: 10px !important;
                min-width: 80px !important;
            }
            .badge.bg-warning:not(.rounded-circle) {
                background-color: #f9efcc !important;
                color: #8a6700 !important;
                border: 1px solid #d8bf70;
                border-radius: 8px !important;
                font-weight: 700;
                padding: 4px 8px !important;
                font-size: 10px !important;
                min-width: 80px !important;
            }

            .no-click .btn-sm {
                padding: 4px !important;
                width: 28px !important;
                height: 28px !important;
                border: 1px solid silver !important;
                margin-left: 5px !important;
                border-radius: 4px !important;
            }

            .no-click .btn-sm img, .no-click .btn-sm i::before {
                font-size: 14px !important;
                height: 12px !important;
                width: auto;
                margin: 3px 0 !important;
            }

            /* Listados: scroll horizontal contenido en el wrapper de DataTables */
            html, body {
                max-width: 100%;
                overflow-x: hidden;
            }
            #wrapper {
                max-width: 100%;
                min-width: 0;
                overflow-x: hidden;
            }
            .content-page {
                max-width: 100%;
                min-width: 0;
                overflow-x: hidden;
            }
            .content-page .content {
                max-width: 100%;
                min-width: 0;
                overflow-x: hidden;
                overflow-y: visible;
            }
            .content-page .content > .container-fluid {
                max-width: 100%;
                min-width: 0;
                height: auto !important;
            }
            .content-page .content .container-fluid > .row > [class*="col-"],
            .content-page .content .container-fluid form > .row > [class*="col-"] {
                min-width: 0;
            }
            .content-page .content .container-fluid > .row > [class*="col-"] > .card,
            .content-page .content .container-fluid form > .row > [class*="col-"] > .card {
                /* visible: overflow-x:clip fuerza overflow-y:auto y recorta Atrás/Guardar */
                overflow: visible;
                max-width: 100%;
                min-width: 0;
                height: auto !important;
            }
            .content-page .content .container-fluid > .row > [class*="col-"] > .card > .card-body,
            .content-page .content .container-fluid form > .row > [class*="col-"] > .card > .card-body {
                max-width: 100%;
                min-width: 0;
                overflow: visible;
            }
            .content-page .form-card,
            .content-page .form-card.bs {
                max-width: 100%;
                min-width: 0;
                overflow-x: clip;
                overflow-y: visible;
            }
            .content-page .table-responsive,
            .content-page .panel-table-scroll {
                width: 100%;
                max-width: 100%;
                min-width: 0;
                overflow-x: auto;
                overflow-y: visible;
                -webkit-overflow-scrolling: touch;
            }
            .content-page .table-responsive:has(> .dataTables_wrapper),
            .content-page .panel-table-scroll:has(> .dataTables_wrapper) {
                overflow-x: visible;
            }
            /* Cualquier DataTable del panel (no solo hijo directo de .card-body) */
            .content-page .dataTables_wrapper {
                width: 100%;
                max-width: 100%;
                min-width: 0;
                overflow-x: auto;
                overflow-y: visible;
                -webkit-overflow-scrolling: touch;
            }
            .content-page .dataTables_wrapper table.dataTable {
                width: 100% !important;
                max-width: none;
            }
            /* Contención si alguna vista conserva scrollX */
            .content-page .dataTables_wrapper .dataTables_scroll {
                width: 100% !important;
                clear: both;
            }
            .content-page .dataTables_wrapper .dataTables_scrollHead {
                overflow: hidden !important;
            }
            .content-page .dataTables_wrapper .dataTables_scrollHeadInner,
            .content-page .dataTables_wrapper .dataTables_scrollHeadInner table {
                width: 100% !important;
            }
            .content-page .dataTables_wrapper .dataTables_scrollBody {
                width: 100% !important;
                max-height: none !important;
                overflow-x: auto !important;
                overflow-y: visible !important;
            }
            
            @media (max-width: 1200px) {
                .navbar-custom {
                    width: 100%;
                }
                .navbar-custom .topbar .nav-user .user-role {
                    display: none;
                }
            }

            .dt-bootstrap5 {
                min-height: calc(100vh - 335px);
                overflow: unset !important;
            }

            .content-page .content .container-fluid > .row > [class*="col-"] > .card,
            .content-page .content .container-fluid form > .row > [class*="col-"] > .card {
                margin-bottom: 0;
            }

            /*
             * Card + footer unidos (como Administraciones / Sorteos listado).
             * La fila del card crece hasta el footer; el footer va justo debajo.
             */
            .content-page {
                display: flex !important;
                flex-direction: column !important;
                min-height: 100vh;
            }

            .content-page > .content {
                flex: 1 1 auto !important;
                display: flex !important;
                flex-direction: column !important;
                min-height: 0 !important;
                padding: 0 15px !important;
            }

            .content-page .content .container-fluid:not(.design-editor-page):not(.dashboard-panel) {
                flex: 1 1 auto !important;
                display: flex !important;
                flex-direction: column !important;
                min-height: 0 !important;
                width: 100%;
            }

            .content-page .content .container-fluid:not(.design-editor-page):not(.dashboard-panel) > .row:last-child {
                flex: 1 1 auto !important;
                display: flex !important;
                min-height: 0 !important;
            }

            .content-page .content .container-fluid:not(.design-editor-page):not(.dashboard-panel) > .row:last-child > [class*="col-"] {
                flex: 1 1 auto !important;
                display: flex !important;
                flex-direction: column !important;
                min-height: 0 !important;
            }

            .content-page .content .container-fluid:not(.design-editor-page):not(.dashboard-panel) > .row:last-child > [class*="col-"] > .card {
                flex: 1 1 auto !important;
                display: flex !important;
                flex-direction: column !important;
                min-height: 0 !important;
                min-width: 0 !important;
                height: auto !important;
                margin-bottom: 0 !important;
                border: 1px solid #d9dde8 !important;
                border-bottom: 0 !important;
                border-radius: 18px 18px 0 0 !important;
                box-shadow: none !important;
                overflow: visible !important;
            }

            /* No forzar flex-column en todo .card-body: apila botones de listados (Sorteos, etc.) */
            .content-page .content .container-fluid:not(.design-editor-page):not(.dashboard-panel) > .row:last-child > [class*="col-"] > .card > .card-body {
                flex: 1 1 auto !important;
                min-height: 0 !important;
                min-width: 0 !important;
                padding-bottom: 32px !important;
                overflow: visible !important;
            }

            /* Footer unido (también si el DOM lo anida dentro de .content) */
            #wrapper .content-page > footer.footer,
            #wrapper .content-page footer.footer,
            #wrapper .content-page .content > footer.footer {
                position: relative !important;
                flex-shrink: 0 !important;
                bottom: auto !important;
                left: auto !important;
                right: auto !important;
                width: auto !important;
                height: 60px !important;
                display: flex !important;
                align-items: center !important;
                margin: -1px 27px 24px !important;
                padding: 0 1.5rem !important;
                border: 1px solid #d9dde8 !important;
                border-top: 0 !important;
                border-radius: 0 0 18px 18px !important;
                background: #fff !important;
                box-shadow: none !important;
            }

            #wrapper .content-page footer.footer > .container-fluid {
                width: 100%;
                padding-left: 0 !important;
                padding-right: 0 !important;
            }

            #wrapper .content-page footer.footer .row {
                align-items: center !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
                width: 100%;
            }

            html[data-layout-mode="detached"]:not([data-layout="horizontal"]) body:not(.auth-fluid-pages) #wrapper .content-page .content {
                min-height: 0 !important;
            }

            html[data-layout-mode="detached"]:not([data-layout="horizontal"]) body:not(.auth-fluid-pages) #wrapper .content-page footer.footer {
                margin: -1px 12px 24px !important;
                border-radius: 0 0 18px 18px !important;
                box-shadow: none !important;
            }

            .header-title {margin: 0 !important;}
            .format-box-btn {text-align: center !important;}

            [class^="col"]:has(.dataTable), 
            [class*=" col"]:has(.dataTable) { 
            overflow-x: auto !important; 
            }
        </style>

        @yield('styles')

    </head>

    <body>

        <div id="partilot-preloader" role="status" aria-live="polite" aria-label="Cargando Partilot">
            <div class="partilot-preloader__inner">
                <img src="{{ url('logo_menu.svg') }}" alt="Partilot" class="partilot-preloader__logo" width="120" height="40">
                <div class="partilot-preloader__spinner" aria-hidden="true"></div>
                <p class="partilot-preloader__text">Cargando…</p>
            </div>
        </div>

        <!-- Begin page -->
        <div id="wrapper">

            
            <!-- ========== Menu ========== -->
            <div class="app-menu menuitem-active">  

                <!-- Brand Logo -->
                <div class="logo-box">
                    <!-- Brand Logo Light -->
                    <a href="{{url('/dashboard')}}" class="logo-light">
                        {{-- <img src="{{url('default')}}/assets/images/logo-light.png" alt="logo" class="logo-lg"> --}}
                        <img src="{{url('/')}}/logo_menu.svg" alt="logo" class="logo-lg">
                        {{-- <img src="{{url('default')}}/assets/images/logo-sm.png" alt="small logo" class="logo-sm"> --}}
                        <img src="{{url('/')}}/logo.svg" alt="small logo" class="logo-sm">
                    </a>

                    <!-- Brand Logo Dark -->
                    <a href="{{url('/dashboard')}}" class="logo-dark">
                        {{-- <img src="{{url('default')}}/assets/images/logo-dark.png" alt="dark logo" class="logo-lg"> --}}
                        <img src="{{url('/')}}/logo_menu.svg" alt="dark logo" class="logo-lg">
                        {{-- <img src="{{url('default')}}/assets/images/logo-sm.png" alt="small logo" class="logo-sm"> --}}
                        <img src="{{url('/')}}/logo.svg" alt="small logo" class="logo-sm">
                    </a>
                </div>

                <!-- menu-left -->
                <div class="scrollbar">

                    <!-- User box -->
                    <div class="user-box text-center">
                        @php
                            $panelHeaderImg = Auth::user()?->panelAccountHeaderImageUrl();
                            $panelContextLabel = Auth::user()?->panelHeaderContextLabel();
                        @endphp
                        <img src="{{ $panelHeaderImg ?? url('default').'/assets/images/users/user-1.jpg' }}" alt="" title="{{ Auth::user()->name ?? 'Usuario' }}" class="rounded-circle avatar-md" @if($panelHeaderImg) style="object-fit:cover;width:64px;height:64px;" @endif>
                        <h5 class="mb-1 mt-2">{{ Auth::user()->name ? Auth::user()->name.' '.Auth::user()->last_name : 'Usuario' }}</h5>
                        @if($panelContextLabel)
                            <p class="user-box-panel-context mb-1" title="{{ $panelContextLabel }}">{{ $panelContextLabel }}</p>
                        @endif
                        <p class="text-muted mb-2">{{ Auth::user()->email ?? '' }}</p>
                        @if(Auth::check() && Auth::user()->isPanelAccount() && Auth::user()->panel_account_type === 'administration')
                            <a href="{{ route('account.my-data') }}" class="btn btn-sm btn-light mb-2">Mis datos</a>
                        @endif
                        <form method="POST" action="{{ url('logout') }}" class="menu-logout-link">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-danger w-100">
                                <i class="fe-log-out me-1"></i> Cerrar sesión
                            </button>
                        </form>
                    </div>

                    <!--- Menu -->
                    @php
                        $currentUser = Auth::user();
                        $selected = null;
                        $isEntityPanelReadOnly = $currentUser && $currentUser->isEntityPanelReadOnly();
                        $canSeeAdminModules = $currentUser && ($currentUser->isSuperAdmin() || $currentUser->isAdministration());
                        $canSeeEntitiesMenu = $canSeeAdminModules || (
                            $currentUser
                            && $currentUser->isEntity()
                            && ! $currentUser->isPanelAccount()
                            && $currentUser->isPrimaryManagerOfActiveEntity()
                        );
                        $entitySwitcherOptions = ($currentUser && \App\Support\ActiveEntityContext::usesActiveEntityScope($currentUser))
                            ? \App\Support\ActiveEntityContext::switcherOptions($currentUser)
                            : [];
                        $activeEntitySwitcherId = ($currentUser && \App\Support\ActiveEntityContext::usesActiveEntityScope($currentUser))
                            ? \App\Support\ActiveEntityContext::activeEntityId($currentUser)
                            : null;
                        $canSeeEntityModules = $currentUser && ($currentUser->isSuperAdmin() || $currentUser->isAdministration() || $currentUser->isEntity());

                        $isRestrictedEntityUser = $currentUser && $currentUser->isEntity() && !$currentUser->isSuperAdmin() && !$currentUser->isAdministration();
                        
                        $canSeeSellerModules = $canSeeEntityModules && (
                            $isEntityPanelReadOnly
                            || !$isRestrictedEntityUser
                            || $currentUser->hasEntityManagerPermission('sellers')
                        );
                        $canSeeDesignModules = $canSeeEntityModules && (
                            $isEntityPanelReadOnly
                            || !$isRestrictedEntityUser
                            || $currentUser->hasEntityManagerPermission('design')
                        );

                        $canSeeSettingsModules = $currentUser && (
                            $currentUser->isSuperAdmin()
                            || $currentUser->isAdministration()
                            || $isEntityPanelReadOnly
                            || ($isRestrictedEntityUser && $currentUser->hasEntityManagerPermission('payments'))
                        );
                        $isPrintShopUser = $currentUser && $currentUser->isPrintShop();
                    @endphp
                    <ul class="menu">

                        <li class="menu-title">Navigation</li>

                        @if($isPrintShopUser)
                            <li class="menu-item @if (Request::is('print-shop') || Request::is('print-shop/*')) menuitem-active @php $selected = 1; @endphp @endif">
                                <a href="{{ route('print-shop.index') }}" class="menu-link">
                                    <span class="menu-icon">
                                        <img src="{{url('icons_')}}/diseno{{$selected == 1 ? '' : ''}}.svg" alt="">
                                    </span>
                                    <span class="menu-text"> Órdenes Imprenta </span>
                                    @php $selected = null; @endphp
                                </a>
                            </li>
                        @else
                        <li class="menu-item @if (Request::is('dashboard') || Request::is('/')) menuitem-active @php $selected = 1; @endphp @endif">
                            <a href="{{url('/dashboard')}}" class="menu-link">
                                <span class="menu-icon">
                                    <img src="{{url('icons_')}}/dashboard{{$selected == 1 ? '_selected' : ''}}.svg" alt="">
                                </span>
                                <span class="menu-text"> Panel </span>
                                @php $selected = null; @endphp
                            </a>
                        </li>

                        @if($currentUser && $currentUser->isSuperAdmin())
                            <li class="menu-item @if (Request::is('administrations/*') || Request::is('administrations')) menuitem-active @php $selected = 1; @endphp @endif">
                                <a href="{{url('/administrations')}}" class="menu-link">
                                    <span class="menu-icon">
                                        <img src="{{url('icons_')}}/administraciones{{$selected == 1 ? '_selected' : ''}}.svg" alt="">
                                    </span>
                                    <span class="menu-text"> Administraciones </span>
                                    @php $selected = null; @endphp
                                </a>
                            </li>
                            <li class="menu-item @if (Request::is('prize-payments/*') || Request::is('prize-payments')) menuitem-active @php $selected = 1; @endphp @endif">
                                <a href="{{ route('prize-payments.index') }}" class="menu-link">
                                    <span class="menu-icon">
                                        <i class="ri-money-euro-circle-line" style="font-size: 18px; position: relative; top: 3px;"></i>
                                    </span>
                                    <span class="menu-text"> Cobro de premios </span>
                                    @php $selected = null; @endphp
                                </a>
                            </li>
                        @endif

                        @if($canSeeEntitiesMenu)
                            <li class="menu-item @if (Request::is('entities/*') || Request::is('entities')) menuitem-active @php $selected = 1; @endphp @endif">
                                <a href="{{url('/entities')}}" class="menu-link">
                                    <span class="menu-icon">
                                        <img src="{{url('icons_')}}/entidades{{$selected == 1 ? '_selected' : ''}}.svg" alt="">
                                    </span>
                                    <span class="menu-text"> Entidades </span>
                                    @php $selected = null; @endphp
                                </a>
                            </li>
                        @endif

                        @if($canSeeSellerModules)
                            <li class="menu-item @if (Request::is('sellers/*') || Request::is('sellers')) menuitem-active @php $selected = 1; @endphp @endif">
                                <a href="{{url('/sellers')}}" class="menu-link">
                                    <span class="menu-icon">
                                        <img src="{{url('icons_')}}/vendedores{{$selected == 1 ? '_selected' : ''}}.svg" alt="">
                                    </span>
                                    <span class="menu-text"> Vendedores/Asignación </span>
                                    @php $selected = null; @endphp
                                </a>
                            </li>
                        @endif

                        @if($currentUser && $currentUser->isSuperAdmin())
                            <li class="menu-item @if (Request::is('users/*') || Request::is('users')) menuitem-active @php $selected = 1; @endphp @endif">
                                <a href="{{url('/users')}}" class="menu-link">
                                    <span class="menu-icon">
                                        <img src="{{url('icons_')}}/usuarios{{$selected == 1 ? '_selected' : ''}}.svg" alt="">
                                    </span>
                                    <span class="menu-text"> Usuarios </span>
                                    @php $selected = null; @endphp
                                </a>
                            </li>
                        @endif

                        @if($canSeeEntityModules)
                            <li class="menu-item @if (Request::is('lottery/*') || Request::is('lottery') || Request::is('lottery_types/*') || Request::is('lottery_types')) menuitem-active @php $selected = 1; @endphp @endif">
                                <a href="{{url('/lottery')}}" class="menu-link">
                                    <span class="menu-icon">
                                        <img src="{{url('icons_')}}/sorteos{{$selected == 1 ? '_selected' : ''}}.svg" alt="">
                                    </span>
                                    <span class="menu-text"> Sorteos </span>
                                    @php $selected = null; @endphp
                                </a>
                            </li>
                        @endif

                        @if($canSeeAdminModules)
                            <li class="menu-item @if (Request::is('scrutiny/*') || Request::is('scrutiny')) menuitem-active @php $selected = 1; @endphp @endif">
                                <a href="{{url('/scrutiny')}}" class="menu-link">
                                    <span class="menu-icon">
                                        <img src="{{url('icons_')}}/sorteos{{$selected == 1 ? '_selected' : ''}}.svg" alt="">
                                    </span>
                                    <span class="menu-text"> Escrutinio </span>
                                    @php $selected = null; @endphp
                                </a>
                            </li>
                        @endif

                        @if($canSeeEntityModules)
                            <li class="menu-item @if (Request::is('reserves/*') || Request::is('reserves')) menuitem-active @php $selected = 1; @endphp @endif">
                                <a href="{{url('/reserves')}}" class="menu-link">
                                    <span class="menu-icon">
                                        <img src="{{url('icons_')}}/reservas{{$selected == 1 ? '_selected' : ''}}.svg" alt="">
                                    </span>
                                    <span class="menu-text"> Reservas </span>
                                    @php $selected = null; @endphp
                                </a>
                            </li>
                        @endif

                        @if($canSeeEntityModules)
                            <li class="menu-item @if (Request::is('sets/*') || Request::is('sets')) menuitem-active @php $selected = 1; @endphp @endif">
                                <a href="{{url('/sets')}}" class="menu-link">
                                    <span class="menu-icon">
                                        <img style="width: 20px;" src="{{url('icons_')}}/sets{{$selected == 1 ? '_selected' : ''}}.svg" alt="">
                                    </span>
                                    <span class="menu-text"> Set particip. </span>
                                    @php $selected = null; @endphp
                                </a>
                            </li>
                        @endif

                        @if($canSeeDesignModules)
                            <li class="menu-item @if (Request::is('design/*') || Request::is('design')) menuitem-active @php $selected = 1; @endphp @endif">
                                <a href="{{url('/design')}}" class="menu-link">
                                    <span class="menu-icon">
                                        <img style="width: 20px;" src="{{url('icons_')}}/diseno{{$selected == 1 ? '_selected' : ''}}.svg" alt="">
                                    </span>
                                    <span class="menu-text"> Diseño e Impresión </span>
                                    @php $selected = null; @endphp
                                </a>
                            </li>
                        @endif

                        @if($canSeeEntityModules)
                            <li class="menu-item @if (Request::is('participations/*') || Request::is('participations')) menuitem-active @php $selected = 1; @endphp @endif">
                                <a href="{{url('participations')}}" class="menu-link">
                                    <span class="menu-icon">
                                        <img src="{{url('icons_')}}/participaciones{{$selected == 1 ? '_selected' : ''}}.svg" alt="">
                                    </span>
                                    <span class="menu-text"> Participaciones </span>
                                    @php $selected = null; @endphp
                                </a>
                            </li>
                        @endif

                        @if($canSeeEntityModules && Auth::check() && Auth::user()->hasAccessToDevolutionsModule())
                            <li class="menu-item @if (Request::is('devolutions/*') || Request::is('devolutions')) menuitem-active @php $selected = 1; @endphp @endif">
                                <a href="{{url('devolutions')}}" class="menu-link">
                                    <span class="menu-icon">
                                        <img style="width: 19.5px;" src="{{url('icons_')}}/devolucion{{$selected == 1 ? '_selected' : ''}}.svg" alt="">
                                    </span>
                                    <span class="menu-text"> Devolución </span>
                                    @php $selected = null; @endphp
                                </a>
                            </li>
                        @endif

                        @if($canSeeEntityModules)
                            <li class="menu-item @if (Request::is('social/*') || Request::is('social')) menuitem-active @php $selected = 1; @endphp @endif">
                                <a href="{{url('social')}}" class="menu-link">
                                    <span class="menu-icon">
                                        <img src="{{url('icons_')}}/websocial{{$selected == 1 ? '_selected' : ''}}.svg" alt="">
                                    </span>
                                    <span class="menu-text"> Web Social </span>
                                    @php $selected = null; @endphp
                                </a>
                            </li>
                        @endif

                        <li class="menu-item @if (Request::is('requests/*') || Request::is('requests')) menuitem-active @php $selected = 1; @endphp @endif">
                            <a href="{{url('requests')}}" class="menu-link">
                                <span class="menu-icon">
                                    <img src="{{url('icons_')}}/solicitudes{{$selected == 1 ? '_selected' : ''}}.svg" alt="">
                                </span>
                                <span class="menu-text"> Solicitudes </span>
                                @php $selected = null; @endphp
                            </a>
                        </li>

                        <li class="menu-item @if (Request::is('notifications/*') || Request::is('notifications')) menuitem-active @php $selected = 1; @endphp @endif">
                            <a href="{{url('notifications')}}" class="menu-link">
                                <span class="menu-icon">
                                    <img src="{{url('icons_')}}/comunicados{{$selected == 1 ? '_selected' : ''}}.svg" alt="">
                                </span>
                                <span class="menu-text"> Notificaciones </span>
                                @php $selected = null; @endphp
                            </a>
                        </li>

                        <li class="menu-item @if (Request::is('communications/*') || Request::is('communications')) menuitem-active @php $selected = 1; @endphp @endif">
                            <a href="{{url('communications')}}" class="menu-link">
                                <span class="menu-icon">
                                    <img src="{{url('icons_')}}/comunicados{{$selected == 1 ? '_selected' : ''}}.svg" alt="">
                                </span>
                                <span class="menu-text"> Comunicaciones </span>
                                @php $selected = null; @endphp
                            </a>
                        </li>

                        @if($canSeeSettingsModules)
                            <li class="menu-item @if (Request::is('configuration/*') || Request::is('configuration')) menuitem-active @php $selected = 1; @endphp @endif">
                                <a href="{{url('configuration')}}" class="menu-link">
                                    <span class="menu-icon">
                                        <i class="fe-settings"></i>
                                    </span>
                                    <span class="menu-text"> Ajustes </span>
                                    @php $selected = null; @endphp
                                </a>
                            </li>
                        @endif

                        @endif


                        {{-- <li class="menu-item">
                            <a href="#menuCrm" data-bs-toggle="collapse" class="menu-link">
                                <span class="menu-icon"><i data-feather="users"></i></span>
                                <span class="menu-text"> CRM </span>
                                <span class="menu-arrow"></span>
                            </a>
                            <div class="collapse" id="menuCrm">
                                <ul class="sub-menu">
                                    <li class="menu-item">
                                        <a href="crm-dashboard.html" class="menu-link">
                                            <span class="menu-text">Dashboard</span>
                                        </a>
                                    </li>
                                    <li class="menu-item">
                                        <a href="crm-contacts.html" class="menu-link">
                                            <span class="menu-text">Contacts</span>
                                        </a>
                                    </li>
                                    <li class="menu-item">
                                        <a href="crm-opportunities.html" class="menu-link">
                                            <span class="menu-text">Opportunities</span>
                                        </a>
                                    </li>
                                    <li class="menu-item">
                                        <a href="crm-leads.html" class="menu-link">
                                            <span class="menu-text">Leads</span>
                                        </a>
                                    </li>
                                    <li class="menu-item">
                                        <a href="crm-customers.html" class="menu-link">
                                            <span class="menu-text">Customers</span>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </li> --}}

                        
                    </ul>
                    <!--- End Menu -->
                    <div class="clearfix"></div>
                </div>
            </div>
            <!-- ========== Left menu End ========== -->

            

            

            <!-- ============================================================== -->
            <!-- Start Page Content here -->
            <!-- ============================================================== -->

            <div class="content-page">

                <!-- ========== Topbar Start ========== -->
                <div class="navbar-custom">
                    <div class="topbar">
                        <div class="topbar-menu d-flex align-items-center gap-1">

                            <!-- Topbar Brand Logo -->
                            <div class="logo-box">
                                <!-- Brand Logo Light -->
                                <a href="{{url('/dashboard')}}" class="logo-light">
                                    {{-- <img src="{{url('default')}}/assets/images/logo-light.png" alt="logo" class="logo-lg">
                                    <img src="{{url('default')}}/assets/images/logo-sm.png" alt="small logo" class="logo-sm"> --}}

                                    <img src="{{url('/')}}/logo_menu.svg" alt="logo" class="logo-lg">
                                    <img src="{{url('/')}}/logo.svg" alt="small logo" class="logo-sm">
                                </a>

                                <!-- Brand Logo Dark -->
                                <a href="{{url('/dashboard')}}" class="logo-dark">
                                    {{-- <img src="{{url('default')}}/assets/images/logo-dark.png" alt="dark logo" class="logo-lg">
                                    <img src="{{url('default')}}/assets/images/logo-sm.png" alt="small logo" class="logo-sm"> --}}

                                    <img src="{{url('/')}}/logo_menu.svg" alt="logo" class="logo-lg">
                                    <img src="{{url('/')}}/logo.svg" alt="small logo" class="logo-sm">
                                </a>
                            </div>

                            <!-- Sidebar Menu Toggle Button -->
                            <button class="button-toggle-menu">
                                <i class="mdi mdi-menu"></i>
                            </button>
                            
                        </div>

                        <ul class="topbar-menu d-flex align-items-center">
                            <!-- Topbar Search Form: búsqueda por número de referencia -->
                            <li class="app-search d-none d-lg-block position-relative" id="top-search-wrap">
                                <form class="position-relative" role="search" onsubmit="return false;">
                                    <input type="search" class="form-control rounded-pill" placeholder="Buscar por referencia..." id="top-search" autocomplete="off"
                                           data-search-url="{{ route('participations.search-by-reference') }}"
                                           data-min-chars="{{ config('partilot.search_min_chars', 16) }}">
                                    <span class="fe-search search-icon font-16" aria-hidden="true"></span>
                                </form>
                                <div class="dropdown-menu dropdown-menu-animated dropdown-lg shadow" id="search-dropdown" 
                                     style="width: 500px; min-width: 500px; max-width: 500px; max-height: 500px; overflow-y: auto; overflow-x: hidden; display: none; position: absolute; top: 100%; left: 0; z-index: 1050; margin-top: 0.5rem; visibility: hidden;">
                                    <div class="dropdown-header noti-title py-2">
                                        <h6 class="text-overflow mb-0" id="search-dropdown-title">Escribe al menos {{ config('partilot.search_min_chars', 16) }} caracteres</h6>
                                    </div>
                                    <div class="notification-list" id="search-results"></div>
                                </div>
                            </li>

                            @if(!empty($entitySwitcherOptions))
                            <li class="dropdown d-none d-md-block partilot-entity-switcher">
                                <a class="nav-link dropdown-toggle waves-effect waves-light arrow-none" data-bs-toggle="dropdown" href="#" role="button" aria-haspopup="true" aria-expanded="false" title="Cambiar entidad activa">
                                    <i class="ri-building-2-line font-22"></i>
                                </a>
                                <div class="dropdown-menu dropdown-menu-end dropdown-menu-animated shadow partilot-entity-switcher__menu">
                                    <div class="dropdown-header noti-title">
                                        <h6 class="mb-0">Entidad activa</h6>
                                    </div>
                                    @foreach($entitySwitcherOptions as $option)
                                        <form method="POST" action="{{ route('panel.switch-entity') }}" class="d-block m-0">
                                            @csrf
                                            <input type="hidden" name="entity_id" value="{{ $option['id'] }}">
                                            <button type="submit" class="dropdown-item notify-item d-flex flex-column align-items-start gap-0 py-2 {{ (int) $activeEntitySwitcherId === (int) $option['id'] ? 'active fw-semibold' : '' }}">
                                                <span class="partilot-entity-switcher__name">{{ $option['name'] }}</span>
                                                <small class="text-muted">{{ $option['role_label'] }}</small>
                                            </button>
                                        </form>
                                    @endforeach
                                </div>
                            </li>
                            @endif

                            <!-- Fullscreen Button -->
                            {{-- <li class="d-none d-md-inline-block">
                                <a class="nav-link waves-effect waves-light" href="#" data-toggle="fullscreen">
                                    <i class="fe-maximize font-22"></i>
                                </a>
                            </li> --}}

                            <!-- Search Dropdown (for Mobile/Tablet) -->
                            {{-- <li class="dropdown d-lg-none">
                                <a class="nav-link dropdown-toggle waves-effect waves-light arrow-none" data-bs-toggle="dropdown" href="#" role="button" aria-haspopup="false" aria-expanded="false">
                                    <i class="ri-search-line font-22"></i>
                                </a>
                                <div class="dropdown-menu dropdown-menu-animated dropdown-lg p-0">
                                    <form class="p-3">
                                        <input type="search" class="form-control" placeholder="Search ..." aria-label="Recipient's username">
                                    </form>
                                </div>
                            </li> --}}

                            {{-- <!-- App Dropdown -->
                            <li class="dropdown d-none d-md-inline-block">
                                <a class="nav-link dropdown-toggle waves-effect waves-light arrow-none" data-bs-toggle="dropdown" href="#" role="button" aria-haspopup="false" aria-expanded="false">
                                    <i class="fe-grid font-22"></i>
                                </a>
                                <div class="dropdown-menu dropdown-menu-end dropdown-menu-animated dropdown-lg p-0">

                                    <div class="p-2">
                                        <div class="row g-0">
                                            <div class="col">
                                                <a class="dropdown-icon-item" href="#">
                                                    <img src="{{url('default')}}/assets/images/brands/slack.png" alt="slack">
                                                    <span>Slack</span>
                                                </a>
                                            </div>
                                            <div class="col">
                                                <a class="dropdown-icon-item" href="#">
                                                    <img src="{{url('default')}}/assets/images/brands/github.png" alt="Github">
                                                    <span>GitHub</span>
                                                </a>
                                            </div>
                                            <div class="col">
                                                <a class="dropdown-icon-item" href="#">
                                                    <img src="{{url('default')}}/assets/images/brands/dribbble.png" alt="dribbble">
                                                    <span>Dribbble</span>
                                                </a>
                                            </div>
                                        </div>

                                        <div class="row g-0">
                                            <div class="col">
                                                <a class="dropdown-icon-item" href="#">
                                                    <img src="{{url('default')}}/assets/images/brands/bitbucket.png" alt="bitbucket">
                                                    <span>Bitbucket</span>
                                                </a>
                                            </div>
                                            <div class="col">
                                                <a class="dropdown-icon-item" href="#">
                                                    <img src="{{url('default')}}/assets/images/brands/dropbox.png" alt="dropbox">
                                                    <span>Dropbox</span>
                                                </a>
                                            </div>
                                            <div class="col">
                                                <a class="dropdown-icon-item" href="#">
                                                    <img src="{{url('default')}}/assets/images/brands/g-suite.png" alt="G Suite">
                                                    <span>G Suite</span>
                                                </a>
                                            </div>
                                        </div> <!-- end row-->
                                    </div>
                                </div>
                            </li>

                            <!-- Language flag dropdown  -->
                            <li class="dropdown d-none d-md-inline-block">
                                <a class="nav-link dropdown-toggle waves-effect waves-light arrow-none" data-bs-toggle="dropdown" href="#" role="button" aria-haspopup="false" aria-expanded="false">
                                    <img src="{{url('default')}}/assets/images/flags/us.jpg" alt="user-image" class="me-0 me-sm-1" height="18">
                                </a>
                                <div class="dropdown-menu dropdown-menu-end dropdown-menu-animated">

                                    <!-- item-->
                                    <a href="javascript:void(0);" class="dropdown-item">
                                        <img src="{{url('default')}}/assets/images/flags/germany.jpg" alt="user-image" class="me-1" height="12"> <span class="align-middle">German</span>
                                    </a>

                                    <!-- item-->
                                    <a href="javascript:void(0);" class="dropdown-item">
                                        <img src="{{url('default')}}/assets/images/flags/italy.jpg" alt="user-image" class="me-1" height="12"> <span class="align-middle">Italian</span>
                                    </a>

                                    <!-- item-->
                                    <a href="javascript:void(0);" class="dropdown-item">
                                        <img src="{{url('default')}}/assets/images/flags/spain.jpg" alt="user-image" class="me-1" height="12"> <span class="align-middle">Spanish</span>
                                    </a>

                                    <!-- item-->
                                    <a href="javascript:void(0);" class="dropdown-item">
                                        <img src="{{url('default')}}/assets/images/flags/russia.jpg" alt="user-image" class="me-1" height="12"> <span class="align-middle">Russian</span>
                                    </a>

                                </div>
                            </li> --}}

                            <!-- Notificaciones (enlace al módulo; contador en futuras iteraciones) -->
                            <li class="topbar-btn-notifications">
                                <a class="nav-link waves-effect waves-light" href="{{ route('notifications.index') }}" title="Notificaciones">
                                    <i class="fe-bell font-22"></i>
                                </a>
                            </li>

                            <!-- Light/Dark Mode Toggle Button -->
                            {{-- <li class="d-none d-sm-inline-block">
                                <div class="nav-link waves-effect waves-light" id="light-dark-mode">
                                    <i class="ri-moon-line font-22"></i>
                                </div>
                            </li> --}}

                            <!-- User Dropdown -->
                            <li class="dropdown">
                                <a class="nav-link dropdown-toggle nav-user me-0 waves-effect waves-light d-flex align-items-center" data-bs-toggle="dropdown" href="#" role="button" aria-haspopup="false" aria-expanded="false">
                                    @php $panelHeaderImgTop = Auth::user()?->panelAccountHeaderImageUrl(); @endphp
                                    <img src="{{ $panelHeaderImgTop ?? url('default').'/assets/images/users/user-1.jpg' }}" alt="" class="rounded-circle" style="width:36px;height:36px;object-fit:cover;">
                                    @php
                                        $topbarRole = 'USUARIO';
                                        if (Auth::check()) {
                                            if (Auth::user()->isSuperAdmin()) {
                                                $topbarRole = 'SUPER ADMINISTRADOR';
                                            } elseif (Auth::user()->isAdministration()) {
                                                $topbarRole = 'ADMINISTRADOR';
                                            } elseif (Auth::user()->isEntity()) {
                                                $topbarRole = 'ENTIDAD';
                                            }
                                        }
                                    @endphp
                                    @php $topbarContextLabel = Auth::user()?->panelHeaderContextLabel(); @endphp
                                    <span class="ms-2 d-none d-md-inline-block text-start">
                                        @if($topbarContextLabel)
                                            <span class="topbar-user-context d-block" title="{{ $topbarContextLabel }}">{{ $topbarContextLabel }}</span>
                                        @endif
                                        <span class="user-name">{{ Auth::user()->name ? Auth::user()->name.' '.Auth::user()->last_name : 'Usuario' }}</span>
                                        <span class="user-role">{{ $topbarRole }}</span>
                                        <i class="mdi mdi-chevron-down"></i>
                                    </span>
                                </a>
                                <div class="dropdown-menu dropdown-menu-end profile-dropdown ">
                                    <div class="dropdown-header noti-title">
                                        <h6 class="text-overflow m-0">{{ Auth::user()->name ? Auth::user()->name.' '.Auth::user()->last_name : 'Usuario' }}</h6>
                                        @if($topbarContextLabel)
                                            <small class="text-muted">{{ $topbarContextLabel }}</small>
                                        @endif
                                    </div>

                                    @if(Auth::check() && Auth::user()->isPanelAccount() && Auth::user()->panel_account_type === 'administration')
                                    <a href="{{ route('account.my-data') }}" class="dropdown-item notify-item">
                                        <i class="fe-user"></i>
                                        <span>Mis datos</span>
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    @endif

                                    <form method="POST" action="{{ url('logout') }}">
                                        @csrf
                                        <button type="submit" class="dropdown-item notify-item text-danger" style="background: none; border: none; width: 100%; text-align: left;">
                                            <i class="fe-log-out me-1"></i>
                                            <span>Cerrar sesión</span>
                                        </button>
                                    </form>

                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
                <!-- ========== Topbar End ========== -->

                <div class="content">

                    @if(Auth::check() && Auth::user()->isEntityPanelReadOnly())
                        <div class="alert alert-info border-0 rounded-0 mb-0 text-center py-2" role="alert" style="font-size: 0.9rem;">
                            <strong>Modo solo consulta.</strong>
                            La cuenta de panel de la entidad puede revisar la misma información que el gestor; no puede realizar cambios (tramitación solo con el usuario del gestor responsable).
                        </div>
                    @endif

                    @yield('content')

                </div> <!-- content -->

                @if (!request()->is('configuration*'))
                    <!-- Footer Start -->
                    @php
                        $isDashboardPanel = request()->routeIs('dashboard');
                        $footerStyle = $isDashboardPanel
                            ? 'position:relative!important;bottom:auto!important;left:auto!important;right:auto!important;width:auto!important;height:60px!important;flex-shrink:0!important;margin:-1px 27px 24px!important;padding:0 1.5rem!important;border:1px solid #d9dde8!important;border-top:0!important;border-radius:0 0 18px 18px!important;background:#fff!important;box-shadow:none!important;align-items:center!important;'
                            : 'position:relative!important;bottom:auto!important;left:auto!important;right:auto!important;width:auto!important;height:60px!important;flex-shrink:0!important;margin:-1px 27px 24px!important;padding:0 1.5rem!important;border:1px solid #d9dde8!important;border-top:0!important;border-radius:0 0 18px 18px!important;background:#fff!important;box-shadow:none!important;display:flex!important;align-items:center!important;';
                    @endphp
                    <footer class="footer" style="{{ $footerStyle }}">
                        <div class="container-fluid" style="width:100%;padding-left:0!important;padding-right:0!important;">
                            <div class="row align-items-center" style="margin:0;width:100%;">
                                <div class="col-md-6">
                                    <div><script>document.write(new Date().getFullYear())</script> © Partilot - <a href="https://partilot.es/" target="_blank">partilot.es</a></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-none d-md-flex gap-4 align-items-center justify-content-md-end footer-links">
                                        <a href="javascript: void(0);">About</a>
                                        <a href="javascript: void(0);">Support</a>
                                        <a href="javascript: void(0);">Contact Us</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </footer>
                    <!-- end Footer -->
                @endif

            </div>

            <!-- ============================================================== -->
            <!-- End Page content -->
            <!-- ============================================================== -->

        </div>
        <!-- END wrapper -->

        <!-- Theme Settings -->
        <div class="offcanvas offcanvas-end right-bar" tabindex="-1" id="theme-settings-offcanvas">
            <div class="d-flex align-items-center w-100 p-0 offcanvas-header">
                <!-- Nav tabs -->
                <ul class="nav nav-tabs nav-bordered nav-justified w-100" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link py-2" data-bs-toggle="tab" href="#chat-tab" role="tab">
                            <i class="mdi mdi-message-text d-block font-22 my-1"></i>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link py-2" data-bs-toggle="tab" href="#tasks-tab" role="tab">
                            <i class="mdi mdi-format-list-checkbox d-block font-22 my-1"></i>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link py-2 active" data-bs-toggle="tab" href="#settings-tab" role="tab">
                            <i class="mdi mdi-cog-outline d-block font-22 my-1"></i>
                        </a>
                    </li>
                </ul>
            </div>

            <div class="offcanvas-body p-3 h-100" data-simplebar>
                <!-- Tab panes -->
                <div class="tab-content pt-0">
                    <div class="tab-pane" id="chat-tab" role="tabpanel">

                        <form class="search-bar">
                            <div class="position-relative">
                                <input type="text" class="form-control" placeholder="Buscar...">
                                <span class="mdi mdi-magnify"></span>
                            </div>
                        </form>

                        <h6 class="fw-medium mt-2 text-uppercase">Group Chats</h6>

                        <div>
                            <a href="javascript: void(0);" class="text-reset notification-item ps-3 mb-2 d-block">
                                <i class="mdi mdi-checkbox-blank-circle-outline me-1 text-success"></i>
                                <span class="mb-0 mt-1">App Development</span>
                            </a>

                            <a href="javascript: void(0);" class="text-reset notification-item ps-3 mb-2 d-block">
                                <i class="mdi mdi-checkbox-blank-circle-outline me-1 text-warning"></i>
                                <span class="mb-0 mt-1">Office Work</span>
                            </a>

                            <a href="javascript: void(0);" class="text-reset notification-item ps-3 mb-2 d-block">
                                <i class="mdi mdi-checkbox-blank-circle-outline me-1 text-danger"></i>
                                <span class="mb-0 mt-1">Personal Group</span>
                            </a>

                            <a href="javascript: void(0);" class="text-reset notification-item ps-3 d-block">
                                <i class="mdi mdi-checkbox-blank-circle-outline me-1"></i>
                                <span class="mb-0 mt-1">Freelance</span>
                            </a>
                        </div>

                        <h6 class="fw-medium mt-3 text-uppercase">Favourites <a href="javascript: void(0);" class="font-18 text-danger"><i class="float-end mdi mdi-plus-circle"></i></a></h6>

                        <div>
                            <a href="javascript: void(0);" class="text-reset notification-item">
                                <div class="d-flex align-items-start noti-user-item">
                                    <div class="position-relative me-2">
                                        <img src="{{url('default')}}/assets/images/users/user-10.jpg" class="rounded-circle avatar-sm" alt="user-pic">
                                        <i class="mdi mdi-circle user-status online"></i>
                                    </div>
                                    <div class="overflow-hidden">
                                        <h6 class="mt-0 mb-1 font-14">Andrew Mackie</h6>
                                        <div class="font-13 text-muted">
                                            <p class="mb-0 text-truncate">It will seem like simplified English.</p>
                                        </div>
                                    </div>
                                </div>
                            </a>

                            <a href="javascript: void(0);" class="text-reset notification-item">
                                <div class="d-flex align-items-start noti-user-item">
                                    <div class="position-relative me-2">
                                        <img src="{{url('default')}}/assets/images/users/user-1.jpg" class="rounded-circle avatar-sm" alt="user-pic">
                                        <i class="mdi mdi-circle user-status away"></i>
                                    </div>
                                    <div class="overflow-hidden">
                                        <h6 class="mt-0 mb-1 font-14">Rory Dalyell</h6>
                                        <div class="font-13 text-muted">
                                            <p class="mb-0 text-truncate">To an English person, it will seem like simplified</p>
                                        </div>
                                    </div>
                                </div>
                            </a>

                            <a href="javascript: void(0);" class="text-reset notification-item">
                                <div class="d-flex align-items-start noti-user-item">
                                    <div class="position-relative me-2">
                                        <img src="{{url('default')}}/assets/images/users/user-9.jpg" class="rounded-circle avatar-sm" alt="user-pic">
                                        <i class="mdi mdi-circle user-status busy"></i>
                                    </div>
                                    <div class="overflow-hidden">
                                        <h6 class="mt-0 mb-1 font-14">Jaxon Dunhill</h6>
                                        <div class="font-13 text-muted">
                                            <p class="mb-0 text-truncate">To achieve this, it would be necessary.</p>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>

                        <h6 class="fw-medium mt-3 text-uppercase">Other Chats <a href="javascript: void(0);" class="font-18 text-danger"><i class="float-end mdi mdi-plus-circle"></i></a></h6>

                        <div class="pb-4">
                            <a href="javascript: void(0);" class="text-reset notification-item">
                                <div class="d-flex align-items-start noti-user-item">
                                    <div class="position-relative me-2">
                                        <img src="{{url('default')}}/assets/images/users/user-2.jpg" class="rounded-circle avatar-sm" alt="user-pic">
                                        <i class="mdi mdi-circle user-status online"></i>
                                    </div>
                                    <div class="overflow-hidden">
                                        <h6 class="mt-0 mb-1 font-14">Jackson Therry</h6>
                                        <div class="font-13 text-muted">
                                            <p class="mb-0 text-truncate">Everyone realizes why a new common language.</p>
                                        </div>
                                    </div>
                                </div>
                            </a>

                            <a href="javascript: void(0);" class="text-reset notification-item">
                                <div class="d-flex align-items-start noti-user-item">
                                    <div class="position-relative me-2">
                                        <img src="{{url('default')}}/assets/images/users/user-4.jpg" class="rounded-circle avatar-sm" alt="user-pic">
                                        <i class="mdi mdi-circle user-status away"></i>
                                    </div>
                                    <div class="overflow-hidden">
                                        <h6 class="mt-0 mb-1 font-14">Charles Deakin</h6>
                                        <div class="font-13 text-muted">
                                            <p class="mb-0 text-truncate">The languages only differ in their grammar.</p>
                                        </div>
                                    </div>
                                </div>
                            </a>

                            <a href="javascript: void(0);" class="text-reset notification-item">
                                <div class="d-flex align-items-start noti-user-item">
                                    <div class="position-relative me-2">
                                        <img src="{{url('default')}}/assets/images/users/user-5.jpg" class="rounded-circle avatar-sm" alt="user-pic">
                                        <i class="mdi mdi-circle user-status online"></i>
                                    </div>
                                    <div class="overflow-hidden">
                                        <h6 class="mt-0 mb-1 font-14">Ryan Salting</h6>
                                        <div class="font-13 text-muted">
                                            <p class="mb-0 text-truncate">If several languages coalesce the grammar of the resulting.</p>
                                        </div>
                                    </div>
                                </div>
                            </a>

                            <a href="javascript: void(0);" class="text-reset notification-item">
                                <div class="d-flex align-items-start noti-user-item">
                                    <div class="position-relative me-2">
                                        <img src="{{url('default')}}/assets/images/users/user-6.jpg" class="rounded-circle avatar-sm" alt="user-pic">
                                        <i class="mdi mdi-circle user-status online"></i>
                                    </div>
                                    <div class="overflow-hidden">
                                        <h6 class="mt-0 mb-1 font-14">Sean Howse</h6>
                                        <div class="font-13 text-muted">
                                            <p class="mb-0 text-truncate">It will seem like simplified English.</p>
                                        </div>
                                    </div>
                                </div>
                            </a>

                            <a href="javascript: void(0);" class="text-reset notification-item">
                                <div class="d-flex align-items-start noti-user-item">
                                    <div class="position-relative me-2">
                                        <img src="{{url('default')}}/assets/images/users/user-7.jpg" class="rounded-circle avatar-sm" alt="user-pic">
                                        <i class="mdi mdi-circle user-status busy"></i>
                                    </div>
                                    <div class="overflow-hidden">
                                        <h6 class="mt-0 mb-1 font-14">Dean Coward</h6>
                                        <div class="font-13 text-muted">
                                            <p class="mb-0 text-truncate">The new common language will be more simple.</p>
                                        </div>
                                    </div>
                                </div>
                            </a>

                            <a href="javascript: void(0);" class="text-reset notification-item">
                                <div class="d-flex align-items-start noti-user-item">
                                    <div class="position-relative me-2">
                                        <img src="{{url('default')}}/assets/images/users/user-8.jpg" class="rounded-circle avatar-sm" alt="user-pic">
                                        <i class="mdi mdi-circle user-status away"></i>
                                    </div>
                                    <div class="overflow-hidden">
                                        <h6 class="mt-0 mb-1 font-14">Hayley East</h6>
                                        <div class="font-13 text-muted">
                                            <p class="mb-0 text-truncate">One could refuse to pay expensive translators.</p>
                                        </div>
                                    </div>
                                </div>
                            </a>

                            <div class="text-center mt-3">
                                <a href="javascript:void(0);" class="btn btn-sm btn-white">
                                    <i class="mdi mdi-spin mdi-loading me-2"></i>
                                    Load more
                                </a>
                            </div>
                        </div>

                    </div>

                    <div class="tab-pane" id="tasks-tab" role="tabpanel">
                        <h6 class="fw-medium p-3 m-0 text-uppercase">Working Tasks</h6>
                        <div class="px-2">
                            <a href="javascript: void(0);" class="text-reset item-hovered d-block p-2">
                                <p class="text-muted mb-0">App Development<span class="float-end">75%</span></p>
                                <div class="progress mt-2" style="height: 4px;">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: 75%" aria-valuenow="75" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </a>

                            <a href="javascript: void(0);" class="text-reset item-hovered d-block p-2">
                                <p class="text-muted mb-0">Database Repair<span class="float-end">37%</span></p>
                                <div class="progress mt-2" style="height: 4px;">
                                    <div class="progress-bar bg-info" role="progressbar" style="width: 37%" aria-valuenow="37" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </a>

                            <a href="javascript: void(0);" class="text-reset item-hovered d-block p-2">
                                <p class="text-muted mb-0">Backup Create<span class="float-end">52%</span></p>
                                <div class="progress mt-2" style="height: 4px;">
                                    <div class="progress-bar bg-warning" role="progressbar" style="width: 52%" aria-valuenow="52" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </a>
                        </div>

                        <h6 class="fw-medium mb-0 mt-4 text-uppercase">Upcoming Tasks</h6>

                        <div>
                            <a href="javascript: void(0);" class="text-reset item-hovered d-block p-2">
                                <p class="text-muted mb-0">Sales Reporting<span class="float-end">12%</span></p>
                                <div class="progress mt-2" style="height: 4px;">
                                    <div class="progress-bar bg-danger" role="progressbar" style="width: 12%" aria-valuenow="12" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </a>

                            <a href="javascript: void(0);" class="text-reset item-hovered d-block p-2">
                                <p class="text-muted mb-0">Redesign Website<span class="float-end">67%</span></p>
                                <div class="progress mt-2" style="height: 4px;">
                                    <div class="progress-bar bg-primary" role="progressbar" style="width: 67%" aria-valuenow="67" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </a>

                            <a href="javascript: void(0);" class="text-reset item-hovered d-block p-2">
                                <p class="text-muted mb-0">New Admin Design<span class="float-end">84%</span></p>
                                <div class="progress mt-2" style="height: 4px;">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: 84%" aria-valuenow="84" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </a>
                        </div>

                        <div class="p-3 mt-2 d-grid">
                            <a href="javascript: void(0);" class="btn btn-success waves-effect waves-light">Create Task</a>
                        </div>

                    </div>

                    <div class="tab-pane active" id="settings-tab" role="tabpanel">

                        <div class="mt-n3">
                            <h6 class="fw-medium py-2 px-3 font-13 text-uppercase bg-light mx-n3 mt-n3 mb-3">
                                <span class="d-block py-1">Theme Settings</span>
                            </h6>
                        </div>

                        <div class="alert alert-warning" role="alert">
                            <strong>Customize </strong> the overall color scheme, sidebar menu, etc.
                        </div>

                        <h5 class="fw-medium font-14 mt-4 mb-2 pb-1">Color Scheme</h5>

                        <div class="colorscheme-cardradio">
                            <div class="d-flex flex-column gap-2">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="data-bs-theme" id="layout-color-light" value="light">
                                    <label class="form-check-label" for="layout-color-light">Light</label>
                                </div>

                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="data-bs-theme" id="layout-color-dark" value="dark">
                                    <label class="form-check-label" for="layout-color-dark">Dark</label>
                                </div>
                            </div>
                        </div>

                        <h5 class="fw-medium font-14 mt-4 mb-2 pb-1">Content Width</h5>
                        <div class="d-flex flex-column gap-2">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="data-layout-width" id="layout-width-default" value="default">
                                <label class="form-check-label" for="layout-width-default">Fluid (Default)</label>
                            </div>

                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="data-layout-width" id="layout-width-boxed" value="boxed">
                                <label class="form-check-label" for="layout-width-boxed">Boxed</label>
                            </div>
                        </div>

                        <div id="layout-mode">
                            <h5 class="fw-medium font-14 mt-4 mb-2 pb-1">Layout Mode</h5>

                            <div class="d-flex flex-column gap-2">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="data-layout-mode" id="layout-mode-default" value="default">
                                    <label class="form-check-label" for="layout-mode-default">Default</label>
                                </div>


                                <div id="layout-detached">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="data-layout-mode" id="layout-mode-detached" value="detached">
                                        <label class="form-check-label" for="layout-mode-detached">Detached</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <h5 class="fw-medium font-14 mt-4 mb-2 pb-1">Topbar Color</h5>

                        <div class="d-flex flex-column gap-2">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="data-topbar-color" id="topbar-color-light" value="light">
                                <label class="form-check-label" for="topbar-color-light">Light</label>
                            </div>

                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="data-topbar-color" id="topbar-color-dark" value="dark">
                                <label class="form-check-label" for="topbar-color-dark">Dark</label>
                            </div>

                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="data-topbar-color" id="topbar-color-brand" value="brand">
                                <label class="form-check-label" for="topbar-color-brand">Brand</label>
                            </div>
                        </div>

                        <div>
                            <h5 class="fw-medium font-14 mt-4 mb-2 pb-1">Menu Color</h5>

                            <div class="d-flex flex-column gap-2">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="data-menu-color" id="leftbar-color-light" value="light">
                                    <label class="form-check-label" for="leftbar-color-light">Light</label>
                                </div>

                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="data-menu-color" id="leftbar-color-dark" value="dark">
                                    <label class="form-check-label" for="leftbar-color-dark">Dark</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="data-menu-color" id="leftbar-color-brand" value="brand">
                                    <label class="form-check-label" for="leftbar-color-brand">Brand</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="data-menu-color" id="leftbar-color-gradient" value="gradient">
                                    <label class="form-check-label" for="leftbar-color-gradient">Gradient</label>
                                </div>
                            </div>
                        </div>

                        <div id="menu-icon-color">
                            <h5 class="fw-medium font-14 mt-4 mb-2 pb-1">Menu Icon Color</h5>

                            <div class="d-flex flex-column gap-2">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="data-two-column-color" id="twocolumn-menu-color-light" value="light">
                                    <label class="form-check-label" for="twocolumn-menu-color-light">Light</label>
                                </div>

                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="data-two-column-color" id="twocolumn-menu-color-dark" value="dark">
                                    <label class="form-check-label" for="twocolumn-menu-color-dark">Dark</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="data-two-column-color" id="twocolumn-menu-color-brand" value="brand">
                                    <label class="form-check-label" for="twocolumn-menu-color-brand">Brand</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="data-two-column-color" id="twocolumn-menu-color-gradient" value="gradient">
                                    <label class="form-check-label" for="twocolumn-menu-color-gradient">Gradient</label>
                                </div>
                            </div>
                        </div>

                        <div>
                            <h5 class="fw-medium font-14 mt-4 mb-2 pb-1">Menu Icon Tone</h5>

                            <div class="d-flex flex-column gap-2">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="data-menu-icon" id="menu-icon-default" value="default">
                                    <label class="form-check-label" for="menu-icon-default">Default</label>
                                </div>

                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="data-menu-icon" id="menu-icon-twotone" value="twotones">
                                    <label class="form-check-label" for="menu-icon-twotone">Twotone</label>
                                </div>
                            </div>
                        </div>

                        <div id="sidebar-size">
                            <h5 class="fw-medium font-14 mt-4 mb-2 pb-1">Sidebar Size</h5>

                            <div class="d-flex flex-column gap-2">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="data-sidenav-size" id="leftbar-size-default" value="default">
                                    <label class="form-check-label" for="leftbar-size-default">Default</label>
                                </div>

                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="data-sidenav-size" id="leftbar-size-compact" value="compact">
                                    <label class="form-check-label" for="leftbar-size-compact">Compact (Medium Width)</label>
                                </div>

                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="data-sidenav-size" id="leftbar-size-small" value="condensed">
                                    <label class="form-check-label" for="leftbar-size-small">Condensed (Icon View)</label>
                                </div>

                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="data-sidenav-size" id="leftbar-size-full" value="full">
                                    <label class="form-check-label" for="leftbar-size-full">Full Layout</label>
                                </div>

                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="data-sidenav-size" id="leftbar-size-fullscreen" value="fullscreen">
                                    <label class="form-check-label" for="leftbar-size-fullscreen">Fullscreen Layout</label>
                                </div>
                            </div>
                        </div>

                        <div id="sidebar-user">
                            <h5 class="fw-medium font-14 mt-4 mb-2 pb-1">Sidebar User Info</h5>

                            <div class="form-check form-switch">
                                <input type="checkbox" class="form-check-input" name="data-sidebar-user" id="sidebaruser-check">
                                <label class="form-check-label" for="sidebaruser-check">Enable</label>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <div class="offcanvas-footer border-top py-2 px-2 text-center">
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-light w-50" onclick="resetThemeSettings()">Reset</button>
                    <button type="button" class="btn btn-primary w-50" onclick="location.reload()">Aplicar</button>
                </div>
            </div>
        </div>
        
        <!-- Vendor js -->
        <script src="{{url('default')}}/assets/js/vendor.min.js"></script>
        <script src="{{ url('assets/libs/pnotify/pnotify.js') }}"></script>
        <script src="{{ url('assets/libs/pnotify/pnotify.buttons.js') }}"></script>
        @include('partials.partilot-flash-notify')

        <!-- App js -->
        <script src="{{url('default')}}/assets/js/app.min.js"></script>
        
        <!-- Plugins js-->
        <script src="{{url('assets')}}/libs/flatpickr/flatpickr.min.js"></script>
        <script src="{{url('assets')}}/libs/apexcharts/apexcharts.min.js"></script>
        <script src="{{url('assets')}}/libs/selectize/js/standalone/selectize.min.js"></script>

        <!-- Dashboar 1 init js-->
        <script src="{{url('default')}}/assets/js/pages/dashboard-1.init.js"></script>


        {{-- /**/ --}}

        <!-- third party js -->
        <script src="{{url('assets')}}/libs/datatables.net/js/jquery.dataTables.min.js"></script>
        <script src="{{url('assets')}}/libs/datatables.net-bs5/js/dataTables.bootstrap5.min.js"></script>
        <script src="{{url('assets')}}/libs/datatables.net-responsive/js/dataTables.responsive.min.js"></script>
        <script src="{{url('assets')}}/libs/datatables.net-responsive-bs5/js/responsive.bootstrap5.min.js"></script>
        <script src="{{url('assets')}}/libs/datatables.net-buttons/js/dataTables.buttons.min.js"></script>
        <script src="{{url('assets')}}/libs/datatables.net-buttons-bs5/js/buttons.bootstrap5.min.js"></script>
        <script src="{{url('assets')}}/libs/datatables.net-buttons/js/buttons.html5.min.js"></script>
        <script src="{{url('assets')}}/libs/datatables.net-buttons/js/buttons.flash.min.js"></script>
        <script src="{{url('assets')}}/libs/datatables.net-buttons/js/buttons.print.min.js"></script>
        <script src="{{url('assets')}}/libs/datatables.net-keytable/js/dataTables.keyTable.min.js"></script>
        <script src="{{url('assets')}}/libs/datatables.net-select/js/dataTables.select.min.js"></script>
        <script src="{{url('assets')}}/libs/pdfmake/build/pdfmake.min.js"></script>
        <script src="{{url('assets')}}/libs/pdfmake/build/vfs_fonts.js"></script>

        <script src="{{ url('assets/libs/jquery-ui/jquery-ui.js') }}"></script>
        <!-- third party js ends -->

        <!-- Datatables init -->
        <script src="{{url('default')}}/assets/js/pages/datatables.init.js"></script>
        <script src="{{url('js/partilot-datatables.js')}}"></script>

        {{-- <script src="https://cdn.ckeditor.com/ckeditor5/45.2.0/ckeditor5.umd.js" crossorigin></script>
        <script src="https://cdn.ckeditor.com/ckeditor5/45.2.0/translations/es.umd.js" crossorigin></script>
        <script src="{{url('main.js')}}"></script> --}}

        <script src="{{url('ckeditor/ckeditor.js')}}"></script>
        <script src="{{url('ckeditor/adapters/jquery.js')}}"></script>

        <!-- Firebase Notifications (tras banner L2 si aplica) -->
        @include('partials.partilot-cookie-consent-state')
        @include('partials.legal-analytics-scripts')
        <script>
            window.partilotDeferFirebaseUntilCookieBanner = @json((bool) config('legal.defer_firebase_until_cookie_banner', true));
        </script>
        <script src="{{url('js/firebase-notifications.js')}}" defer></script>

        <!-- Image Persistence -->
        <script src="{{url('js/image-persistence.js')}}"></script>

        <!-- Spanish Document Validator -->
        <script src="{{url('js/spanish-document-validator.js')}}"></script>
        <script src="{{url('js/partilot-date-input.js')}}"></script>
        
        <!-- Email Validator -->
        <script src="{{url('js/email-validator.js')}}"></script>

        <script>
            (function () {
                var MIN_VISIBLE_MS = 400;
                var MAX_WAIT_MS = 10000;
                var startedAt = Date.now();
                var finished = false;

                function hidePartilotPreloader() {
                    if (finished) {
                        return;
                    }
                    finished = true;

                    var elapsed = Date.now() - startedAt;
                    var delay = Math.max(0, MIN_VISIBLE_MS - elapsed);

                    window.setTimeout(function () {
                        var preloader = document.getElementById('partilot-preloader');
                        document.documentElement.classList.remove('partilot-loading');

                        if (!preloader) {
                            window.setTimeout(function () {
                                if (typeof window.partilotShowPageFlashes === 'function') {
                                    window.partilotShowPageFlashes();
                                }
                            }, 0);
                            return;
                        }

                        preloader.classList.add('partilot-preloader--hide');
                        window.setTimeout(function () {
                            if (preloader.parentNode) {
                                preloader.parentNode.removeChild(preloader);
                            }
                            window.setTimeout(function () {
                                if (typeof window.partilotShowPageFlashes === 'function') {
                                    window.partilotShowPageFlashes();
                                }
                            }, 0);
                        }, 380);
                    }, delay);
                }

                function whenReady() {
                    var loadDone = new Promise(function (resolve) {
                        if (document.readyState === 'complete') {
                            resolve();
                            return;
                        }
                        window.addEventListener('load', resolve, { once: true });
                    });

                    var fontsDone = (document.fonts && document.fonts.ready)
                        ? document.fonts.ready
                        : Promise.resolve();

                    Promise.all([loadDone, fontsDone]).then(hidePartilotPreloader);
                }

                whenReady();
                window.setTimeout(hidePartilotPreloader, MAX_WAIT_MS);
            })();
        </script>
        @include('partials.panel-legal-acceptance-modal')
        @include('partials.lottery-deadline-reminder-modal')
        @include('partials.lottery-deadline-admin-decision-modal')
        @include('partials.entity-management-fee-modal')

        @auth
        @if(!empty($panelLegalAcceptanceModal))
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var modalEl = document.getElementById('panelLegalAcceptanceModal');
                if (!modalEl || typeof bootstrap === 'undefined') return;
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            });
        </script>
        @elseif(!empty($entityManagementFeeModalAlert))
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var modalEl = document.getElementById('entityManagementFeeModal');
                if (!modalEl || typeof bootstrap === 'undefined') return;
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            });
        </script>
        @endif
        @if(!empty($lotteryDeadlineAdminDecisionAlerts))
        <script>
            function showLotteryDeadlineAdminDecisionModalIfAny() {
                var adminModalEl = document.getElementById('lotteryDeadlineAdminDecisionModal');
                if (!adminModalEl || typeof bootstrap === 'undefined') return;
                bootstrap.Modal.getOrCreateInstance(adminModalEl).show();
            }
        </script>
        @endif
        @if(!empty($lotteryDeadlineModalAlerts))
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var modalEl = document.getElementById('lotteryDeadlineReminderModal');
                if (!modalEl || typeof bootstrap === 'undefined') {
                    if (typeof showLotteryDeadlineAdminDecisionModalIfAny === 'function') {
                        showLotteryDeadlineAdminDecisionModalIfAny();
                    }
                    return;
                }

                var modal = new bootstrap.Modal(modalEl);
                modal.show();

                var dismissBtn = document.getElementById('lotteryDeadlineReminderDismiss');
                if (!dismissBtn) {
                    modalEl.addEventListener('hidden.bs.modal', function () {
                        if (typeof showLotteryDeadlineAdminDecisionModalIfAny === 'function') {
                            showLotteryDeadlineAdminDecisionModalIfAny();
                        }
                    });
                    return;
                }

                dismissBtn.addEventListener('click', function () {
                    var alertKeys = Array.from(modalEl.querySelectorAll('[data-alert-key]'))
                        .map(function (el) { return el.getAttribute('data-alert-key'); })
                        .filter(Boolean);

                    fetch(@json(route('lottery-deadline-reminders.dismiss')), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ alerts: alertKeys })
                    }).finally(function () {
                        modal.hide();
                        if (typeof showLotteryDeadlineAdminDecisionModalIfAny === 'function') {
                            showLotteryDeadlineAdminDecisionModalIfAny();
                        }
                    });
                });
            });
        </script>
        @elseif(!empty($lotteryDeadlineAdminDecisionAlerts))
        <script>
            document.addEventListener('DOMContentLoaded', showLotteryDeadlineAdminDecisionModalIfAny);
        </script>
        @endif
        @if(!empty($lotteryDeadlineAdminDecisionAlerts))
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var adminModalEl = document.getElementById('lotteryDeadlineAdminDecisionModal');
                if (!adminModalEl) return;

                var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                var firstItem = adminModalEl.querySelector('.lottery-deadline-admin-decision-item');
                if (!firstItem) return;

                var entityId = firstItem.getAttribute('data-entity-id');
                var lotteryId = firstItem.getAttribute('data-lottery-id');

                function postDecision(url, confirmMessage) {
                    if (!window.confirm(confirmMessage)) return;

                    fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            entity_id: parseInt(entityId, 10),
                            lottery_id: parseInt(lotteryId, 10),
                            confirm: true
                        })
                    })
                    .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
                    .then(function (result) {
                        if (result.ok && result.data.ok) {
                            bootstrap.Modal.getInstance(adminModalEl).hide();
                            if (typeof PNotify !== 'undefined') {
                                new PNotify({
                                    title: 'Decisión registrada',
                                    text: result.data.message || '',
                                    type: 'success',
                                    addclass: 'partilot-notify'
                                });
                            } else {
                                alert(result.data.message || 'Decisión registrada.');
                            }
                        } else {
                            alert(result.data.message || 'No se pudo registrar la decisión.');
                        }
                    })
                    .catch(function () {
                        alert('Error de conexión al registrar la decisión.');
                    });
                }

                var assumeBtn = document.getElementById('lotteryDeadlineAdminAssumeDebtBtn');
                var annulBtn = document.getElementById('lotteryDeadlineAdminAnnulBtn');

                if (assumeBtn) {
                    assumeBtn.addEventListener('click', function () {
                        postDecision(
                            @json(route('lottery-deadline-decisions.assume-debt')),
                            '¿Confirmas que deseas ASUMIR LA DEUDA? Las participaciones seguirán activas y la administración asumirá el riesgo si la entidad no paga.'
                        );
                    });
                }

                if (annulBtn) {
                    annulBtn.addEventListener('click', function () {
                        postDecision(
                            @json(route('lottery-deadline-decisions.annul')),
                            '¿Confirmas que deseas ANULAR LAS PARTICIPACIONES? Esta acción es irreversible y se notificará a los compradores cuando el proceso esté completo.'
                        );
                    });
                }
            });
        </script>
        @endif
        @php
            $backgroundTasksPollUrl = route('background-tasks.index', ['mine' => 1, 'active' => 1, 'limit' => 20]);
            $backgroundTasksAckUrl = route('background-tasks.index', ['mine' => 1, 'limit' => 50]);
            $backgroundTasksShowBaseUrl = url('background-tasks');
            $devolutionsIndexUrlForBg = route('devolutions.index');
        @endphp
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const pollUrl = @json($backgroundTasksPollUrl);
                const ackUrl = @json($backgroundTasksAckUrl);
                const taskShowBaseUrl = @json($backgroundTasksShowBaseUrl);
                const taskShowUrl = function (uuid) {
                    return taskShowBaseUrl + '/' + encodeURIComponent(uuid);
                };
                const devolutionsIndexUrl = @json($devolutionsIndexUrlForBg);
                const notifiedPrefix = 'background_task_notified_';
                const PARTILOT_PENDING_NOTIFY_KEY = 'partilot_pending_background_notify';
                const PARTILOT_BG_JOB_STARTED_KEY = 'partilot_bg_job_started';
                const PARTILOT_WATCHED_TASKS_KEY = 'partilot_watched_background_tasks';

                window.partilotWatchBackgroundTask = function (uuid) {
                    if (!uuid) return;
                    var list = [];
                    try {
                        list = JSON.parse(sessionStorage.getItem(PARTILOT_WATCHED_TASKS_KEY) || '[]');
                    } catch (e) {}
                    if (!Array.isArray(list)) list = [];
                    if (list.indexOf(uuid) === -1) {
                        list.push(uuid);
                        sessionStorage.setItem(PARTILOT_WATCHED_TASKS_KEY, JSON.stringify(list));
                    }
                };

                const getWatchedTaskUuids = function () {
                    try {
                        var list = JSON.parse(sessionStorage.getItem(PARTILOT_WATCHED_TASKS_KEY) || '[]');
                        return Array.isArray(list) ? list.filter(Boolean) : [];
                    } catch (e) {
                        return [];
                    }
                };

                const unwatchBackgroundTask = function (uuid) {
                    var list = getWatchedTaskUuids().filter(function (id) { return id !== uuid; });
                    try {
                        if (list.length) {
                            sessionStorage.setItem(PARTILOT_WATCHED_TASKS_KEY, JSON.stringify(list));
                        } else {
                            sessionStorage.removeItem(PARTILOT_WATCHED_TASKS_KEY);
                        }
                    } catch (e) {}
                };

                const markTaskNotified = function (task) {
                    if (!task || !task.uuid || !task.status) return;
                    localStorage.setItem(notifiedPrefix + task.uuid + '_' + task.status, '1');
                };

                const acknowledgeHistoricalTasks = function () {
                    fetch(ackUrl, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        }
                    })
                        .then(function (res) { return res.ok ? res.json() : null; })
                        .then(function (data) {
                            var items = Array.isArray(data?.items) ? data.items : [];
                            items.forEach(function (item) {
                                if (item.status === 'completed' || item.status === 'failed') {
                                    markTaskNotified(item);
                                }
                            });
                        })
                        .catch(function () {});
                };

                const queuePendingNotify = function (title, text, pnotifyType) {
                    try {
                        sessionStorage.setItem(PARTILOT_PENDING_NOTIFY_KEY, JSON.stringify({
                            title: title || 'Proceso finalizado',
                            text: text || '',
                            type: pnotifyType || 'success'
                        }));
                    } catch (e) {}
                };

                const flushPendingBackgroundNotify = function () {
                    var raw = null;
                    try {
                        raw = sessionStorage.getItem(PARTILOT_PENDING_NOTIFY_KEY);
                    } catch (e) {}
                    if (!raw) return;
                    try {
                        sessionStorage.removeItem(PARTILOT_PENDING_NOTIFY_KEY);
                    } catch (e2) {}
                    var data = null;
                    try {
                        data = JSON.parse(raw);
                    } catch (e3) {
                        return;
                    }
                    if (typeof PNotify === 'undefined') return;
                    if (typeof PNotify.removeAll === 'function') {
                        PNotify.removeAll();
                    }
                    document.querySelectorAll('.ui-pnotify').forEach(function (el) {
                        el.remove();
                    });
                    var pType = data.type === 'error' ? 'error' : (data.type === 'notice' ? 'notice' : 'success');
                    new PNotify({
                        title: data.title || 'Proceso finalizado',
                        text: data.text || '',
                        type: pType,
                        addclass: 'partilot-notify',
                        width: '460px',
                        hide: true,
                        icon: false,
                        delay: 7000,
                        buttons: { closer: true, sticker: false, closer_hover: false }
                    });
                };

                const flushPendingBackgroundStarted = function () {
                    var raw = null;
                    try {
                        raw = sessionStorage.getItem(PARTILOT_BG_JOB_STARTED_KEY);
                    } catch (e) {}
                    if (!raw) return;
                    try {
                        sessionStorage.removeItem(PARTILOT_BG_JOB_STARTED_KEY);
                    } catch (e2) {}
                    var data = null;
                    try {
                        data = JSON.parse(raw);
                    } catch (e3) {
                        return;
                    }
                    if (typeof PNotify === 'undefined') return;
                    if (typeof PNotify.removeAll === 'function') {
                        PNotify.removeAll();
                    }
                    document.querySelectorAll('.ui-pnotify').forEach(function (el) {
                        el.remove();
                    });
                    var pType = data.type === 'error' ? 'error' : (data.type === 'success' ? 'success' : 'notice');
                    new PNotify({
                        title: data.title || 'En proceso',
                        text: data.text || 'La tarea se está ejecutando en segundo plano.',
                        type: pType,
                        addclass: 'partilot-notify',
                        width: '460px',
                        hide: true,
                        icon: false,
                        delay: 8000,
                        buttons: { closer: true, sticker: false, closer_hover: false }
                    });
                };

                flushPendingBackgroundStarted();
                flushPendingBackgroundNotify();

                const notifyTask = (task) => {
                    if (!task || !task.uuid) return;
                    const notifyKey = notifiedPrefix + task.uuid + '_' + task.status;
                    if (localStorage.getItem(notifyKey)) return;
                    markTaskNotified(task);

                    if (typeof PNotify === 'undefined') return;

                    const summary = task.result_summary || {};
                    const successMsg = (typeof summary.message === 'string' && summary.message)
                        ? summary.message
                        : 'La operación finalizó correctamente.';

                    if (task.status === 'completed') {
                        const t = task.type || '';
                        if (t === 'participation_assignment' || t === 'devolution_delete') {
                            queuePendingNotify('Proceso finalizado', successMsg, 'success');
                            setTimeout(function () {
                                window.location.reload();
                            }, 250);
                            return;
                        }
                        if (t === 'devolution') {
                            queuePendingNotify('Proceso finalizado', successMsg, 'success');
                            setTimeout(function () {
                                window.location.href = devolutionsIndexUrl;
                            }, 250);
                            return;
                        }
                        if (typeof PNotify.removeAll === 'function') {
                            PNotify.removeAll();
                        }
                        document.querySelectorAll('.ui-pnotify').forEach(function (el) {
                            el.remove();
                        });
                        new PNotify({
                            type: 'success',
                            addclass: 'partilot-notify',
                            width: '460px',
                            title: 'Proceso finalizado',
                            text: successMsg,
                            hide: true,
                            icon: false,
                            buttons: { closer: true, sticker: false, closer_hover: false }
                        });
                    } else if (task.status === 'failed') {
                        if (typeof PNotify.removeAll === 'function') {
                            PNotify.removeAll();
                        }
                        document.querySelectorAll('.ui-pnotify').forEach(function (el) {
                            el.remove();
                        });
                        new PNotify({
                            type: 'error',
                            addclass: 'partilot-notify',
                            width: '460px',
                            title: 'Proceso fallido',
                            text: task.error_message || 'La tarea en segundo plano ha fallado.',
                            hide: true,
                            buttons: { closer: true, sticker: false, closer_hover: false }
                        });
                    }
                };

                const pollWatchedTasks = function () {
                    var watched = getWatchedTaskUuids();
                    if (!watched.length) return;

                    watched.forEach(function (uuid) {
                        fetch(taskShowUrl(uuid), {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json'
                            }
                        })
                            .then(function (res) { return res.ok ? res.json() : null; })
                            .then(function (task) {
                                if (!task || !task.uuid) return;
                                if (task.status === 'completed' || task.status === 'failed') {
                                    unwatchBackgroundTask(uuid);
                                    notifyTask(task);
                                }
                            })
                            .catch(function () {});
                    });
                };

                const pollBackgroundTasks = () => {
                    fetch(pollUrl, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        }
                    })
                        .then((res) => (res.ok ? res.json() : null))
                        .then((data) => {
                            const items = Array.isArray(data?.items) ? data.items : [];
                            items.forEach(function (item) {
                                if (item.uuid && (item.status === 'pending' || item.status === 'running')) {
                                    window.partilotWatchBackgroundTask(item.uuid);
                                }
                            });
                        })
                        .catch(() => {});
                };

                acknowledgeHistoricalTasks();
                pollWatchedTasks();
                pollBackgroundTasks();
                setInterval(function () {
                    pollWatchedTasks();
                    pollBackgroundTasks();
                }, 3000);
            });
        </script>
        @endauth

        @yield('scripts')

        <script>
        (function() {
            var searchInput = document.getElementById('top-search');
            var searchDropdown = document.getElementById('search-dropdown');
            var searchResults = document.getElementById('search-results');
            var searchTitle = document.getElementById('search-dropdown-title');
            var wrap = document.getElementById('top-search-wrap');
            if (!searchInput || !searchDropdown) {
                console.error('Elementos del buscador no encontrados');
                return;
            }
            console.log('Buscador inicializado correctamente');
            var searchUrl = searchInput.getAttribute('data-search-url');
            var minChars = parseInt(searchInput.getAttribute('data-min-chars') || '6', 10);
            var debounceTimer = null;
            var hideTimer = null;

            function showDropdown() {
                wrap.classList.add('show');
                wrap.style.setProperty('z-index', '10000', 'important');
                searchDropdown.classList.add('show');
                searchDropdown.style.setProperty('display', 'block', 'important');
                searchDropdown.style.setProperty('visibility', 'visible', 'important');
                searchDropdown.style.setProperty('opacity', '1', 'important');
                searchDropdown.style.setProperty('z-index', '10001', 'important');
                console.log('Dropdown mostrado - z-index:', window.getComputedStyle(searchDropdown).zIndex);
            }
            function hideDropdown() {
                wrap.classList.remove('show');
                searchDropdown.classList.remove('show');
                searchDropdown.style.setProperty('display', 'none', 'important');
                wrap.style.removeProperty('z-index');
            }

            function renderResult(r) {
                var img = r.snapshot_path ? '<img src="' + escapeHtml(r.snapshot_path) + '" alt="" class="rounded me-2" style="width:48px;height:48px;object-fit:cover;flex-shrink:0;">' : '<div class="rounded me-2 bg-light d-flex align-items-center justify-content-center" style="width:48px;height:48px;flex-shrink:0;"><span class="fe-file font-18 text-muted"></span></div>';
                var statusBadge = '<span class="badge ' + (r.status === 'vendida' ? 'bg-primary' : r.status === 'disponible' ? 'bg-success' : r.status === 'devuelta' ? 'bg-info' : r.status === 'anulada' ? 'bg-danger' : 'bg-secondary') + '" style="flex-shrink:0;">' + escapeHtml(r.status_text || r.status) + '</span>';
                var detailUrl = escapeHtml(r.detail_url || ('/participations/view/' + r.id));
                return '<a href="' + detailUrl + '" class="dropdown-item notify-item py-2 border-bottom border-light search-result-item" style="overflow:hidden;">' +
                    '<div class="d-flex align-items-start" style="min-width:0;">' +
                    '<div class="flex-shrink-0">' + img + '</div>' +
                    '<div class="flex-grow-1" style="min-width:0;overflow:hidden;">' +
                    '<div class="fw-semibold text-truncate" style="max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + escapeHtml(r.referencia) + '</div>' +
                    '<div class="small text-muted" style="max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + escapeHtml(r.sorteo) + ' · ' + formatMoney(r.importeJugado) + ' · Donativo ' + formatMoney(r.donativo) + ' · ' + escapeHtml(r.fechaSorteo) + '</div>' +
                    '</div>' +
                    '<div class="flex-shrink-0 ms-2">' + statusBadge + '</div>' +
                    '</div></a>';
            }
            function escapeHtml(s) {
                if (s == null) return '';
                var div = document.createElement('div');
                div.textContent = s;
                return div.innerHTML;
            }
            function formatMoney(n) {
                if (n == null) return '—';
                return parseFloat(n).toFixed(2) + ' €';
            }

            function doSearch() {
                var q = (searchInput.value || '').trim();
                if (q.length < minChars) {
                    searchTitle.textContent = 'Escribe al menos ' + minChars + ' caracteres';
                    searchResults.innerHTML = '';
                    showDropdown();
                    console.log('Mostrando dropdown - caracteres insuficientes');
                    return;
                }
                searchTitle.textContent = 'Buscando...';
                searchResults.innerHTML = '';
                showDropdown();
                console.log('Mostrando dropdown - buscando:', q);
                fetch(searchUrl + '?q=' + encodeURIComponent(q), { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                    .then(function(res) { return res.json(); })
                    .then(function(data) {
                        var list = (data && data.results) ? data.results : [];
                        searchTitle.textContent = list.length === 0 ? 'Sin resultados' : (list.length + ' resultado' + (list.length !== 1 ? 's' : ''));
                        searchResults.innerHTML = list.map(function(r) { return renderResult(r); }).join('');
                        if (list.length === 0) {
                            searchResults.innerHTML = '<div class="dropdown-item text-muted small py-2">No hay participaciones con esa referencia.</div>';
                        }
                    })
                    .catch(function() {
                        searchTitle.textContent = 'Error en la búsqueda';
                        searchResults.innerHTML = '<div class="dropdown-item text-muted small py-2">Inténtalo de nuevo.</div>';
                    });
            }

            searchInput.addEventListener('mousedown', function(e) {
                e.stopPropagation();
            });
            searchInput.addEventListener('click', function(e) {
                e.stopPropagation();
            });
            searchInput.addEventListener('input', function() {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(doSearch, 320);
            });
            searchInput.addEventListener('focus', function() {
                doSearch();
            });
            searchInput.addEventListener('blur', function(e) {
                // No ocultar si el clic es dentro del dropdown
                setTimeout(function() {
                    if (!wrap.contains(document.activeElement) && !searchDropdown.matches(':hover')) {
                        hideDropdown();
                    }
                }, 200);
            });
            searchDropdown.addEventListener('mousedown', function(e) {
                e.preventDefault(); // Prevenir blur del input
                clearTimeout(hideTimer);
            });
            searchDropdown.addEventListener('click', function(e) {
                // Si es un link, permitir navegación
                if (e.target.closest('a')) {
                    return; // Permitir navegación
                }
            });
            document.addEventListener('click', function(e) {
                if (!wrap.contains(e.target)) {
                    hideDropdown();
                }
            });
        })();
        </script>

        <script>
            localStorage.removeItem('step2');
            localStorage.removeItem('step3');
            localStorage.removeItem('step4');
            localStorage.removeItem('step5');

            localStorage.removeItem('bgimg-step2');
            localStorage.removeItem('bgimg-step3');
            localStorage.removeItem('bgimg-step4');

            localStorage.removeItem('bg-step2');
            localStorage.removeItem('bg-step3');
            localStorage.removeItem('bg-step4');

            localStorage.removeItem('guide-step2');
            localStorage.removeItem('guide-step3');
            localStorage.removeItem('guide-step4');
        </script>

        @include('partials.cookie-consent-banner')

    </body>

<!-- Mirrored from coderthemes.com/ubold/layouts/default/index.html by HTTrack Website Copier/3.x [XR&CO'2014], Sun, 25 May 2025 15:57:16 GMT -->
</html>