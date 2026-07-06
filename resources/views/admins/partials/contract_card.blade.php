<div class="row mt-4">
    <div class="col-12">
        <h4 class="mb-0 mt-1">Contrato SaaS PARTILOT</h4>
        <small class="text-muted">Contrato de prestación de servicios entre la administración titular y PARTILOT. El enlace de firma se envía al correo de acceso al panel de la administración (no al gestor de contacto).</small>
    </div>
    <div class="col-md-4 mt-3">
        <label class="label-control">Estado</label>
        <div>
            <span class="badge bg-{{ $administration->contract_status_class }}">{{ $administration->contract_status_text }}</span>
        </div>
    </div>
    <div class="col-md-4 mt-3">
        <label class="label-control">Referencia</label>
        <input readonly class="form-control" value="{{ $administration->contract_reference ?? '—' }}" style="border-radius: 30px;">
    </div>
    <div class="col-md-4 mt-3">
        <label class="label-control">Enviado / Firmado</label>
        <input readonly class="form-control" value="{{ $administration->contract_sent_at ? $administration->contract_sent_at->format('d/m/Y H:i') : '—' }} / {{ $administration->contract_signed_at ? $administration->contract_signed_at->format('d/m/Y H:i') : '—' }}" style="border-radius: 30px;">
    </div>
    @if($administration->contract_signer_name)
    <div class="col-md-6 mt-3">
        <label class="label-control">Firmante</label>
        <input readonly class="form-control" value="{{ $administration->contract_signer_name }} ({{ $administration->contract_signer_nif }})" style="border-radius: 30px;">
    </div>
    @endif
    <div class="col-12 mt-3 d-flex flex-wrap gap-2">
        <a href="{{ route('administrations.contract-preview', $administration) }}" target="_blank" class="btn btn-light" style="border: 1px solid silver; border-radius: 30px;">
            <i class="ri-file-pdf-line"></i> Vista previa PDF
        </a>
        @if($administration->hasSignedSaasContract() && $administration->contract_pdf_path)
            <a href="{{ route('administrations.contract-download', $administration) }}" class="btn btn-light" style="border: 1px solid silver; border-radius: 30px;">
                <i class="ri-download-line"></i> Descargar firmado
            </a>
        @endif
        @if(!$administration->hasSignedSaasContract())
            <form method="post" action="{{ route('administrations.send-contract', $administration) }}" class="d-inline" onsubmit="return confirm('¿Enviar o reenviar el correo con el enlace de firma del contrato SaaS?');">
                @csrf
                <button type="submit" class="btn btn-dark" style="border-radius: 30px;">
                    <i class="ri-mail-send-line"></i> {{ $administration->contract_sent_at ? 'Reenviar contrato' : 'Enviar contrato' }}
                </button>
            </form>
        @endif
    </div>
</div>
