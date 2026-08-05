<!DOCTYPE html>
<html lang="en" data-topbar-color="dark">

<head>
        <meta charset="utf-8" />
        <title>Log In | PARTILOT</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta content="A fully featured admin theme which can be used to build CRM, CMS, etc." name="description" />
        <meta content="Coderthemes" name="author" />

        <link rel="shortcut icon" href="{{url('/')}}/logo.svg">

        <script src="{{url('default')}}/assets/js/head.js"></script>

        <link href="{{url('default')}}/assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" id="app-style" />
        <link href="{{url('default')}}/assets/css/app.min.css" rel="stylesheet" type="text/css" />
        <link href="{{url('assets')}}/css/icons.min.css" rel="stylesheet" type="text/css" />
        <link href="{{url('assets')}}/css/partilot-ui-fixes.css" rel="stylesheet" type="text/css" />

        <style>
            .group-login {
                border: 2px solid silver;
                border-radius: 30px;
                background: #fff;
            }

            .group-login div,
            .group-login input {
                border: none;
                padding: .7rem .9rem;
            }
        </style>
</head>

<body class="auth-fluid-pages pb-0">

    {{-- Móvil: solo pantallas pequeñas (< 768px). Tablet y desktop usan el layout de abajo. --}}
    <div class="login-layout-mobile d-md-none">
        <div class="partilot-login-mobile">
            <div class="partilot-login-mobile-inner">
                <div class="auth-brand text-center mb-4">
                    <a href="{{ url('/') }}" class="logo logo-dark text-center d-inline-block">
                        <span class="logo-lg">
                            <img src="{{ url('/') }}/logo.svg" alt="PARTILOT" height="40">
                        </span>
                    </a>
                    <h4 class="text-center mt-3 mb-0"><b>Bienvenido</b> al panel de <b>PARTILOT</b></h4>
                </div>

                <form action="{{ url('/login') }}" method="POST" class="partilot-login-mobile-form">
                    @csrf

                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if (session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif

                    <div class="mb-3">
                        <label for="emailaddress-mobile" class="form-label visually-hidden">Usuario de panel o email</label>
                        <div class="input-group input-group-merge group-login group-login-mobile">
                            <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                <span class="ri-user-line"></span>
                            </div>
                            <input
                                class="form-control @error('email') is-invalid @enderror"
                                type="text"
                                name="email"
                                id="emailaddress-mobile"
                                placeholder="Usuario de panel o email"
                                value="{{ old('email') }}"
                                style="border-radius: 0 30px 30px 0;"
                                required
                                autocomplete="username"
                                title="Las cuentas de administración deben usar el usuario de panel del correo de bienvenida"
                            >
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-end mb-1">
                            <a href="{{ route('password.request') }}" class="text-muted"><small>¿Olvidaste tu contraseña?</small></a>
                        </div>
                        <label for="password-mobile" class="form-label visually-hidden">Contraseña</label>
                        <div class="input-group input-group-merge group-login group-login-mobile">
                            <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                <span class="ri-lock-line"></span>
                            </div>
                            <input
                                type="password"
                                name="password"
                                id="password-mobile"
                                class="form-control @error('password') is-invalid @enderror"
                                placeholder="Ingresa tu contraseña"
                                required
                            >
                            <div class="input-group-text" data-password="false" style="border-radius: 0 30px 30px 0;">
                                <span class="password-eye"></span>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="remember" class="form-check-input" id="checkbox-signin-mobile">
                            <label class="form-check-label" for="checkbox-signin-mobile">Recordar contraseña</label>
                        </div>
                    </div>

                    <div class="text-center d-grid">
                        <button class="btn btn-dark partilot-login-mobile-submit" type="submit">Acceso</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Desktop y tablet: layout original sin cambios --}}
    <div class="login-layout-desktop d-none d-md-block">
        <div class="auth-fluid container" style="padding: 120px 80px;">
            <div class="auth-fluid-form-box" style="border-radius: 80px 0 0 80px; box-shadow: -5px 0px 5px silver">
                <div class="align-items-center d-flex h-100">
                    <div class="p-3" style="width: 100%;">

                        <div class="auth-brand text-center text-lg-start">
                            <div class="auth-brand">
                                <a href="{{url('/')}}" class="logo logo-dark text-center">
                                    <span class="logo-lg">
                                        <img src="{{url('/')}}/logo.svg" alt="" height="40">
                                    </span>
                                </a>

                                <a href="{{url('/')}}" class="logo logo-light text-center">
                                    <span class="logo-lg">
                                        <img src="{{url('/')}}/logo.svg" alt="" height="40">
                                    </span>
                                </a>
                                <h4 class="text-center mt-1"><b>Bienvenido</b> al <br> panel de <b>PARTILOT</b></h4>
                            </div>
                        </div>

                        <form action="{{url('/login')}}" method="POST">
                            @csrf

                            @if ($errors->any())
                                <div class="alert alert-danger">
                                    <ul class="mb-0">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            @if (session('success'))
                                <div class="alert alert-success">{{ session('success') }}</div>
                            @endif

                            <div class="mb-3">
                                <div class="input-group input-group-merge group-login">
                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                        <span class="ri-user-line"></span>
                                    </div>
                                    <input class="form-control @error('email') is-invalid @enderror" type="text" name="email" id="emailaddress" placeholder="Usuario de panel o email" value="{{ old('email') }}" style="border-radius: 0 30px 30px 0;" required autocomplete="username" title="Las cuentas de administración deben usar el usuario de panel del correo de bienvenida">
                                </div>
                            </div>

                            <div class="mb-3">
                                <a href="{{ route('password.request') }}" class="text-muted float-end mb-1"><small>¿Olvidaste tu contraseña?</small></a>

                                <div class="input-group input-group-merge group-login">
                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                        <span class="ri-lock-line"></span>
                                    </div>
                                    <input type="password" name="password" id="password" class="form-control @error('password') is-invalid @enderror" placeholder="Ingresa tu contraseña" required>
                                    <div class="input-group-text" data-password="false" style="border-radius: 0 30px 30px 0;">
                                        <span class="password-eye"></span>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="form-check text-end">
                                    <label class="form-check-label float-end" for="checkbox-signin">Recordar contraseña</label>
                                    <input type="checkbox" name="remember" class="form-check-input float-end" id="checkbox-signin" style="margin-right: 8px;">
                                </div>
                            </div>
                            <div style="clear: both"></div>
                            <div class="text-center d-grid">
                                <button class="btn btn-dark" style="border-radius: 30px; padding: 13px 0;" type="submit">Acceso </button>
                            </div>
                        </form>

                    </div>
                </div>
            </div>

            <div class="auth-fluid-right text-left" style="border-radius: 0 80px 80px 0; box-shadow: 5px 0 5px silver">
                <div class="auth-user-testimonial">
                    <h1 class="mb-3">Participaciones</h1>
                    <h2><i class="mdi mdi-format-quote-open"></i> El control total del <br> estado de las <br> participaciones <i class="mdi mdi-format-quote-close"></i></h2>
                    <div style="text-align: right">
                        <img src="{{url('assets/talonario.svg')}}" alt="" width="65%">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="{{url('default')}}/assets/js/vendor.min.js"></script>
    <script src="{{url('default')}}/assets/js/app.min.js"></script>
    <script src="{{url('default')}}/assets/js/pages/authentication.init.js"></script>

</body>
</html>
