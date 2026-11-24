{{-- Modal: confirmar datos del pagador antes de descargar XML SEPA pain.001 --}}
<div class="modal fade" id="modalSepaConfirmDebtor" tabindex="-1" aria-labelledby="modalSepaConfirmDebtorLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form id="form-sepa-confirm-debtor" method="POST" action="#">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="modalSepaConfirmDebtorLabel">Confirmar datos del pagador</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        Estos datos salen en el XML como <strong>ordenante</strong>. Deben coincidir con el titular de la cuenta del banco
                        (sociedad → CIF; persona → NIF). Revísalos antes de descargar.
                    </p>
                    <div id="sepa-confirm-debtor-error" class="alert alert-danger d-none" role="alert"></div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="sepa_debtor_name">Nombre / razón social <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="sepa_debtor_name" name="debtor_name" required maxlength="255">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="sepa_debtor_nif_cif">NIF / CIF</label>
                            <input type="text" class="form-control" id="sepa_debtor_nif_cif" name="debtor_nif_cif" maxlength="50">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="sepa_debtor_iban">IBAN <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text fw-bold">ES</span>
                                <input type="text" class="form-control" id="sepa_debtor_iban" name="debtor_iban" required maxlength="22" placeholder="22 dígitos" inputmode="numeric">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="sepa_execution_date">Fecha de ejecución <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="sepa_execution_date" name="execution_date" required min="{{ date('Y-m-d') }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="sepa_debtor_address">Dirección</label>
                            <input type="text" class="form-control" id="sepa_debtor_address" name="debtor_address" maxlength="500">
                        </div>
                    </div>
                    <p class="small text-muted mt-3 mb-0">
                        Orden: <code id="sepa_confirm_message_id">—</code>
                        · Importe control: <strong id="sepa_confirm_control_sum">—</strong> €
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btn-sepa-confirm-download" style="background-color: #e78307; border-color: #e78307; color: #333;">
                        <i class="ri-download-line me-1"></i> Confirmar y descargar XML
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    function stripEsPrefix(iban) {
        var clean = String(iban || '').replace(/\s+/g, '').toUpperCase();
        return clean.indexOf('ES') === 0 ? clean.slice(2) : clean;
    }

    function showError(msg) {
        var el = document.getElementById('sepa-confirm-debtor-error');
        if (!el) return;
        el.textContent = msg || 'No se pudieron cargar los datos del pagador.';
        el.classList.remove('d-none');
    }

    function clearError() {
        var el = document.getElementById('sepa-confirm-debtor-error');
        if (!el) return;
        el.textContent = '';
        el.classList.add('d-none');
    }

    window.openSepaConfirmDebtorModal = function (orderId) {
        if (!orderId) return;
        clearError();

        var modalEl = document.getElementById('modalSepaConfirmDebtor');
        var form = document.getElementById('form-sepa-confirm-debtor');
        if (!modalEl || !form) return;

        var url = '{{ url("/sepa-payments") }}/' + orderId + '/debtor-data';
        fetch(url, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(function (data) {
                form.action = data.confirm_url;
                document.getElementById('sepa_debtor_name').value = data.debtor_name || '';
                document.getElementById('sepa_debtor_nif_cif').value = data.debtor_nif_cif || '';
                document.getElementById('sepa_debtor_iban').value = stripEsPrefix(data.debtor_iban || data.debtor_iban_full || '');
                document.getElementById('sepa_debtor_address').value = data.debtor_address || '';
                document.getElementById('sepa_execution_date').value = data.execution_date || '{{ date("Y-m-d") }}';
                document.getElementById('sepa_confirm_message_id').textContent = data.message_id || ('#' + orderId);
                document.getElementById('sepa_confirm_control_sum').textContent = data.control_sum || '0.00';

                var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                modal.show();
            })
            .catch(function () {
                showError('No se pudieron cargar los datos del pagador. Recarga e inténtalo de nuevo.');
                var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                modal.show();
            });
    };

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-sepa-confirm-download]').forEach(function (el) {
            el.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var orderId = el.getAttribute('data-sepa-confirm-download');
                window.openSepaConfirmDebtorModal(orderId);
            });
        });

        var form = document.getElementById('form-sepa-confirm-debtor');
        if (form) {
            form.addEventListener('submit', function () {
                var ibanInput = document.getElementById('sepa_debtor_iban');
                if (ibanInput) {
                    ibanInput.value = stripEsPrefix(ibanInput.value);
                }
            });
        }

        @if(session('sepa_confirm_debtor_order_id'))
            window.openSepaConfirmDebtorModal({{ (int) session('sepa_confirm_debtor_order_id') }});
        @endif
    });
})();
</script>
