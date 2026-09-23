/**
 * Confirmaciones y avisos Partilot (MEJ-005 / INC-004).
 * Sustituye confirm()/alert() nativos en flujos auditados del panel.
 */
(function (window, document) {
    'use strict';

    function ensureConfirmModal() {
        var existing = document.getElementById('partilot-confirm-modal');
        if (existing) {
            return existing;
        }

        var html =
            '<div class="modal fade" id="partilot-confirm-modal" tabindex="-1" aria-hidden="true">' +
            '  <div class="modal-dialog modal-dialog-centered">' +
            '    <div class="modal-content" style="border-radius: 16px;">' +
            '      <div class="modal-header border-0">' +
            '        <h5 class="modal-title" id="partilot-confirm-title">Confirmar</h5>' +
            '        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>' +
            '      </div>' +
            '      <div class="modal-body" id="partilot-confirm-body"></div>' +
            '      <div class="modal-footer border-0">' +
            '        <button type="button" class="btn btn-light" data-bs-dismiss="modal" id="partilot-confirm-cancel" style="border-radius: 30px;">Cancelar</button>' +
            '        <button type="button" class="btn btn-dark" id="partilot-confirm-ok" style="border-radius: 30px;">Confirmar</button>' +
            '      </div>' +
            '    </div>' +
            '  </div>' +
            '</div>';

        document.body.insertAdjacentHTML('beforeend', html);
        return document.getElementById('partilot-confirm-modal');
    }

    /**
     * @param {Object} options
     * @param {string} options.title
     * @param {string} options.message  HTML permitido de forma limitada (texto + <strong>)
     * @param {string} [options.confirmText]
     * @param {string} [options.cancelText]
     * @returns {Promise<boolean>}
     */
    window.partilotConfirm = function (options) {
        options = options || {};
        var title = options.title || 'Confirmar';
        var message = options.message || '';
        var confirmText = options.confirmText || 'Confirmar';
        var cancelText = options.cancelText || 'Cancelar';

        return new Promise(function (resolve) {
            var modalEl = ensureConfirmModal();
            var titleEl = document.getElementById('partilot-confirm-title');
            var bodyEl = document.getElementById('partilot-confirm-body');
            var okBtn = document.getElementById('partilot-confirm-ok');
            var cancelBtn = document.getElementById('partilot-confirm-cancel');

            titleEl.textContent = title;
            bodyEl.innerHTML = message;
            okBtn.textContent = confirmText;
            cancelBtn.textContent = cancelText;

            var settled = false;
            function finish(result) {
                if (settled) return;
                settled = true;
                okBtn.removeEventListener('click', onOk);
                modalEl.removeEventListener('hidden.bs.modal', onHidden);
                resolve(result);
            }

            function onOk() {
                var instance = bootstrap.Modal.getInstance(modalEl);
                if (instance) instance.hide();
                finish(true);
            }

            function onHidden() {
                finish(false);
            }

            okBtn.addEventListener('click', onOk);
            modalEl.addEventListener('hidden.bs.modal', onHidden);

            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        });
    };

    /**
     * @param {'success'|'error'|'warning'|'info'} type
     * @param {string} text
     * @param {string} [title]
     */
    window.partilotNotify = function (type, text, title) {
        if (typeof PNotify !== 'undefined') {
            if (typeof PNotify.removeAll === 'function') {
                // no borrar otros avisos de la página
            }
            new PNotify({
                title: title || (type === 'error' ? 'Error' : type === 'success' ? 'Correcto' : 'Aviso'),
                text: text || '',
                type: type === 'error' ? 'error' : type === 'success' ? 'success' : type === 'warning' ? 'notice' : 'info',
                addclass: 'partilot-notify',
                delay: 6000
            });
            return;
        }
        // Fallback mínimo
        window.alert((title ? title + ': ' : '') + (text || ''));
    };

    /**
     * Opciones TomSelect con filtro «empieza por» (MEJ-002).
     */
    window.partilotTomSelectStartsWithOptions = function (extra) {
        var opts = {
            create: false,
            allowEmptyOption: true,
            score: function (search) {
                var q = String(search || '').toLowerCase();
                return function (item) {
                    var text = String((item && (item.text || item.value)) || '').toLowerCase();
                    if (!q) return 1;
                    return text.indexOf(q) === 0 ? 1 : 0;
                };
            }
        };
        if (extra && typeof extra === 'object') {
            Object.keys(extra).forEach(function (k) {
                opts[k] = extra[k];
            });
        }
        return opts;
    };

    /**
     * Intercepta formularios con data-partilot-confirm.
     */
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || !form.getAttribute) return;
        var msg = form.getAttribute('data-partilot-confirm');
        if (!msg) return;
        if (form.getAttribute('data-partilot-confirmed') === '1') {
            form.removeAttribute('data-partilot-confirmed');
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        window.partilotConfirm({
            title: form.getAttribute('data-partilot-confirm-title') || 'Confirmar',
            message: msg,
            confirmText: form.getAttribute('data-partilot-confirm-ok') || 'Confirmar'
        }).then(function (ok) {
            if (!ok) return;
            form.setAttribute('data-partilot-confirmed', '1');
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        });
    }, true);
})(window, document);
