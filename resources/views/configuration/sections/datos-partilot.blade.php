@php
    $settings = $partilotBillingSettings ?? \App\Models\PartilotBillingSetting::current();
    $panelUser = auth()->user();
@endphp

<div class="form-card bs" style="min-height: 658px;">
    @if(session('success') && request('section') === 'datos-partilot')
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <form method="POST" action="{{ route('configuration.partilot-profile.update') }}" id="form-datos-partilot">
        @csrf

        <h4 class="mb-0 mt-1">Datos legales de PARTILOT</h4>
        <small><i>Contacto y razón social usados en contratos, facturación y comunicaciones oficiales.</i></small>

        <div class="form-group mt-2 mb-3">
            <div class="row">
                <div class="col-6">
                    <div class="form-group mt-2 mb-3">
                        <label class="label-control">Empresa / Razón social</label>
                        <div class="input-group input-group-merge group-form">
                            <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                <img src="{{ url('assets/form-groups/admin/1.svg') }}" alt="">
                            </div>
                            <input class="form-control @error('company_name') is-invalid @enderror" type="text" name="company_name" id="company-name" placeholder="PARTILOT, S.L.U." value="{{ old('company_name', $settings->company_name) }}" required style="border-radius: 0 30px 30px 0;">
                        </div>
                        @error('company_name')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="col-6">
                    <div class="form-group mt-2 mb-3">
                        <label class="label-control">NIF/CIF</label>
                        <div class="input-group input-group-merge group-form">
                            <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                <img src="{{ url('assets/form-groups/admin/4.svg') }}" alt="">
                            </div>
                            <input class="form-control @error('nif_cif') is-invalid @enderror" type="text" name="nif_cif" id="nif-cif" placeholder="NIF/CIF" value="{{ old('nif_cif', $settings->nif_cif) }}" required style="border-radius: 0 30px 30px 0;">
                        </div>
                        @error('nif_cif')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="col-3">
                    <div class="form-group mt-2 mb-3">
                        <label class="label-control">Provincia</label>
                        <div class="input-group input-group-merge group-form">
                            <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                <img src="{{ url('assets/form-groups/admin/5.svg') }}" alt="">
                            </div>
                            <input class="form-control @error('province') is-invalid @enderror" type="text" name="province" id="provincia" placeholder="Provincia" value="{{ old('province', $settings->province) }}" required style="border-radius: 0 30px 30px 0;">
                        </div>
                        @error('province')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="col-3">
                    <div class="form-group mt-2 mb-3">
                        <label class="label-control">Localidad</label>
                        <div class="input-group input-group-merge group-form">
                            <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                <img src="{{ url('assets/form-groups/admin/6.svg') }}" alt="">
                            </div>
                            <input class="form-control @error('city') is-invalid @enderror" type="text" name="city" id="localidad" placeholder="Localidad" value="{{ old('city', $settings->city) }}" required style="border-radius: 0 30px 30px 0;">
                        </div>
                        @error('city')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="col-3">
                    <div class="form-group mt-2 mb-3">
                        <label class="label-control">Código Postal</label>
                        <div class="input-group input-group-merge group-form">
                            <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                <img src="{{ url('assets/form-groups/admin/7.svg') }}" alt="">
                            </div>
                            <input class="form-control @error('postal_code') is-invalid @enderror" type="text" name="postal_code" id="codigo-postal" placeholder="C.P." value="{{ old('postal_code', $settings->postal_code) }}" required style="border-radius: 0 30px 30px 0;">
                        </div>
                        @error('postal_code')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="col-3">
                    <div class="form-group mt-2 mb-3">
                        <label class="label-control">Teléfono</label>
                        <div class="input-group input-group-merge group-form">
                            <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                <img src="{{ url('assets/form-groups/admin/10.svg') }}" alt="">
                            </div>
                            <input class="form-control @error('phone') is-invalid @enderror" type="text" name="phone" id="telefono" placeholder="941 900 900" value="{{ old('phone', $settings->phone) }}" required style="border-radius: 0 30px 30px 0;">
                        </div>
                        @error('phone')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="col-6">
                    <div class="form-group mt-2 mb-3">
                        <label class="label-control">Dirección</label>
                        <div class="input-group input-group-merge group-form">
                            <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                <img src="{{ url('assets/form-groups/admin/8.svg') }}" alt="">
                            </div>
                            <input class="form-control @error('address') is-invalid @enderror" type="text" name="address" id="direccion" placeholder="Dirección" value="{{ old('address', $settings->address) }}" required style="border-radius: 0 30px 30px 0;">
                        </div>
                        @error('address')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="col-6">
                    <div class="form-group mt-2 mb-3">
                        <label class="label-control">Email de contacto legal</label>
                        <div class="input-group input-group-merge group-form">
                            <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                <img src="{{ url('assets/form-groups/admin/9.svg') }}" alt="">
                            </div>
                            <input class="form-control @error('email') is-invalid @enderror" type="email" name="email" id="email" placeholder="legal@partilot.es" value="{{ old('email', $settings->email) }}" required style="border-radius: 0 30px 30px 0;">
                        </div>
                        @error('email')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        <h4 class="mb-0 mt-1">Datos de acceso al panel</h4>
        <small><i>Credenciales de su cuenta de superadministrador.</i></small>

        <div class="row">
            <div class="col-6">
                <div class="form-group mt-2 mb-3">
                    <label class="label-control">Email de acceso</label>
                    <div class="input-group input-group-merge group-form">
                        <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                            <img src="{{ url('assets/form-groups/admin/9.svg') }}" alt="">
                        </div>
                        <input class="form-control @error('access_email') is-invalid @enderror" type="email" name="access_email" id="email-acceso" placeholder="Email de acceso" value="{{ old('access_email', $panelUser?->email) }}" required style="border-radius: 0 30px 30px 0;">
                    </div>
                    @error('access_email')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="col-3">
                <div class="form-group mt-2 mb-3">
                    <label class="label-control">Nueva contraseña</label>
                    <div class="input-group input-group-merge group-form">
                        <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                            <img src="{{ url('assets/form-groups/admin/11.svg') }}" alt="">
                        </div>
                        <input class="form-control @error('password') is-invalid @enderror" type="password" name="password" id="nueva-password" placeholder="Opcional" autocomplete="new-password" style="border-radius: 0;">
                        <button class="input-group-text btn btn-link text-muted" type="button" id="toggle-password" style="border: none; background: transparent; border-radius: 0 30px 30px 0; padding: 0.375rem 0.75rem;">
                            <i class="fe-eye" id="eye-icon"></i>
                        </button>
                    </div>
                    @error('password')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="col-3">
                <div class="form-group mt-2 mb-3">
                    <label class="label-control">Repetir contraseña</label>
                    <div class="input-group input-group-merge group-form">
                        <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                            <img src="{{ url('assets/form-groups/admin/11.svg') }}" alt="">
                        </div>
                        <input class="form-control" type="password" name="password_confirmation" id="repetir-password" placeholder="Confirmación" autocomplete="new-password" style="border-radius: 0 30px 30px 0;">
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12 text-end">
                <button type="submit" style="border-radius: 30px; width: 200px; background-color: #e78307; color: #333; padding: 8px; font-weight: bolder; position: relative;" class="btn btn-md btn-light mt-2">
                    Guardar
                    <i style="top: 6px; margin-left: 6px; font-size: 18px; position: absolute;" class="ri-arrow-right-circle-line"></i>
                </button>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.getElementById('toggle-password');
    if (!toggleBtn) {
        return;
    }

    toggleBtn.addEventListener('click', function() {
        const passwordInput = document.getElementById('nueva-password');
        const eyeIcon = document.getElementById('eye-icon');

        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            eyeIcon.classList.remove('fe-eye');
            eyeIcon.classList.add('fe-eye-off');
        } else {
            passwordInput.type = 'password';
            eyeIcon.classList.remove('fe-eye-off');
            eyeIcon.classList.add('fe-eye');
        }
    });
});
</script>
