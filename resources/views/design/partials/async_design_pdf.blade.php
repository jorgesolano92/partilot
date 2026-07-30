{{-- Modales de impresión: estilo alineado con «Configurar salida» del editor --}}
<style>
.design-pdf-print-modal .form-control {
    border-radius: 30px;
}
.design-pdf-print-modal .form-check.form-switch {
    min-height: 1.5rem;
    padding-left: 0;
}
.design-pdf-print-modal .form-check.form-switch .form-check-input {
    float: left;
    margin-left: 0;
    width: 2.5em;
    height: 1.25em;
}
.design-pdf-print-modal .form-check.form-switch .form-check-label {
    float: left;
    margin-left: 50px;
    padding-top: 0.1rem;
}
.design-pdf-print-modal .form-check.form-switch::after {
    content: "";
    display: table;
    clear: both;
}
.design-pdf-print-modal .btn-print-cancel {
    border-radius: 30px;
    padding: 8px 18px;
    font-weight: 700;
    background-color: #333;
    color: #fff;
    border: none;
}
.design-pdf-print-modal .btn-print-cancel:hover,
.design-pdf-print-modal .btn-print-cancel:focus {
    background-color: #222;
    color: #fff;
}
.design-pdf-print-modal .btn-print-confirm {
    border-radius: 30px;
    padding: 8px 18px;
    font-weight: 700;
    background-color: #e78307;
    color: #333;
    border: none;
}
.design-pdf-print-modal .btn-print-confirm:hover,
.design-pdf-print-modal .btn-print-confirm:focus {
    background-color: #d07406;
    color: #333;
}
</style>

<div class="modal fade design-pdf-print-modal" id="designPdfParticipationModal" tabindex="-1" aria-labelledby="designPdfParticipationModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="designPdfParticipationModalLabel">Imprimir participaciones</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" data-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2">Indique el número de participación <strong>desde</strong> y <strong>hasta</strong> (orden del taco), por ejemplo para reponer participaciones perdidas o dañadas.</p>
                <div class="row g-2">
                    <div class="col-6">
                        <label for="designPdfPartFrom" class="form-label">Desde</label>
                        <input type="number" class="form-control" id="designPdfPartFrom" min="1" value="1">
                    </div>
                    <div class="col-6">
                        <label for="designPdfPartTo" class="form-label">Hasta</label>
                        <input type="number" class="form-control" id="designPdfPartTo" min="1" value="1">
                    </div>
                </div>
                <p class="small text-muted mt-2 mb-3"><span id="designPdfPartMaxHint"></span></p>

                <h6 class="mb-2">Empaquetado de documentos</h6>
                <p class="small text-muted mb-2">Esto solo afecta a cómo se genera el archivo (un PDF o un ZIP). No modifica el diseño ni requiere aprobación.</p>
                <div class="form-group mb-2">
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input bg-dark" type="radio" name="designPdfPartDocsMode" id="designPdfPartDocsMode1" value="1" role="switch" checked>
                        <label class="form-check-label" for="designPdfPartDocsMode1"><b>Un único documento (PDF)</b></label>
                    </div>
                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input bg-dark" type="radio" name="designPdfPartDocsMode" id="designPdfPartDocsMode2" value="2" role="switch">
                        <label class="form-check-label" for="designPdfPartDocsMode2"><b>Varios documentos (ZIP)</b></label>
                    </div>
                </div>
                <div id="designPdfPartPagesWrap" class="mb-1" style="display:none;">
                    <label for="designPdfPartPagesPerDoc" class="form-label">Páginas por documento</label>
                    <input type="number" class="form-control" id="designPdfPartPagesPerDoc" min="1" value="150" style="max-width: 140px;">
                    <p class="small text-muted mt-1 mb-0" id="designPdfPartDocsHint"></p>
                </div>

                <h6 class="mt-3 mb-2">Nombre del archivo</h6>
                <label for="designPdfPartDownloadName" class="form-label">Se guardará como</label>
                <input type="text" class="form-control" id="designPdfPartDownloadName" maxlength="160" autocomplete="off">
                <p class="small text-muted mt-1 mb-0">Puede editarlo. Se añadirá automáticamente <code>.pdf</code> o <code>.zip</code>.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-print-cancel" data-bs-dismiss="modal" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-print-confirm" id="designPdfPartConfirm">Generar PDF</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade design-pdf-print-modal" id="designPdfCoverModal" tabindex="-1" aria-labelledby="designPdfCoverModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="designPdfCoverModalLabel">Imprimir portadas</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" data-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">Se generarán las portadas de todos los tacos del diseño.</p>
                <h6 class="mb-2">Empaquetado de documentos</h6>
                <p class="small text-muted mb-2">Esto solo afecta a cómo se genera el archivo (un PDF o un ZIP). No modifica el diseño ni requiere aprobación.</p>
                <div class="form-group mb-2">
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input bg-dark" type="radio" name="designPdfCoverDocsMode" id="designPdfCoverDocsMode1" value="1" role="switch" checked>
                        <label class="form-check-label" for="designPdfCoverDocsMode1"><b>Un único documento (PDF)</b></label>
                    </div>
                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input bg-dark" type="radio" name="designPdfCoverDocsMode" id="designPdfCoverDocsMode2" value="2" role="switch">
                        <label class="form-check-label" for="designPdfCoverDocsMode2"><b>Varios documentos (ZIP)</b></label>
                    </div>
                </div>
                <div id="designPdfCoverPagesWrap" class="mb-1" style="display:none;">
                    <label for="designPdfCoverPagesPerDoc" class="form-label">Páginas por documento</label>
                    <input type="number" class="form-control" id="designPdfCoverPagesPerDoc" min="1" value="150" style="max-width: 140px;">
                    <p class="small text-muted mt-1 mb-0" id="designPdfCoverDocsHint"></p>
                </div>

                <h6 class="mt-3 mb-2">Nombre del archivo</h6>
                <label for="designPdfCoverDownloadName" class="form-label">Se guardará como</label>
                <input type="text" class="form-control" id="designPdfCoverDownloadName" maxlength="160" autocomplete="off">
                <p class="small text-muted mt-1 mb-0">Puede editarlo. Se añadirá automáticamente <code>.pdf</code> o <code>.zip</code>.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-print-cancel" data-bs-dismiss="modal" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-print-confirm" id="designPdfCoverConfirm">Generar PDF</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade design-pdf-print-modal" id="designPdfBackModal" tabindex="-1" aria-labelledby="designPdfBackModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="designPdfBackModalLabel">Imprimir traseras</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" data-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2">Las traseras son idénticas. Indique cuántas unidades necesita imprimir.</p>
                <label for="designPdfBackCount" class="form-label">Número de traseras</label>
                <input type="number" class="form-control" id="designPdfBackCount" min="1" max="100000" value="1" style="max-width: 160px;">

                <h6 class="mt-3 mb-2">Empaquetado de documentos</h6>
                <p class="small text-muted mb-2">Esto solo afecta a cómo se genera el archivo (un PDF o un ZIP). No modifica el diseño ni requiere aprobación.</p>
                <div class="form-group mb-2">
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input bg-dark" type="radio" name="designPdfBackDocsMode" id="designPdfBackDocsMode1" value="1" role="switch" checked>
                        <label class="form-check-label" for="designPdfBackDocsMode1"><b>Un único documento (PDF)</b></label>
                    </div>
                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input bg-dark" type="radio" name="designPdfBackDocsMode" id="designPdfBackDocsMode2" value="2" role="switch">
                        <label class="form-check-label" for="designPdfBackDocsMode2"><b>Varios documentos (ZIP)</b></label>
                    </div>
                </div>
                <div id="designPdfBackPagesWrap" class="mb-1" style="display:none;">
                    <label for="designPdfBackPagesPerDoc" class="form-label">Páginas por documento</label>
                    <input type="number" class="form-control" id="designPdfBackPagesPerDoc" min="1" value="150" style="max-width: 140px;">
                    <p class="small text-muted mt-1 mb-0" id="designPdfBackDocsHint"></p>
                </div>

                <h6 class="mt-3 mb-2">Nombre del archivo</h6>
                <label for="designPdfBackDownloadName" class="form-label">Se guardará como</label>
                <input type="text" class="form-control" id="designPdfBackDownloadName" maxlength="160" autocomplete="off">
                <p class="small text-muted mt-1 mb-0">Puede editarlo. Se añadirá automáticamente <code>.pdf</code> o <code>.zip</code>.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-print-cancel" data-bs-dismiss="modal" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-print-confirm" id="designPdfBackConfirm">Generar PDF</button>
            </div>
        </div>
    </div>
</div>

{{-- Overlay bloqueante eliminado: solo aviso PNotify (arriba derecha) mientras genera el PDF --}}

<script>
(function ($) {
  function partilotRemoveAllNotifies() {
    if (typeof PNotify !== 'undefined' && typeof PNotify.removeAll === 'function') {
      PNotify.removeAll();
    }
    document.querySelectorAll('.ui-pnotify').forEach(function (el) {
      el.remove();
    });
  }

  function partilotFilenameFromUrl(url, fallback) {
    try {
      var path = (url || '').split('?')[0];
      var name = path.split('/').pop() || '';
      if (name) return decodeURIComponent(name);
    } catch (e) {}
    return fallback || 'archivo.pdf';
  }

  function partilotFilenameFromDisposition(header, fallback) {
    if (!header) return fallback;
    var m = /filename\*=UTF-8''([^;]+)|filename="?([^";]+)"?/i.exec(header);
    if (!m) return fallback;
    try {
      return decodeURIComponent((m[1] || m[2] || '').trim()) || fallback;
    } catch (e) {
      return (m[1] || m[2] || fallback).trim() || fallback;
    }
  }

  /** Descarga en la misma pestaña (blob), sin abrir ventanas. */
  function partilotTriggerDownload(url, preferredName) {
    if (!url) return Promise.resolve(false);
    var fallbackName = preferredName || partilotFilenameFromUrl(url, 'archivo.pdf');

    if (window.fetch) {
      return fetch(url, { credentials: 'same-origin', redirect: 'follow' })
        .then(function (res) {
          if (!res.ok) {
            throw new Error('HTTP ' + res.status);
          }
          var name = partilotFilenameFromDisposition(res.headers.get('Content-Disposition'), fallbackName);
          return res.blob().then(function (blob) {
            return { blob: blob, name: name };
          });
        })
        .then(function (payload) {
          var objectUrl = URL.createObjectURL(payload.blob);
          var a = document.createElement('a');
          a.href = objectUrl;
          a.download = payload.name || fallbackName;
          a.style.display = 'none';
          document.body.appendChild(a);
          a.click();
          setTimeout(function () {
            a.remove();
            URL.revokeObjectURL(objectUrl);
          }, 2000);
          return true;
        })
        .catch(function () {
          // Fallback silencioso: iframe oculto (no abre pestaña).
          return partilotTriggerDownloadIframe(url);
        });
    }

    return Promise.resolve(partilotTriggerDownloadIframe(url));
  }

  function partilotTriggerDownloadIframe(url) {
    try {
      var iframe = document.createElement('iframe');
      iframe.setAttribute('style', 'display:none;width:0;height:0;border:0');
      iframe.setAttribute('src', url);
      document.body.appendChild(iframe);
      setTimeout(function () {
        iframe.remove();
      }, 180000);
      return true;
    } catch (e) {
      return false;
    }
  }

  function partilotFinishDownload(url, title) {
    if (!url) return;
    partilotNotifyPdf('info', title || 'PDF', 'Preparando descarga…', true);
    partilotTriggerDownload(url).then(function (ok) {
      if (ok) {
        partilotNotifyPdf(
          'success',
          title || 'PDF',
          'Descarga iniciada. Si no ve el archivo, compruebe la carpeta de descargas o <a href="' + url + '" target="_blank" rel="noopener">pulse aquí</a>.',
          false
        );
      } else {
        partilotNotifyPdf(
          'warning',
          title || 'PDF',
          'No se pudo iniciar la descarga automática. <a href="' + url + '" target="_blank" rel="noopener">Pulse aquí para descargar</a>.',
          true
        );
      }
    });
  }

  function partilotNotifyPdf(type, title, message, sticky) {
    if (typeof PNotify === 'undefined') {
      if (message) window.alert(title + '\\n\\n' + message.replace(/<[^>]+>/g, ''));
      return;
    }
    partilotRemoveAllNotifies();
    var opts = {
      type: type,
      addclass: 'partilot-notify',
      width: '460px',
      title: title,
      text: message,
      icon: false,
      opacity: 1,
      nonblock: false,
      styling: 'bootstrap3',
      buttons: { closer: true, sticker: false, closer_hover: false }
    };
    if (sticky) {
      opts.hide = false;
    } else {
      opts.hide = true;
      opts.delay = 6000;
    }
    new PNotify(opts);
  }

  function partilotPollPdfStatus(checkUrl, notifyTitle, attemptsLeft, restoreBtn, $restoreEl) {
    if (attemptsLeft <= 0) {
      if (restoreBtn && $restoreEl && $restoreEl.length) $restoreEl.prop('disabled', false);
      partilotNotifyPdf('error', notifyTitle || 'PDF', 'El tiempo de espera terminó. Si el PDF era grande, vuelva a intentarlo; si el problema continúa, revise el log del servidor.');
      return;
    }
    $.getJSON(checkUrl)
      .done(function (st) {
        if (st && st.status === 'failed') {
          if (restoreBtn && $restoreEl && $restoreEl.length) $restoreEl.prop('disabled', false);
          partilotNotifyPdf('error', notifyTitle || 'PDF', st.message || 'La generación del PDF falló.', false);
          return;
        }
        if (st && st.status === 'completed' && st.download_url) {
          if (restoreBtn && $restoreEl && $restoreEl.length) $restoreEl.prop('disabled', false);
          partilotFinishDownload(st.download_url, notifyTitle);
          return;
        }
        setTimeout(function () {
          partilotPollPdfStatus(checkUrl, notifyTitle, attemptsLeft - 1, restoreBtn, $restoreEl);
        }, 2000);
      })
      .fail(function () {
        if (restoreBtn && $restoreEl && $restoreEl.length) $restoreEl.prop('disabled', false);
        partilotNotifyPdf('error', notifyTitle || 'PDF', 'No se pudo consultar el estado del PDF.');
      });
  }

  function partilotModalShow(modalEl) {
    if (window.bootstrap && window.bootstrap.Modal) {
      window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
      return;
    }
    if (modalEl && jQuery.fn.modal) {
      jQuery(modalEl).modal('show');
    }
  }

  function partilotModalHide(modalEl) {
    if (window.bootstrap && window.bootstrap.Modal) {
      var inst = window.bootstrap.Modal.getInstance(modalEl);
      if (inst) inst.hide();
      return;
    }
    if (modalEl && jQuery.fn.modal) {
      jQuery(modalEl).modal('hide');
    }
  }

  function partilotParsePositiveInt(val, fallback) {
    var n = parseInt(val, 10);
    if (isNaN(n) || n < 1) return fallback;
    return n;
  }

  function partilotBtnGridMeta($btn) {
    return {
      rows: partilotParsePositiveInt($btn.data('rows'), 1),
      cols: partilotParsePositiveInt($btn.data('cols'), 1),
      docsMode: String($btn.data('documents-mode') || '1') === '2' ? '2' : '1',
      pagesPerDoc: partilotParsePositiveInt($btn.data('pages-per-document'), 150)
    };
  }

  function partilotFillDocsFields(prefix, meta, itemCount) {
    var mode = meta.docsMode;
    $('input[name="' + prefix + 'DocsMode"][value="' + mode + '"]').prop('checked', true);
    $('#' + prefix + 'PagesPerDoc').val(meta.pagesPerDoc);
    $('#' + prefix + 'PagesWrap').toggle(mode === '2');
    partilotUpdateDocsHint(prefix, meta.rows, meta.cols, itemCount);
  }

  function partilotUpdateDocsHint(prefix, rows, cols, itemCount) {
    var mode = $('input[name="' + prefix + 'DocsMode"]:checked').val() || '1';
    var pages = partilotParsePositiveInt($('#' + prefix + 'PagesPerDoc').val(), 150);
    var perPage = Math.max(1, rows * cols);
    var itemsPerDoc = pages * perPage;
    var $hint = $('#' + prefix + 'DocsHint');
    if (mode !== '2') {
      $hint.text('');
      return;
    }
    var msg = perPage + ' unidades por página; ' + itemsPerDoc + ' por documento.';
    if (itemCount && itemCount > 0) {
      var docs = Math.max(1, Math.ceil(itemCount / itemsPerDoc));
      msg += ' Con el volumen actual (~' + itemCount + ') saldrían ' + docs + ' PDF en el ZIP.';
    }
    $hint.text(msg);
  }

  function partilotReadDocsQuery(prefix) {
    var mode = $('input[name="' + prefix + 'DocsMode"]:checked').val() || '1';
    if (mode !== '2') mode = '1';
    var pages = partilotParsePositiveInt($('#' + prefix + 'PagesPerDoc').val(), 150);
    var q = 'documents_mode=' + encodeURIComponent(mode) + '&pages_per_document=' + encodeURIComponent(pages);
    var downloadName = $.trim($('#' + prefix + 'DownloadName').val() || '');
    if (downloadName) {
      q += '&download_name=' + encodeURIComponent(downloadName);
    }
    return q;
  }

  function partilotDefaultDownloadBase(designName, suffix) {
    var base = $.trim(designName || '');
    if (!base) base = 'Diseño';
    // Barras de fechas (15/07/2026) no son válidas en nombres de archivo Windows.
    base = base.replace(/[\/\\:*?"<>|]/g, '-');
    base = base.replace(/\s+/g, ' ').replace(/^[\s.-]+|[\s.-]+$/g, '');
    return base + ' - ' + suffix;
  }

  function partilotFillDownloadName(inputId, designName, suffix) {
    $('#' + inputId).val(partilotDefaultDownloadBase(designName, suffix));
  }

  function partilotSyncBtnDocsDefaults($btn, prefix) {
    if (!$btn || !$btn.length) return;
    var mode = $('input[name="' + prefix + 'DocsMode"]:checked').val() || '1';
    if (mode !== '2') mode = '1';
    var pages = partilotParsePositiveInt($('#' + prefix + 'PagesPerDoc').val(), 150);
    $btn.attr('data-documents-mode', mode).data('documents-mode', mode);
    $btn.attr('data-pages-per-document', pages).data('pages-per-document', pages);
  }

  function partilotAppendQuery(baseUrl, query) {
    var sep = baseUrl.indexOf('?') >= 0 ? '&' : '?';
    return baseUrl + sep + query;
  }

  function partilotStartDesignPdfAjax(url, title, $btn) {
    $btn.prop('disabled', true);
    partilotNotifyPdf('info', title, 'Generando PDF…', true);
    $.ajax({ url: url, method: 'GET', dataType: 'json', timeout: 300000 })
      .done(function (data) {
        if (data && data.status === 'completed' && data.download_url) {
          $btn.prop('disabled', false);
          partilotFinishDownload(data.download_url, title);
          return;
        }
        if (data && data.status === 'failed') {
          $btn.prop('disabled', false);
          partilotNotifyPdf('error', title, data.message || 'La generación del PDF falló.', false);
          return;
        }
        if (data && data.status === 'processing' && data.check_url) {
          partilotPollPdfStatus(data.check_url, title, 1800, true, $btn);
          return;
        }
        $btn.prop('disabled', false);
        partilotNotifyPdf('error', title, data && data.message ? data.message : 'Respuesta inesperada al iniciar la generación.', false);
      })
      .fail(function (xhr) {
        $btn.prop('disabled', false);
        var msg = 'No se pudo generar el PDF.';
        try {
          var j = xhr.responseJSON;
          if (j && j.message) msg = j.message;
        } catch (err) {}
        partilotNotifyPdf('error', title, msg, false);
      });
  }

  $(document).on('change', 'input[name="designPdfPartDocsMode"]', function () {
    var show = $(this).val() === '2';
    $('#designPdfPartPagesWrap').toggle(show);
    var $modal = $('#designPdfParticipationModal');
    var meta = $modal.data('pdf-grid-meta') || { rows: 1, cols: 1 };
    var from = partilotParsePositiveInt($('#designPdfPartFrom').val(), 1);
    var to = partilotParsePositiveInt($('#designPdfPartTo').val(), from);
    partilotUpdateDocsHint('designPdfPart', meta.rows, meta.cols, Math.max(0, to - from + 1));
  });
  $(document).on('input change', '#designPdfPartFrom, #designPdfPartTo, #designPdfPartPagesPerDoc', function () {
    var $modal = $('#designPdfParticipationModal');
    var meta = $modal.data('pdf-grid-meta') || { rows: 1, cols: 1 };
    var from = partilotParsePositiveInt($('#designPdfPartFrom').val(), 1);
    var to = partilotParsePositiveInt($('#designPdfPartTo').val(), from);
    partilotUpdateDocsHint('designPdfPart', meta.rows, meta.cols, Math.max(0, to - from + 1));
  });

  $(document).on('change', 'input[name="designPdfCoverDocsMode"]', function () {
    $('#designPdfCoverPagesWrap').toggle($(this).val() === '2');
    var $modal = $('#designPdfCoverModal');
    var meta = $modal.data('pdf-grid-meta') || { rows: 1, cols: 1 };
    partilotUpdateDocsHint('designPdfCover', meta.rows, meta.cols, $modal.data('pdf-item-count') || 0);
  });
  $(document).on('input change', '#designPdfCoverPagesPerDoc', function () {
    var $modal = $('#designPdfCoverModal');
    var meta = $modal.data('pdf-grid-meta') || { rows: 1, cols: 1 };
    partilotUpdateDocsHint('designPdfCover', meta.rows, meta.cols, $modal.data('pdf-item-count') || 0);
  });

  $(document).on('change', 'input[name="designPdfBackDocsMode"]', function () {
    $('#designPdfBackPagesWrap').toggle($(this).val() === '2');
    var $modal = $('#designPdfBackModal');
    var meta = $modal.data('pdf-grid-meta') || { rows: 1, cols: 1 };
    var n = partilotParsePositiveInt($('#designPdfBackCount').val(), 1);
    partilotUpdateDocsHint('designPdfBack', meta.rows, meta.cols, n);
  });
  $(document).on('input change', '#designPdfBackCount, #designPdfBackPagesPerDoc', function () {
    var $modal = $('#designPdfBackModal');
    var meta = $modal.data('pdf-grid-meta') || { rows: 1, cols: 1 };
    var n = partilotParsePositiveInt($('#designPdfBackCount').val(), 1);
    partilotUpdateDocsHint('designPdfBack', meta.rows, meta.cols, n);
  });

  $(document).on('click', '.js-design-pdf-async', function (e) {
    e.preventDefault();
    e.stopPropagation();
    var $btn = $(this);
    var baseUrl = $btn.data('async-url');
    var title = $btn.data('title') || 'PDF';
    if (!baseUrl) return;

    var dialog = ($btn.data('pdf-dialog') || '').toString();
    var total = parseInt($btn.data('total-participations'), 10);
    if (isNaN(total) || total < 0) total = 0;
    var meta = partilotBtnGridMeta($btn);

    if (dialog === 'participation') {
      var $modal = $('#designPdfParticipationModal');
      $modal.data('pdf-wait-url', baseUrl).data('pdf-wait-title', title).data('pdf-wait-btn', $btn).data('pdf-grid-meta', meta);
      $('#designPdfPartFrom').val(1);
      $('#designPdfPartTo').val(total > 0 ? total : 1);
      $('#designPdfPartMaxHint').text(total > 0 ? 'Participaciones en el set (referencia máxima): ' + total + '.' : 'No hay total de participaciones en el set; ajuste el rango manualmente.');
      $('#designPdfPartFrom').attr('max', total > 0 ? total : '');
      $('#designPdfPartTo').attr('max', total > 0 ? total : '');
      partilotFillDocsFields('designPdfPart', meta, total > 0 ? total : 0);
      partilotFillDownloadName('designPdfPartDownloadName', $btn.attr('data-design-name'), 'participaciones');
      partilotModalShow($modal[0]);
      return;
    }

    if (dialog === 'covers') {
      var $cModal = $('#designPdfCoverModal');
      var coverCount = partilotParsePositiveInt($btn.data('cover-count'), total > 0 ? total : 0);
      $cModal.data('pdf-wait-url', baseUrl).data('pdf-wait-title', title).data('pdf-wait-btn', $btn)
        .data('pdf-grid-meta', meta).data('pdf-item-count', coverCount);
      partilotFillDocsFields('designPdfCover', meta, coverCount);
      partilotFillDownloadName('designPdfCoverDownloadName', $btn.attr('data-design-name'), 'portadas');
      partilotModalShow($cModal[0]);
      return;
    }

    if (dialog === 'backs') {
      var $bModal = $('#designPdfBackModal');
      $bModal.data('pdf-wait-url', baseUrl).data('pdf-wait-title', title).data('pdf-wait-btn', $btn).data('pdf-grid-meta', meta);
      var defCount = total > 0 ? total : 1;
      $('#designPdfBackCount').val(defCount);
      partilotFillDocsFields('designPdfBack', meta, defCount);
      partilotFillDownloadName('designPdfBackDownloadName', $btn.attr('data-design-name'), 'traseras');
      partilotModalShow($bModal[0]);
      return;
    }

    partilotStartDesignPdfAjax(
      partilotAppendQuery(baseUrl, 'download_name=' + encodeURIComponent(partilotDefaultDownloadBase($btn.attr('data-design-name'), 'pdf'))),
      title,
      $btn
    );
  });

  $('#designPdfPartConfirm').on('click', function () {
    var $modal = $('#designPdfParticipationModal');
    var baseUrl = $modal.data('pdf-wait-url');
    var title = $modal.data('pdf-wait-title') || 'PDF';
    var $btn = $modal.data('pdf-wait-btn');
    var from = parseInt($('#designPdfPartFrom').val(), 10);
    var to = parseInt($('#designPdfPartTo').val(), 10);
    if (!from || !to || from < 1 || to < 1) {
      partilotNotifyPdf('error', title, 'Indique valores válidos en «desde» y «hasta».', false);
      return;
    }
    if (from > to) {
      partilotNotifyPdf('error', title, '«Desde» no puede ser mayor que «hasta».', false);
      return;
    }
    if (!$.trim($('#designPdfPartDownloadName').val() || '')) {
      partilotNotifyPdf('error', title, 'Indique un nombre para el archivo.', false);
      return;
    }
    var url = partilotAppendQuery(baseUrl, 'pdf_from=' + encodeURIComponent(from) + '&pdf_to=' + encodeURIComponent(to) + '&' + partilotReadDocsQuery('designPdfPart'));
    partilotSyncBtnDocsDefaults($btn, 'designPdfPart');
    partilotModalHide($modal[0]);
    if ($btn && $btn.length) partilotStartDesignPdfAjax(url, title, $btn);
  });

  $('#designPdfCoverConfirm').on('click', function () {
    var $modal = $('#designPdfCoverModal');
    var baseUrl = $modal.data('pdf-wait-url');
    var title = $modal.data('pdf-wait-title') || 'PDF';
    var $btn = $modal.data('pdf-wait-btn');
    if (!$.trim($('#designPdfCoverDownloadName').val() || '')) {
      partilotNotifyPdf('error', title, 'Indique un nombre para el archivo.', false);
      return;
    }
    var url = partilotAppendQuery(baseUrl, partilotReadDocsQuery('designPdfCover'));
    partilotSyncBtnDocsDefaults($btn, 'designPdfCover');
    partilotModalHide($modal[0]);
    if ($btn && $btn.length) partilotStartDesignPdfAjax(url, title, $btn);
  });

  $('#designPdfBackConfirm').on('click', function () {
    var $modal = $('#designPdfBackModal');
    var baseUrl = $modal.data('pdf-wait-url');
    var title = $modal.data('pdf-wait-title') || 'PDF';
    var $btn = $modal.data('pdf-wait-btn');
    var n = parseInt($('#designPdfBackCount').val(), 10);
    if (!n || n < 1 || n > 100000) {
      partilotNotifyPdf('error', title, 'Indique un número de traseras entre 1 y 100000.', false);
      return;
    }
    if (!$.trim($('#designPdfBackDownloadName').val() || '')) {
      partilotNotifyPdf('error', title, 'Indique un nombre para el archivo.', false);
      return;
    }
    var url = partilotAppendQuery(baseUrl, 'count=' + encodeURIComponent(n) + '&' + partilotReadDocsQuery('designPdfBack'));
    partilotSyncBtnDocsDefaults($btn, 'designPdfBack');
    partilotModalHide($modal[0]);
    if ($btn && $btn.length) partilotStartDesignPdfAjax(url, title, $btn);
  });
})(window.jQuery);
</script>
