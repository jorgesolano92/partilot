@if(!empty($panelLegalAcceptanceModal))
<div class="modal fade" id="panelLegalAcceptanceModal" tabindex="-1" aria-labelledby="panelLegalAcceptanceModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="panelLegalAcceptanceModalLabel">
                    Aceptación de condiciones legales
                </h5>
            </div>
            <div class="modal-body pt-2">
                @if(!empty($panelLegalAcceptanceModal['intro']))
                    <p class="text-muted mb-3">{{ $panelLegalAcceptanceModal['intro'] }}</p>
                @endif

                <p class="mb-2">Consulte los siguientes documentos:</p>
                <ul class="mb-4 ps-3">
                    @foreach(($panelLegalAcceptanceModal['documents'] ?? []) as $document)
                        <li class="mb-2">
                            <a href="{{ $document['url'] }}" target="_blank" rel="noopener">
                                {{ $document['title'] }}
                                @if(!empty($document['version']))
                                    <span class="text-muted">(v{{ $document['version'] }})</span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>

                @if ($errors->has('accept_legal'))
                    <div class="alert alert-danger py-2">{{ $errors->first('accept_legal') }}</div>
                @endif

                <form method="post" action="{{ route('panel-legal.submit') }}" id="panelLegalAcceptanceForm">
                    @csrf
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" name="accept_legal" value="1" id="panel_accept_legal" required {{ old('accept_legal') ? 'checked' : '' }}>
                        <label class="form-check-label" for="panel_accept_legal">
                            {{ $panelLegalAcceptanceModal['checkbox_label'] ?? '' }}
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pt-0 d-flex flex-wrap justify-content-between gap-2">
                <form method="POST" action="{{ route('logout') }}" class="m-0">
                    @csrf
                    <button type="submit" class="btn btn-light rounded-pill px-4">Cerrar sesión</button>
                </form>
                <button type="submit" form="panelLegalAcceptanceForm" class="btn btn-dark rounded-pill px-4">
                    Aceptar y continuar
                </button>
            </div>
        </div>
    </div>
</div>
@endif
