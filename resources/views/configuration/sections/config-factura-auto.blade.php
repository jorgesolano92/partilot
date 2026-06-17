@php
    $settings = $partilotBillingSettings ?? \App\Models\PartilotBillingSetting::current();
@endphp

<div class="form-card bs pb-3" style="min-height: 658px;">
    @if(session('success') && request('section') === 'config-factura-auto')
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form action="{{ route('configuration.partilot-billing.update') }}" method="POST" id="form-config-factura-auto">
        @csrf

        <h4 class="mb-0 mt-1">
            Datos legales PARTILOT
        </h4>
        <small><i>Todos los campos son obligatorios</i></small>

        <div class="form-group mt-2 mb-3">
            <div class="row">
                <div class="col-6">
                    <div class="form-group mt-2 mb-3">
                        <label class="label-control">Empresa</label>
                        <div class="input-group input-group-merge group-form">
                            <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                <img src="{{ url('assets/form-groups/admin/1.svg') }}" alt="">
                            </div>
                            <input class="form-control" type="text" name="company_name" id="empresa" placeholder="Empresa" value="{{ old('company_name', $settings->company_name) }}" required style="border-radius: 0 30px 30px 0;">
                        </div>
                    </div>
                </div>

                <div class="col-6">
                    <div class="form-group mt-2 mb-3">
                        <label class="label-control">NIF/CIF</label>
                        <div class="input-group input-group-merge group-form">
                            <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                <img src="{{ url('assets/form-groups/admin/4.svg') }}" alt="">
                            </div>
                            <input class="form-control" type="text" name="nif_cif" id="nif-cif-imprenta" placeholder="NIF/CIF" value="{{ old('nif_cif', $settings->nif_cif) }}" required style="border-radius: 0 30px 30px 0;">
                        </div>
                    </div>
                </div>

                <div class="col-6">
                    <div class="form-group mt-2 mb-3">
                        <label class="label-control">Dirección</label>
                        <div class="input-group input-group-merge group-form">
                            <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                <img src="{{ url('assets/form-groups/admin/8.svg') }}" alt="">
                            </div>
                            <input class="form-control" type="text" name="address" id="direccion-imprenta" placeholder="Dirección" value="{{ old('address', $settings->address) }}" required style="border-radius: 0 30px 30px 0;">
                        </div>
                    </div>
                </div>

                <div class="col-3">
                    <div class="form-group mt-2 mb-3">
                        <label class="label-control">Código Postal</label>
                        <div class="input-group input-group-merge group-form">
                            <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                <img src="{{ url('assets/form-groups/admin/7.svg') }}" alt="">
                            </div>
                            <input class="form-control" type="text" name="postal_code" id="codigo-postal-imprenta" placeholder="C.P." value="{{ old('postal_code', $settings->postal_code) }}" required style="border-radius: 0 30px 30px 0;">
                        </div>
                    </div>
                </div>

                <div class="col-3">
                    <div class="form-group mt-2 mb-3">
                        <label class="label-control">Provincia</label>
                        <div class="input-group input-group-merge group-form">
                            <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                <img src="{{ url('assets/form-groups/admin/5.svg') }}" alt="">
                            </div>
                            <input class="form-control" type="text" name="province" id="provincia-imprenta" placeholder="Provincia" value="{{ old('province', $settings->province) }}" required style="border-radius: 0 30px 30px 0;">
                        </div>
                    </div>
                </div>

                <div class="col-3">
                    <div class="form-group mt-2 mb-3">
                        <label class="label-control">Localidad</label>
                        <div class="input-group input-group-merge group-form">
                            <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                <img src="{{ url('assets/form-groups/admin/6.svg') }}" alt="">
                            </div>
                            <input class="form-control" type="text" name="city" id="localidad-imprenta" placeholder="Localidad" value="{{ old('city', $settings->city) }}" required style="border-radius: 0 30px 30px 0;">
                        </div>
                    </div>
                </div>

                <div class="col-3">
                    <div class="form-group mt-2 mb-3">
                        <label class="label-control">Teléfono</label>
                        <div class="input-group input-group-merge group-form">
                            <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                <img src="{{ url('assets/form-groups/admin/10.svg') }}" alt="">
                            </div>
                            <input class="form-control" type="text" name="phone" id="telefono-imprenta" placeholder="941 900 900" value="{{ old('phone', $settings->phone) }}" required style="border-radius: 0 30px 30px 0;">
                        </div>
                    </div>
                </div>

                <div class="col-6">
                    <div class="form-group mt-2 mb-3">
                        <label class="label-control">Email</label>
                        <div class="input-group input-group-merge group-form">
                            <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                <img src="{{ url('assets/form-groups/admin/9.svg') }}" alt="">
                            </div>
                            <input class="form-control" type="email" name="email" id="email-imprenta" placeholder="ejemplo@cuentaemail.com" value="{{ old('email', $settings->email) }}" required style="border-radius: 0 30px 30px 0;">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-3 mb-1 g-3">
            <div class="col-lg-7">
                <div class="form-group mt-2 mb-0 h-100" style="background-color: #f8f9fa; padding: 1rem; border-radius: 0.75rem; border: 1px solid #ececec;">
                    <h4 class="mb-0 mt-1">Precio Gestión</h4>
                    <small><i>Todos los campos son obligatorios. Importe por participación (€).</i></small>

                    <div class="row mt-2">
                        <div class="col-6 col-md-4">
                            <div class="form-group mt-2 mb-3">
                                <label class="label-control">Gestión Particip. 1000Un</label>
                                <div class="input-group input-group-merge group-form">
                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                        <img src="{{ url('assets/form-groups/admin/4.svg') }}" alt="">
                                    </div>
                                    <input class="form-control" type="number" step="0.0001" min="0" name="fee_per_participation_1000" id="gestion-1000" placeholder="0,05" value="{{ old('fee_per_participation_1000', $settings->fee_per_participation_1000) }}" required style="border-radius: 0 30px 30px 0;">
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="form-group mt-2 mb-3">
                                <label class="label-control">Gestión Part. Administración</label>
                                <div class="input-group input-group-merge group-form">
                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                        <img src="{{ url('assets/form-groups/admin/4.svg') }}" alt="">
                                    </div>
                                    <input class="form-control" type="number" step="0.0001" min="0" name="fee_administration_per_participation" id="gestion-administracion" placeholder="0,03" value="{{ old('fee_administration_per_participation', $settings->fee_administration_per_participation) }}" required style="border-radius: 0 30px 30px 0;">
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="form-group mt-2 mb-3">
                                <label class="label-control">Gestión Particip. 5000Un</label>
                                <div class="input-group input-group-merge group-form">
                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                        <img src="{{ url('assets/form-groups/admin/4.svg') }}" alt="">
                                    </div>
                                    <input class="form-control" type="number" step="0.0001" min="0" name="fee_per_participation_5000" id="gestion-5000" placeholder="0,04" value="{{ old('fee_per_participation_5000', $settings->fee_per_participation_5000) }}" required style="border-radius: 0 30px 30px 0;">
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="form-group mt-2 mb-3">
                                <label class="label-control">Comisión Gestión Pago</label>
                                <div class="input-group input-group-merge group-form">
                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                        <img src="{{ url('assets/form-groups/admin/4.svg') }}" alt="">
                                    </div>
                                    <input class="form-control" type="number" step="0.0001" min="0" name="payment_management_commission" id="comision-gestion-pago" placeholder="0,03" value="{{ old('payment_management_commission', $settings->payment_management_commission) }}" required style="border-radius: 0 30px 30px 0;">
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="form-group mt-2 mb-3">
                                <label class="label-control">Gestión Particip. 10000Un</label>
                                <div class="input-group input-group-merge group-form">
                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                        <img src="{{ url('assets/form-groups/admin/4.svg') }}" alt="">
                                    </div>
                                    <input class="form-control" type="number" step="0.0001" min="0" name="fee_per_participation_10000" id="gestion-10000" placeholder="0,03" value="{{ old('fee_per_participation_10000', $settings->fee_per_participation_10000) }}" required style="border-radius: 0 30px 30px 0;">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="form-group mt-2 mb-0 h-100 d-flex flex-column justify-content-between">
                    <div>
                        <h4 class="mb-0 mt-1">Datos Pago</h4>
                        <small><i>Introduzca los detalles de su cuenta bancaria para procesar los pagos.</i></small>

                        <div class="form-group mt-3 mb-3">
                            <label class="label-control">Cuenta bancaria (IBAN)</label>
                            <div class="input-group input-group-merge group-form">
                                <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                    <img src="{{ url('assets/form-groups/admin/4.svg') }}" alt="">
                                </div>
                                <input class="form-control" type="text" name="bank_account" id="iban" placeholder="1234 - 1234 - 1234 - 12 - 1234567890" value="{{ old('bank_account', $settings->bank_account) }}" required style="border-radius: 0 30px 30px 0; letter-spacing: 0.05em;">
                            </div>
                        </div>

                        <div class="form-group mt-2 mb-3">
                            <label class="label-control">Identificador acreedor SEPA (pain.008)</label>
                            <input class="form-control" type="text" name="sepa_creditor_id" value="{{ old('sepa_creditor_id', $settings->sepa_creditor_id) }}" placeholder="ESXXXXXXXXX001 (opcional, se deriva del NIF)">
                            <small class="text-muted">Necesario para generar adeudos a administraciones en remesa.</small>
                        </div>

                        <h5 class="mb-2 mt-4">Stripe (cuota gestión PARTILOT)</h5>
                        <div class="form-group mt-2 mb-3">
                            <label class="label-control">Publishable Key</label>
                            <input class="form-control" type="text" name="stripe_publishable_key" value="{{ old('stripe_publishable_key', $settings->stripe_publishable_key) }}" placeholder="pk_test_...">
                        </div>
                        <div class="form-group mt-2 mb-3">
                            <label class="label-control">Secret Key</label>
                            <input class="form-control" type="password" name="stripe_secret_key" value="{{ old('stripe_secret_key', $settings->stripe_secret_key) }}" placeholder="sk_test_..." autocomplete="new-password">
                        </div>
                        <div class="form-group mt-2 mb-3">
                            <label class="label-control">Webhook Secret</label>
                            <input class="form-control" type="password" name="stripe_webhook_secret" value="{{ old('stripe_webhook_secret', $settings->stripe_webhook_secret) }}" placeholder="whsec_..." autocomplete="new-password">
                        </div>
                    </div>

                    <div class="text-end mt-2">
                        <button type="submit" id="btn-guardar-config-factura" style="border-radius: 30px; min-width: 140px; background-color: #e78307; color: #333; padding: 8px 16px; font-weight: bolder;" class="btn btn-md btn-light">
                            <i class="fe-save me-1"></i> Guardar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
