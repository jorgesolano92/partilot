@php
    $entity = $settingsEntity;
    $panelUser = $settingsPanelUser ?? auth()->user();
    $activeTab = request('tab', 'legales');
@endphp

<div class="form-card bs" style="min-height: 658px;">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row">
        <div class="col-md-3">
            <div class="form-card bs mb-3">
                <div class="form-wizard-element {{ $activeTab === 'legales' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#entidad-tab-legales" role="button">
                    <span>&nbsp;&nbsp;</span>
                    <img src="{{ url('assets/entidad.svg') }}" alt="">
                    <label>Datos legales</label>
                </div>
                <div class="form-wizard-element {{ $activeTab === 'gestores' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#entidad-tab-gestores" role="button">
                    <span>&nbsp;&nbsp;</span>
                    <img src="{{ url('assets/gestor.svg') }}" alt="">
                    <label>Datos gestores</label>
                </div>
            </div>
        </div>

        <div class="col-md-9">
            <div class="tab-content">
                <div class="tab-pane fade {{ $activeTab === 'legales' ? 'show active' : '' }}" id="entidad-tab-legales">
                    <h4 class="mb-0 mt-1">Datos legales de la entidad</h4>
                    <small><i>Solo lectura. Los datos de la entidad los modifica su administración.</i></small>

                    <div class="alert alert-info mt-3 py-2">
                        Si necesita corregir nombre, NIF, dirección o email de la entidad, contacte con su administración de lotería.
                    </div>

                    <div class="row mt-2">
                        <div class="col-md-6">
                            <div class="form-group mt-2 mb-3">
                                <label class="label-control">Nombre comercial</label>
                                <input class="form-control" type="text" value="{{ $entity->name }}" readonly style="border-radius: 30px; background: #f8f9fa;">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group mt-2 mb-3">
                                <label class="label-control">NIF/CIF</label>
                                <input class="form-control" type="text" value="{{ $entity->nif_cif }}" readonly style="border-radius: 30px; background: #f8f9fa;">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group mt-2 mb-3">
                                <label class="label-control">Teléfono</label>
                                <input class="form-control" type="text" value="{{ $entity->phone }}" readonly style="border-radius: 30px; background: #f8f9fa;">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group mt-2 mb-3">
                                <label class="label-control">Provincia</label>
                                <input class="form-control" type="text" value="{{ $entity->province }}" readonly style="border-radius: 30px; background: #f8f9fa;">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group mt-2 mb-3">
                                <label class="label-control">Localidad</label>
                                <input class="form-control" type="text" value="{{ $entity->city }}" readonly style="border-radius: 30px; background: #f8f9fa;">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group mt-2 mb-3">
                                <label class="label-control">Código Postal</label>
                                <input class="form-control" type="text" value="{{ $entity->postal_code }}" readonly style="border-radius: 30px; background: #f8f9fa;">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group mt-2 mb-3">
                                <label class="label-control">Email</label>
                                <input class="form-control" type="email" value="{{ $entity->email }}" readonly style="border-radius: 30px; background: #f8f9fa;">
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-group mt-2 mb-3">
                                <label class="label-control">Dirección</label>
                                <input class="form-control" type="text" value="{{ $entity->address }}" readonly style="border-radius: 30px; background: #f8f9fa;">
                            </div>
                        </div>
                    </div>

                    <hr>

                    <form method="POST" action="{{ route('configuration.entity-settings.update') }}">
                        @csrf
                        <h4 class="mb-0 mt-1">Datos de acceso</h4>
                        <small><i>Puede cambiar la contraseña de esta cuenta de panel. El email de acceso lo gestiona la administración.</i></small>

                        <div class="row mt-2">
                            <div class="col-md-6">
                                <div class="form-group mt-2 mb-3">
                                    <label class="label-control">Email de acceso</label>
                                    <input class="form-control" type="email" value="{{ $panelUser->email ?? $entity->email }}" readonly style="border-radius: 30px; background: #f8f9fa;">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group mt-2 mb-3">
                                    <label class="label-control">Contraseña actual</label>
                                    <input class="form-control" type="password" name="current_password" autocomplete="current-password" style="border-radius: 30px;">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group mt-2 mb-3">
                                    <label class="label-control">Nueva contraseña</label>
                                    <input class="form-control" type="password" name="panel_password" autocomplete="new-password" style="border-radius: 30px;">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group mt-2 mb-3">
                                    <label class="label-control">Repetir contraseña</label>
                                    <input class="form-control" type="password" name="panel_password_confirmation" autocomplete="new-password" style="border-radius: 30px;">
                                </div>
                            </div>
                        </div>

                        <div class="text-end mt-2">
                            <button type="submit" class="btn btn-md btn-light" style="border-radius: 30px; min-width: 180px; background-color: #e78307; color: #333; font-weight: bolder;">
                                Cambiar contraseña <i class="ri-arrow-right-circle-line ms-1"></i>
                            </button>
                        </div>
                    </form>
                </div>

                <div class="tab-pane fade {{ $activeTab === 'gestores' ? 'show active' : '' }}" id="entidad-tab-gestores">
                    <h4 class="mb-0 mt-1">Gestores de la entidad</h4>
                    <small><i>Solo consulta. Los datos personales de cada gestor los cambia el propio usuario desde su cuenta, no desde este panel.</i></small>

                    @forelse($settingsManagers as $manager)
                        @php($managerUser = $manager->user)
                        <div class="border rounded p-3 mt-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <strong>{{ trim(($managerUser->name ?? '').' '.($managerUser->last_name ?? '')) ?: 'Gestor #'.$manager->id }}</strong>
                                @if($manager->is_primary)
                                    <span class="badge bg-secondary">Principal</span>
                                @endif
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-2">
                                    <label class="form-label small">Nombre</label>
                                    <input class="form-control form-control-sm" value="{{ $managerUser->name ?? '' }}" readonly style="border-radius: 20px; background: #f8f9fa;">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label small">Primer apellido</label>
                                    <input class="form-control form-control-sm" value="{{ $managerUser->last_name ?? '' }}" readonly style="border-radius: 20px; background: #f8f9fa;">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label small">Segundo apellido</label>
                                    <input class="form-control form-control-sm" value="{{ $managerUser->last_name2 ?? '' }}" readonly style="border-radius: 20px; background: #f8f9fa;">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label small">NIF/CIF</label>
                                    <input class="form-control form-control-sm" value="{{ $managerUser->nif_cif ?? '' }}" readonly style="border-radius: 20px; background: #f8f9fa;">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label small">Email</label>
                                    <input class="form-control form-control-sm" type="email" value="{{ $managerUser->email ?? '' }}" readonly style="border-radius: 20px; background: #f8f9fa;">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label small">Teléfono</label>
                                    <input class="form-control form-control-sm" value="{{ $managerUser->phone ?? '' }}" readonly style="border-radius: 20px; background: #f8f9fa;">
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="alert alert-info mt-3 mb-0">
                            No hay gestores secundarios registrados. Si necesita añadir gestores, contacte con su administración.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
