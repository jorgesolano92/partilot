{{-- Modales para rango de participaciones y cantidad de traseras idénticas --}}
<div class="modal fade" id="designPdfParticipationModal" tabindex="-1" aria-labelledby="designPdfParticipationModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="designPdfParticipationModalLabel">Rango de participaciones</h5>
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
                <p class="small text-muted mt-2 mb-0"><span id="designPdfPartMaxHint"></span></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="designPdfPartConfirm">Generar PDF</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="designPdfBackModal" tabindex="-1" aria-labelledby="designPdfBackModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="designPdfBackModalLabel">Cantidad de traseras</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" data-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2">Las traseras son idénticas. Indique cuántas unidades necesita imprimir.</p>
                <label for="designPdfBackCount" class="form-label">Número de traseras</label>
                <input type="number" class="form-control" id="designPdfBackCount" min="1" max="100000" value="1">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="designPdfBackConfirm">Generar PDF</button>
            </div>
        </div>
    </div>
</div>

{{-- Overlay bloqueante eliminado: solo notificación PNotify (arriba derecha) mientras genera el PDF --}}

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

  function partilotTriggerDownload(url) {
    var iframe = document.createElement('iframe');
    iframe.setAttribute('style', 'display:none;width:0;height:0;border:0');
    iframe.setAttribute('src', url);
    document.body.appendChild(iframe);
    setTimeout(function () {
      iframe.remove();
    }, 180000);
  }

  function partilotNotifyPdf(type, title, message, sticky) {
    if (typeof PNotify === 'undefined') {
      if (message) window.alert(title + '\\n\\n' + message);
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
          partilotRemoveAllNotifies();
          partilotTriggerDownload(st.download_url);
          partilotNotifyPdf('success', notifyTitle || 'PDF', 'Descarga iniciada. Si no ve el archivo, compruebe descargas y el bloqueador.', false);
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

    function partilotStartDesignPdfAjax(url, title, $btn) {
    $btn.prop('disabled', true);
    partilotNotifyPdf('info', title, 'Generando PDF…', true);
    $.ajax({ url: url, method: 'GET', dataType: 'json', timeout: 300000 })
      .done(function (data) {
        if (data && data.status === 'completed' && data.download_url) {
          $btn.prop('disabled', false);
          partilotRemoveAllNotifies();
          partilotTriggerDownload(data.download_url);
          partilotNotifyPdf('success', title, 'Descarga iniciada. Si no ve el archivo, compruebe descargas y el bloqueador.', false);
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

    if (dialog === 'participation') {
      var $modal = $('#designPdfParticipationModal');
      $modal.data('pdf-wait-url', baseUrl).data('pdf-wait-title', title).data('pdf-wait-btn', $btn);
      $('#designPdfPartFrom').val(total > 0 ? 1 : 1);
      $('#designPdfPartTo').val(total > 0 ? total : 1);
      $('#designPdfPartMaxHint').text(total > 0 ? 'Participaciones en el set (referencia máxima): ' + total + '.' : 'No hay total de participaciones en el set; ajuste el rango manualmente.');
      $('#designPdfPartFrom').attr('max', total > 0 ? total : '');
      $('#designPdfPartTo').attr('max', total > 0 ? total : '');
      partilotModalShow($modal[0]);
      return;
    }

    if (dialog === 'backs') {
      var $bModal = $('#designPdfBackModal');
      $bModal.data('pdf-wait-url', baseUrl).data('pdf-wait-title', title).data('pdf-wait-btn', $btn);
      var defCount = total > 0 ? total : 1;
      $('#designPdfBackCount').val(defCount);
      partilotModalShow($bModal[0]);
      return;
    }

    partilotStartDesignPdfAjax(baseUrl, title, $btn);
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
    var sep = baseUrl.indexOf('?') >= 0 ? '&' : '?';
    var url = baseUrl + sep + 'pdf_from=' + encodeURIComponent(from) + '&pdf_to=' + encodeURIComponent(to);
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
    var sep = baseUrl.indexOf('?') >= 0 ? '&' : '?';
    var url = baseUrl + sep + 'count=' + encodeURIComponent(n);
    partilotModalHide($modal[0]);
    if ($btn && $btn.length) partilotStartDesignPdfAjax(url, title, $btn);
  });
})(window.jQuery);
</script>
