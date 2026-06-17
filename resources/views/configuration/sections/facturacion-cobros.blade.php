@if(!empty($configurationAdministrationScoped) && !empty($settingsAdministration))
    @php
        $administration = $settingsAdministration;
        $accountValue = $administration->account ?? '';
        if ($accountValue && str_starts_with($accountValue, 'ES')) {
            $accountValue = substr($accountValue, 2);
        }
        $accountValue = preg_replace('/\s+/', '', (string) $accountValue);
        $formatAccount = function (?string $value): string {
            $digits = preg_replace('/\D/', '', (string) $value);
            if ($digits === '') {
                return '';
            }
            if (strlen($digits) >= 2) {
                $formatted = substr($digits, 0, 2);
                if (strlen($digits) > 2) {
                    $formatted .= ' '.substr($digits, 2, 4);
                    if (strlen($digits) > 6) {
                        $formatted .= ' '.substr($digits, 6, 4);
                        if (strlen($digits) > 10) {
                            $formatted .= ' '.substr($digits, 10, 2);
                            if (strlen($digits) > 12) {
                                $formatted .= ' '.substr($digits, 12);
                            }
                        }
                    }
                }
                return $formatted;
            }
            return $digits;
        };
    @endphp
    <div class="form-card bs pb-3" style="min-height: 658px;">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <form method="POST" action="{{ route('configuration.administration-billing.update') }}">
            @csrf
            <div class="row">
                <div class="col-lg-7">
                    <h4 class="mb-0 mt-1">Datos legales de facturación</h4>
                    <small><i>Estos datos se usan en las órdenes de pago de sus entidades</i></small>

                    <div class="row mt-3">
                        <div class="col-md-6">
                            <label class="label-control">Nombre comercial</label>
                            <input class="form-control" type="text" value="{{ $administration->name }}" readonly style="border-radius: 30px; background:#f8f9fa;">
                        </div>
                        <div class="col-md-6">
                            <label class="label-control">Sociedad</label>
                            <input class="form-control" type="text" value="{{ $administration->society ?? '—' }}" readonly style="border-radius: 30px; background:#f8f9fa;">
                        </div>
                        <div class="col-md-6 mt-2">
                            <label class="label-control">NIF/CIF</label>
                            <input class="form-control" type="text" value="{{ $administration->nif_cif ?? '—' }}" readonly style="border-radius: 30px; background:#f8f9fa;">
                        </div>
                        <div class="col-md-12 mt-2">
                            <label class="label-control">Dirección</label>
                            <input class="form-control" type="text" value="{{ $administration->address ?? '—' }}" readonly style="border-radius: 30px; background:#f8f9fa;">
                        </div>
                        <div class="col-md-4 mt-2">
                            <label class="label-control">Provincia</label>
                            <input class="form-control" type="text" value="{{ $administration->province ?? '—' }}" readonly style="border-radius: 30px; background:#f8f9fa;">
                        </div>
                        <div class="col-md-4 mt-2">
                            <label class="label-control">Localidad</label>
                            <input class="form-control" type="text" value="{{ $administration->city ?? '—' }}" readonly style="border-radius: 30px; background:#f8f9fa;">
                        </div>
                        <div class="col-md-4 mt-2">
                            <label class="label-control">Código postal</label>
                            <input class="form-control" type="text" value="{{ $administration->postal_code ?? '—' }}" readonly style="border-radius: 30px; background:#f8f9fa;">
                        </div>
                        <div class="col-md-6 mt-2">
                            <label class="label-control">Teléfono</label>
                            <input class="form-control" type="text" value="{{ $administration->phone ?? '—' }}" readonly style="border-radius: 30px; background:#f8f9fa;">
                        </div>
                        <div class="col-md-6 mt-2">
                            <label class="label-control">Email</label>
                            <input class="form-control" type="text" value="{{ $administration->email ?? '—' }}" readonly style="border-radius: 30px; background:#f8f9fa;">
                        </div>
                    </div>
                    <p class="small text-muted mt-3 mb-0">Para modificar los datos legales, use la sección <a href="{{ url('/configuration?section=datos-administracion') }}">Mis datos</a>.</p>
                </div>

                <div class="col-lg-5">
                    <h4 class="mb-0 mt-1">Datos pago</h4>
                    <small><i>Cuenta bancaria de la administración para cobros SEPA</i></small>

                    <div class="form-group mt-3 mb-3">
                        <label class="label-control">Cuenta bancaria (IBAN)</label>
                        <div class="input-group input-group-merge group-form">
                            <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                <span style="font-weight: bold;">ES</span>
                            </div>
                            <input class="form-control @error('account') is-invalid @enderror" type="text" name="account" value="{{ old('account', $formatAccount($accountValue)) }}" placeholder="12 1234 1234 12 1234567890" style="border-radius: 0 30px 30px 0;">
                        </div>
                        @error('account')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                        @error('iban')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="text-end mt-4">
                        <button type="submit" class="btn btn-md btn-light" style="border-radius: 30px; min-width: 140px; background-color: #e78307; color: #333; font-weight: bolder;">
                            <i class="fe-save me-1"></i> Guardar
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
@elseif(!empty($configurationEntityScoped) && !empty($settingsEntity))
    @php
        $entity = $settingsEntity;
        $formatIban = function (?string $iban): string {
            $digits = preg_replace('/\s+/', '', strtoupper((string) $iban));
            if ($digits === '') {
                return '';
            }
            return trim(chunk_split($digits, 4, ' '));
        };
    @endphp
    <div class="form-card bs pb-3" style="min-height: 658px;">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <form method="POST" action="{{ route('configuration.entity-billing.update') }}">
            @csrf
            <div class="row">
                <div class="col-lg-7">
                    <h4 class="mb-0 mt-1">Datos legales de facturación</h4>
                    <small><i>Estos datos se usan en las órdenes de pago de su entidad</i></small>

                    <div class="row mt-3">
                        <div class="col-md-6">
                            <label class="label-control">Nombre comercial</label>
                            <input class="form-control" type="text" value="{{ $entity->name }}" readonly style="border-radius: 30px; background:#f8f9fa;">
                        </div>
                        <div class="col-md-6">
                            <label class="label-control">NIF/CIF</label>
                            <input class="form-control" type="text" value="{{ $entity->nif_cif ?? '—' }}" readonly style="border-radius: 30px; background:#f8f9fa;">
                        </div>
                        <div class="col-md-12 mt-2">
                            <label class="label-control">Dirección</label>
                            <input class="form-control" type="text" value="{{ $entity->address ?? '—' }}" readonly style="border-radius: 30px; background:#f8f9fa;">
                        </div>
                        <div class="col-md-4 mt-2">
                            <label class="label-control">Provincia</label>
                            <input class="form-control" type="text" value="{{ $entity->province ?? '—' }}" readonly style="border-radius: 30px; background:#f8f9fa;">
                        </div>
                        <div class="col-md-4 mt-2">
                            <label class="label-control">Localidad</label>
                            <input class="form-control" type="text" value="{{ $entity->city ?? '—' }}" readonly style="border-radius: 30px; background:#f8f9fa;">
                        </div>
                        <div class="col-md-4 mt-2">
                            <label class="label-control">Código postal</label>
                            <input class="form-control" type="text" value="{{ $entity->postal_code ?? '—' }}" readonly style="border-radius: 30px; background:#f8f9fa;">
                        </div>
                        <div class="col-md-6 mt-2">
                            <label class="label-control">Teléfono</label>
                            <input class="form-control" type="text" value="{{ $entity->phone ?? '—' }}" readonly style="border-radius: 30px; background:#f8f9fa;">
                        </div>
                        <div class="col-md-6 mt-2">
                            <label class="label-control">Email</label>
                            <input class="form-control" type="text" value="{{ $entity->email ?? '—' }}" readonly style="border-radius: 30px; background:#f8f9fa;">
                        </div>
                    </div>
                    <p class="small text-muted mt-3 mb-0">Para modificar los datos legales, use la sección <a href="{{ url('/configuration?section=datos-entidad') }}">Mis datos</a>.</p>
                </div>

                <div class="col-lg-5">
                    <h4 class="mb-0 mt-1">Datos pago</h4>
                    <small><i>Cuenta bancaria para recibir cobros de participaciones</i></small>

                    <div class="form-group mt-3 mb-3">
                        <label class="label-control">Cuenta bancaria (IBAN)</label>
                        <div class="input-group input-group-merge group-form">
                            <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                <img src="{{ url('assets/form-groups/admin/4.svg') }}" alt="">
                            </div>
                            <input class="form-control @error('billing_iban') is-invalid @enderror" type="text" name="billing_iban" value="{{ old('billing_iban', $formatIban($entity->billing_iban)) }}" placeholder="ES00 0000 0000 0000 0000 0000" style="border-radius: 0 30px 30px 0; letter-spacing: 0.05em;">
                        </div>
                        @error('billing_iban')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="text-end mt-4">
                        <button type="submit" class="btn btn-md btn-light" style="border-radius: 30px; min-width: 140px; background-color: #e78307; color: #333; font-weight: bolder;">
                            <i class="fe-save me-1"></i> Guardar
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
@elseif(!empty($showBillingRemittancePanel))
    @include('configuration.sections.partials.facturacion-cobros-remesas')
@else
<div class="alert alert-info mb-0">
    <i class="fe-info me-2"></i>
    No hay opciones de facturación disponibles para su perfil en esta sección.
</div>
@endif
