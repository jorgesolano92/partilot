<!DOCTYPE html>
<html lang="en" data-topbar-color="dark">

    
<!-- Mirrored from coderthemes.com/ubold/layouts/default/auth-login-2.html by HTTrack Website Copier/3.x [XR&CO'2014], Sun, 25 May 2025 15:57:44 GMT -->
<head>
        <meta charset="utf-8" />
        <title>Log In | PARTILOT</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta content="A fully featured admin theme which can be used to build CRM, CMS, etc." name="description" />
        <meta content="Coderthemes" name="author" />

        <!-- App favicon -->
        <link rel="shortcut icon" href="{{url('/')}}/logo.svg">

        <!-- Theme Config Js -->
        <script src="{{url('default')}}/assets/js/head.js"></script>

        <!-- Bootstrap css -->
        <link href="{{url('default')}}/assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" id="app-style" />

        <!-- App css -->
        <link href="{{url('default')}}/assets/css/app.min.css" rel="stylesheet" type="text/css" />

        <!-- Icons css -->
        <link href="{{url('assets')}}/css/icons.min.css" rel="stylesheet" type="text/css" />
        <link href="{{url('assets')}}/css/partilot-ui-fixes.css" rel="stylesheet" type="text/css" />

        <style>

            .group-login {
                border: 2px solid silver;
                /* padding: 5px 0; */
                border-radius: 30px;
                background: #fff;
            }
            
            .group-login div, .group-login input {
                border: none;
                padding: .7rem .9rem;
            }

        </style>
    </head>

    <body class="auth-fluid-pages pb-0">

        <div class="auth-fluid container" style="padding: 120px 80px;">
            <!--Auth fluid left content -->
            <div class="auth-fluid-form-box" style="border-radius: 80px 0 0 80px; box-shadow: -5px 0px 5px silver">
                <div class="align-items-center d-flex h-100">
                    <div class="p-3" style="width: 100%;">

                        <!-- Logo -->
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

                        <!-- title-->

                        <!-- form -->
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
                                {{-- <label for="emailaddress" class="form-label">Email</label> --}}
                                <div class="input-group input-group-merge group-login">

                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                        <span class="ri-user-line"></span>
                                    </div>

                                    <input class="form-control @error('email') is-invalid @enderror" type="text" name="email" id="emailaddress" placeholder="Usuario de panel o email" value="{{ old('email') }}" style="border-radius: 0 30px 30px 0;" required autocomplete="username" title="Las cuentas de administración deben usar el usuario de panel del correo de bienvenida">
                                </div>
                            </div>
                            <div class="mb-3">
                                <a href="javascript:void(0);" class="text-muted float-end mb-1"><small>¿Olvidaste tu contraseña?</small></a>
                                {{-- <label for="password" class="form-label">Contraseña</label> --}}
                                
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
                            <!-- social-->
                            {{-- <div class="text-center mt-4">
                                <p class="text-muted font-16">Sign in with</p>
                                <ul class="social-list list-inline mt-3">
                                    <li class="list-inline-item">
                                        <a href="javascript: void(0);" class="social-list-item border-primary text-primary"><i class="mdi mdi-facebook"></i></a>
                                    </li>
                                    <li class="list-inline-item">
                                        <a href="javascript: void(0);" class="social-list-item border-danger text-danger"><i class="mdi mdi-google"></i></a>
                                    </li>
                                    <li class="list-inline-item">
                                        <a href="javascript: void(0);" class="social-list-item border-info text-info"><i class="mdi mdi-twitter"></i></a>
                                    </li>
                                    <li class="list-inline-item">
                                        <a href="javascript: void(0);" class="social-list-item border-secondary text-secondary"><i class="mdi mdi-github"></i></a>
                                    </li>
                                </ul>
                            </div> --}}
                        </form>
                        <!-- end form-->

                        <!-- Footer-->
                        {{-- <footer class="footer footer-alt">
                            <p class="text-muted">Don't have an account? <a href="auth-register-2.html" class="text-muted ms-1"><b>Sign Up</b></a></p>
                        </footer> --}}

                    </div> <!-- end .card-body -->
                </div> <!-- end .align-items-center.d-flex.h-100-->
            </div>
            <!-- end auth-fluid-form-box-->

            <!-- Auth fluid right content -->
            <div class="auth-fluid-right text-left" style="border-radius: 0 80px 80px 0; box-shadow: 5px 0 5px silver">
                <div class="auth-user-testimonial">

                    <h1 class="mb-3">Participaciones</h1>
                    <h2><i class="mdi mdi-format-quote-open"></i> El control total del <br> estado de las <br> participaciones <i class="mdi mdi-format-quote-close"></i>
                    </h2>
                    <div style="text-align: right">
                        <img src="{{url('assets/talonario.svg')}}" alt="" width="65%">
                    </div>
                </div> <!-- end auth-user-testimonial-->
            </div>
            <!-- end Auth fluid right content -->
        </div>
        <!-- end auth-fluid-->

        <!-- Vendor js -->
        <script src="{{url('default')}}/assets/js/vendor.min.js"></script>

        <!-- App js -->
        <script src="{{url('default')}}/assets/js/app.min.js"></script>

        <!-- Authentication js -->
        <script src="{{url('default')}}/assets/js/pages/authentication.init.js"></script>

    </body>

<!-- Mirrored from coderthemes.com/ubold/layouts/default/auth-login-2.html by HTTrack Website Copier/3.x [XR&CO'2014], Sun, 25 May 2025 15:57:44 GMT -->
</html>