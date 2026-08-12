<div class="form-card bs pb-3" style="min-height: 658px;">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-1 mb-2">
        <a href="{{ route('configuration.index', ['section' => 'imprenta']) }}" class="btn btn-sm btn-outline-dark" style="border-radius: 20px;">
            <i class="ri-arrow-left-line me-1"></i> Todas las imprentas
        </a>
    </div>

    <form action="{{ route('configuration.imprenta.update') }}" method="POST">
        @csrf
        @if($printConfiguration->exists)
            <input type="hidden" name="print_configuration_id" value="{{ $printConfiguration->id }}">
        @else
            <input type="hidden" name="print_configuration_id" value="new">
        @endif

        <h4 class="mb-0 mt-1">{{ $printConfiguration->exists ? $printConfiguration->displayName() : 'Nueva imprenta' }}</h4>
        <small><i>{{ $printConfiguration->exists ? 'Costes, datos legales, Stripe y acceso al panel de esta imprenta' : 'Rellena los datos y pulsa Guardar para dar de alta la imprenta' }}</i></small>

        <div class="form-check form-switch mt-3">
            <input class="form-check-input" type="checkbox" name="status" value="1" id="print-config-status"
                {{ old('status', $printConfiguration->status ?? 0) == \App\Models\PrintConfiguration::STATUS_DEFAULT ? 'checked' : '' }}>
            <label class="form-check-label" for="print-config-status">Imprenta por defecto (diseño e impresión)</label>
        </div>
        <div class="form-text small">Solo puede haber una imprenta por defecto. Al marcar esta, las demás dejan de serlo.</div>

        <div class="row mt-3">
            <div class="col-lg-12">
                <div class="form-group mt-2 mb-3">
                    <h5 class="mb-3">Datos legales de la imprenta</h5>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <div class="form-group mt-2 mb-3">
                                <label class="label-control">Empresa</label>
                                <div class="input-group input-group-merge group-form">
                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                        <img src="{{ url('assets/form-groups/admin/1.svg') }}" alt="">
                                    </div>
                                    <input class="form-control" type="text" name="company_name"
                                        placeholder="Empresa"
                                        value="{{ old('company_name', $printConfiguration->company_name ?? '') }}"
                                        style="border-radius: 0 30px 30px 0;">
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="form-group mt-2 mb-3">
                                <label class="label-control">NIF/CIF</label>
                                <div class="input-group input-group-merge group-form">
                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                        <img src="{{ url('assets/form-groups/admin/4.svg') }}" alt="">
                                    </div>
                                    <input class="form-control" type="text" name="nif_cif"
                                        placeholder="NIF/CIF"
                                        value="{{ old('nif_cif', $printConfiguration->nif_cif ?? '') }}"
                                        style="border-radius: 0 30px 30px 0;">
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="form-group mt-2 mb-3">
                                <label class="label-control">Código Postal</label>
                                <div class="input-group input-group-merge group-form">
                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                        <img src="{{ url('assets/form-groups/admin/7.svg') }}" alt="">
                                    </div>
                                    <input class="form-control" type="text" name="postal_code"
                                        placeholder="C.P."
                                        value="{{ old('postal_code', $printConfiguration->postal_code ?? '') }}"
                                        style="border-radius: 0 30px 30px 0;">
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group mt-2 mb-3">
                                <label class="label-control">Dirección</label>
                                <div class="input-group input-group-merge group-form">
                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                        <img src="{{ url('assets/form-groups/admin/8.svg') }}" alt="">
                                    </div>
                                    <input class="form-control" type="text" name="address"
                                        placeholder="Dirección"
                                        value="{{ old('address', $printConfiguration->address ?? '') }}"
                                        style="border-radius: 0 30px 30px 0;">
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="form-group mt-2 mb-3">
                                <label class="label-control">Provincia</label>
                                <div class="input-group input-group-merge group-form">
                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                        <img src="{{ url('assets/form-groups/admin/5.svg') }}" alt="">
                                    </div>
                                    @php($selectedProvince = old('province', $printConfiguration->province ?? ''))
                                    <select class="form-control" name="province" id="imprenta-province-select" style="border-radius: 0 30px 30px 0;">
                                        <option value="">Selecciona provincia</option>
                                        @foreach(($provinces ?? []) as $province)
                                            <option value="{{ $province }}" @selected($selectedProvince === $province)>{{ $province }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="form-group mt-2 mb-3">
                                <label class="label-control">Localidad</label>
                                <div class="input-group input-group-merge group-form">
                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                        <img src="{{ url('assets/form-groups/admin/6.svg') }}" alt="">
                                    </div>
                                    @php($selectedCity = old('city', $printConfiguration->city ?? ''))
                                    <select class="form-control" name="city" id="imprenta-city-select" style="border-radius: 0 30px 30px 0;">
                                        <option value="">Selecciona localidad</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="form-group mt-2 mb-3">
                                <label class="label-control">Teléfono</label>
                                <div class="input-group input-group-merge group-form">
                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                        <img src="{{ url('assets/form-groups/admin/10.svg') }}" alt="">
                                    </div>
                                    <input class="form-control" type="text" name="phone"
                                        placeholder="Teléfono"
                                        value="{{ old('phone', $printConfiguration->phone ?? '') }}"
                                        style="border-radius: 0 30px 30px 0;">
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="form-group mt-2 mb-3">
                                <label class="label-control">Email</label>
                                <div class="input-group input-group-merge group-form">
                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                        <img src="{{ url('assets/form-groups/admin/9.svg') }}" alt="">
                                    </div>
                                    <input class="form-control" type="email" name="email"
                                        placeholder="ejemplo@cuentaemail.com"
                                        value="{{ old('email', $printConfiguration->email ?? '') }}"
                                        style="border-radius: 0 30px 30px 0;">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-3 mb-1 g-3">
                    <div class="col-lg-7">
                    <div class="form-group mt-2 mb-0 h-100" style="background-color: #f8f9fa; padding: 1rem; border-radius: 0.75rem; border: 1px solid #ececec;">
                    <h4 class="mb-0 mt-1">Datos de impresión</h4>
                    <small><i>Importes por participación / trasera y tacos</i></small>

                    <div class="row mt-3 g-3">
                        <div class="col-6 col-md-4">
                            <div class="form-group mt-2 mb-3">
                                <label class="label-control">Precio Diseño</label>
                                <div class="input-group input-group-merge group-form">
                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                        <img src="{{ url('assets/form-groups/admin/4.svg') }}" alt="">
                                    </div>
                                    <input class="form-control" type="number" step="0.0001" min="0"
                                        name="price_design"
                                        value="{{ old('price_design', $printConfiguration->price_design ?? 0) }}"
                                        style="border-radius: 0 30px 30px 0;">
                                </div>
                            </div>
                        </div>

                        <div class="col-6 col-md-4">
                            <div class="form-group mt-2 mb-3">
                                <label class="label-control">Precio Participación</label>
                                <div class="input-group input-group-merge group-form">
                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                        <img src="{{ url('assets/form-groups/admin/4.svg') }}" alt="">
                                    </div>
                                    <input class="form-control" type="number" step="0.0001" min="0"
                                        name="price_participation"
                                        value="{{ old('price_participation', $printConfiguration->price_participation ?? 0) }}"
                                        style="border-radius: 0 30px 30px 0;">
                                </div>
                            </div>
                        </div>

                        <div class="col-6 col-md-4">
                            <div class="form-group mt-2 mb-3">
                                <label class="label-control">Precio Trasera (B/N)</label>
                                <div class="input-group input-group-merge group-form">
                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                        <img src="{{ url('assets/form-groups/admin/4.svg') }}" alt="">
                                    </div>
                                    <input class="form-control" type="number" step="0.0001" min="0"
                                        name="price_back_bw"
                                        value="{{ old('price_back_bw', $printConfiguration->price_back_bw ?? 0) }}"
                                        style="border-radius: 0 30px 30px 0;">
                                </div>
                            </div>
                        </div>

                        <div class="col-6 col-md-4">
                            <div class="form-group mt-2 mb-3">
                                <label class="label-control">Precio Trasera (Color)</label>
                                <div class="input-group input-group-merge group-form">
                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                        <img src="{{ url('assets/form-groups/admin/4.svg') }}" alt="">
                                    </div>
                                    <input class="form-control" type="number" step="0.0001" min="0"
                                        name="price_back_color"
                                        value="{{ old('price_back_color', $printConfiguration->price_back_color ?? 0) }}"
                                        style="border-radius: 0 30px 30px 0;">
                                </div>
                            </div>
                        </div>

                        <div class="col-6 col-md-4">
                            <div class="form-group mt-2 mb-3">
                                <label class="label-control">Precio Taco 25</label>
                                <div class="input-group input-group-merge group-form">
                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                        <img src="{{ url('assets/form-groups/admin/4.svg') }}" alt="">
                                    </div>
                                    <input class="form-control" type="number" step="0.0001" min="0"
                                        name="price_taco_25"
                                        value="{{ old('price_taco_25', $printConfiguration->price_taco_25 ?? 0) }}"
                                        style="border-radius: 0 30px 30px 0;">
                                </div>
                            </div>
                        </div>

                        <div class="col-6 col-md-4">
                            <div class="form-group mt-2 mb-3">
                                <label class="label-control">Precio Taco 50</label>
                                <div class="input-group input-group-merge group-form">
                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                        <img src="{{ url('assets/form-groups/admin/4.svg') }}" alt="">
                                    </div>
                                    <input class="form-control" type="number" step="0.0001" min="0"
                                        name="price_taco_50"
                                        value="{{ old('price_taco_50', $printConfiguration->price_taco_50 ?? 0) }}"
                                        style="border-radius: 0 30px 30px 0;">
                                </div>
                            </div>
                        </div>

                        <div class="col-6 col-md-4">
                            <div class="form-group mt-2 mb-3">
                                <label class="label-control">Precio Taco 100</label>
                                <div class="input-group input-group-merge group-form">
                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                        <img src="{{ url('assets/form-groups/admin/4.svg') }}" alt="">
                                    </div>
                                    <input class="form-control" type="number" step="0.0001" min="0"
                                        name="price_taco_100"
                                        value="{{ old('price_taco_100', $printConfiguration->price_taco_100 ?? 0) }}"
                                        style="border-radius: 0 30px 30px 0;">
                                </div>
                            </div>
                        </div>
                    </div>
                    </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="form-group mt-2 mb-0 h-100 d-flex flex-column justify-content-between">
                            <div>
                            <h5 class="mb-3">Datos de pago (Stripe)</h5>
                            <div class="form-group mt-2 mb-3">
                                <label class="label-control">Stripe Publishable Key</label>
                                <div class="input-group input-group-merge group-form">
                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                        <img src="{{ url('assets/form-groups/admin/4.svg') }}" alt="">
                                    </div>
                                    <input class="form-control" type="text" name="stripe_publishable_key"
                                        placeholder="pk_test_..."
                                        value="{{ old('stripe_publishable_key', $printConfiguration->stripe_publishable_key ?? '') }}"
                                        style="border-radius: 0 30px 30px 0;">
                                </div>
                            </div>
                            <div class="form-group mt-2 mb-3">
                                <label class="label-control">Stripe Secret Key</label>
                                <div class="input-group input-group-merge group-form">
                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                        <img src="{{ url('assets/form-groups/admin/4.svg') }}" alt="">
                                    </div>
                                    <input class="form-control" type="password" name="stripe_secret_key"
                                        placeholder="sk_test_..."
                                        value="{{ old('stripe_secret_key', $printConfiguration->stripe_secret_key ?? '') }}"
                                        style="border-radius: 0 30px 30px 0;">
                                </div>
                            </div>
                            <div class="form-group mt-2 mb-3">
                                <label class="label-control">Stripe Webhook Secret</label>
                                <div class="input-group input-group-merge group-form">
                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                        <img src="{{ url('assets/form-groups/admin/4.svg') }}" alt="">
                                    </div>
                                    <input class="form-control" type="password" name="stripe_webhook_secret"
                                        placeholder="whsec_..."
                                        value="{{ old('stripe_webhook_secret', $printConfiguration->stripe_webhook_secret ?? '') }}"
                                        style="border-radius: 0 30px 30px 0;">
                                </div>
                                <div class="form-text text-muted mt-1">
                                    Estas claves se usarán para crear pagos y validar webhooks de Stripe.
                                </div>
                            </div>
                            </div>
                            <div class="text-end mt-2">
                                <button type="submit"
                                    style="border-radius: 30px; min-width: 140px; background-color: #e78307; color: #333; padding: 8px 16px; font-weight: bolder;"
                                    class="btn btn-md btn-light">
                                    <i class="ri-save-line me-1"></i> {{ $printConfiguration->exists ? 'Guardar' : 'Crear imprenta' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
<style>
    .group-form .ts-wrapper {
        flex: 1 1 auto;
    }
    .group-form .ts-wrapper.single .ts-control {
        height: calc(1.5em + 0.9rem + 2px) !important;
        min-height: calc(1.5em + 0.9rem + 2px) !important;
        border-radius: 0 30px 30px 0 !important;
        border-left: 0 !important;
    }
    .group-form .ts-wrapper.single .ts-control input::placeholder {
        color: #6c757d !important;
    }
</style>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script>
    (function () {
        const provinceSelect = document.getElementById('imprenta-province-select');
        const citySelect = document.getElementById('imprenta-city-select');
        if (!provinceSelect || !citySelect) {
            return;
        }

        const provinceCityMap = @json($provinceCityMap ?? []);
        const selectedCity = @json(old('city', $printConfiguration->city ?? ''));
        let provinceTs = null;
        let cityTs = null;

        const fillCities = (province, cityToSelect = '') => {
            const cities = provinceCityMap[province] || [];
            if (cityTs) {
                cityTs.clear(true);
                cityTs.clearOptions();
                cities.forEach((city) => cityTs.addOption({ value: city, text: city }));
                cityTs.refreshOptions(false);
                if (cityToSelect && cities.includes(cityToSelect)) {
                    cityTs.setValue(cityToSelect, true);
                } else {
                    cityTs.clear(true);
                }
                return;
            }

            citySelect.innerHTML = '<option value="">Selecciona localidad</option>';
            cities.forEach((city) => citySelect.add(new Option(city, city)));
            citySelect.value = cityToSelect && cities.includes(cityToSelect) ? cityToSelect : '';
        };

        fillCities(provinceSelect.value, selectedCity);

        if (window.TomSelect) {
            provinceTs = new TomSelect(provinceSelect, {
                create: false,
                allowEmptyOption: true,
                placeholder: 'Seleccionar provincia',
                maxOptions: 60,
                sortField: [{ field: 'text', direction: 'asc' }],
            });

            cityTs = new TomSelect(citySelect, {
                create: false,
                allowEmptyOption: true,
                placeholder: 'Seleccionar localidad',
                maxOptions: 200,
                sortField: [{ field: 'text', direction: 'asc' }],
            });

            provinceTs.on('change', function (value) {
                fillCities(value || '', '');
            });
            if (!provinceSelect.value) {
                provinceTs.clear(true);
            }
            if (!citySelect.value) {
                cityTs.clear(true);
            }
        } else {
            provinceSelect.addEventListener('change', function () {
                fillCities(this.value, '');
            });
        }
    })();
</script>

@if(auth()->user()?->isSuperAdmin() && ($printConfiguration->exists ?? false))
<hr class="my-4">
<div class="form-card bs pb-3">
    <h4 class="mb-0 mt-1">Acceso al panel de imprenta</h4>
    <small class="text-muted"><i>Cuenta dedicada para que la imprenta gestione órdenes de impresión (sin acceso al resto del panel).</i></small>

    @if($printShopPanelUser ?? null)
        <div class="alert alert-light border small mt-3 mb-3">
            <div><strong>Usuario panel:</strong> {{ $printShopPanelUser->panel_login_username ?? '—' }}</div>
            <div><strong>Email acceso:</strong> {{ $printShopPanelUser->email }}</div>
            <div class="mt-1"><a href="{{ route('print-shop.index') }}" class="text-decoration-none">Abrir panel imprenta <i class="ri-external-link-line"></i></a></div>
        </div>
    @else
        <div class="alert alert-warning small mt-3 mb-3">Todavía no hay cuenta de panel para la imprenta. Complétala abajo.</div>
    @endif

    <form action="{{ route('configuration.imprenta.panel-access') }}" method="POST" class="row g-3 mt-1">
        @csrf
        <input type="hidden" name="print_configuration_id" value="{{ $printConfiguration->id }}">
        <div class="col-md-5">
            <label class="label-control">Email de acceso</label>
            <input type="email" name="panel_email" class="form-control" required
                value="{{ old('panel_email', $printShopPanelUser->email ?? ($printConfiguration->email ?? '')) }}"
                style="border-radius: 30px;">
        </div>
        <div class="col-md-3">
            <label class="label-control">Nueva contraseña</label>
            <input type="password" name="panel_password" class="form-control" autocomplete="new-password"
                placeholder="{{ ($printShopPanelUser ?? null) ? 'Opcional' : 'Mín. 8 caracteres' }}"
                style="border-radius: 30px;">
        </div>
        <div class="col-md-3">
            <label class="label-control">Confirmar contraseña</label>
            <input type="password" name="panel_password_confirmation" class="form-control" autocomplete="new-password" style="border-radius: 30px;">
        </div>
        <div class="col-md-1 d-flex align-items-end">
            <button type="submit" class="btn btn-dark w-100" style="border-radius: 30px;">
                <i class="ri-save-line"></i>
            </button>
        </div>
    </form>
    @if(!($printShopPanelUser ?? null))
        <p class="form-text small mb-0 mt-2">Si no indicas contraseña, se generará una aleatoria y se enviará por correo a la imprenta junto con el enlace de acceso.</p>
    @else
        <p class="form-text small mb-0 mt-2">Al guardar se enviará un correo a la imprenta con el enlace de acceso. Si indicas una nueva contraseña, también se incluirá en el correo.</p>
    @endif
</div>
@endif
