{{-- Botón + JS: previsualizar 1 participación / portada / trasera del paso actual --}}
@php
    $previewPdfUrl = $preview_pdf_url
        ?? ((isset($printShopOrder) && $printShopOrder)
            ? route('design.previewStepPdf')
            : (request()->routeIs('design.external.*')
                ? route('design.external.previewStepPdf')
                : route('design.previewStepPdf')));
@endphp

<script>
(function ($) {
  window.syncDesignPreviewPdfButton = function () {
    var $btn = $('#design-preview-pdf-btn');
    if (!$btn.length) return;
    var s = (typeof step !== 'undefined') ? step : 1;
    var show = (s === 2 || s === 3 || (s === 4 && !window.__backSkipped));
    $btn.toggleClass('d-none', !show);
  };

  function designPreviewTypeForStep() {
    if (typeof step === 'undefined') return null;
    if (step === 2) return 'participation';
    if (step === 3) return 'cover';
    if (step === 4) return 'back';
    return null;
  }

  function designPreviewHtmlForStep() {
    if (typeof getFormatBoxHtmlForSave !== 'function') return '';
    if (step === 2) return getFormatBoxHtmlForSave('#step-2 .format-box');
    if (step === 3) return getFormatBoxHtmlForSave('#step-3 .format-box');
    if (step === 4) {
      var sel = $('#design-back-bg').length ? '#step-4 .format-box' : '#step-4 .format-box';
      return getFormatBoxHtmlForSave(sel);
    }
    return '';
  }

  $(document).on('click', '#design-preview-pdf-btn', function (e) {
    e.preventDefault();
    var type = designPreviewTypeForStep();
    if (!type) {
      alert('La previsualización solo está disponible en participación, portada o trasera.');
      return;
    }
    var html = designPreviewHtmlForStep();
    if (!html || html.length < 20) {
      alert('No hay contenido en el lienzo para previsualizar.');
      return;
    }

    var $btn = $(this);
    $btn.prop('disabled', true);
    var fd = new FormData();
    fd.append('type', type);
    fd.append('html', html);
    fd.append('_token', $('meta[name="csrf-token"]').attr('content') || '{{ csrf_token() }}');
    var designId = window.__designId || @json(
        isset($format) ? (int) $format->id : (
            isset($designFormatId) ? (int) $designFormatId : (
                (isset($design) && $design) ? (int) $design->id : null
            )
        )
    );
    if (designId) fd.append('design_id', designId);
    if ($('#page').length) fd.append('page', $('#page').val());
    if ($('#orientation').length) fd.append('orientation', $('#orientation').val());
    if ($('#rows').length) fd.append('rows', $('#rows').val());
    if ($('#cols').length) fd.append('cols', $('#cols').val());
    if ($('#identation').length) fd.append('identation', $('#identation').val());

    fetch(@json($previewPdfUrl), {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/pdf'
      }
    }).then(function (res) {
      if (!res.ok) {
        return res.text().then(function (t) {
          throw new Error(t || ('Error ' + res.status));
        });
      }
      return res.blob();
    }).then(function (blob) {
      var url = URL.createObjectURL(blob);
      var w = window.open(url, 'designPdfPreview', 'noopener,noreferrer,width=1100,height=800');
      if (!w) {
        // Popup bloqueado: forzar descarga/abrir en pestaña
        var a = document.createElement('a');
        a.href = url;
        a.target = '_blank';
        a.rel = 'noopener';
        document.body.appendChild(a);
        a.click();
        a.remove();
      }
      setTimeout(function () { URL.revokeObjectURL(url); }, 120000);
    }).catch(function (err) {
      console.error(err);
      alert('No se pudo generar la previsualización. Guarde el diseño e intente de nuevo.');
    }).finally(function () {
      $btn.prop('disabled', false);
    });
  });

  $(document).on('click', '.next-step, .prev-step', function () {
    setTimeout(function () {
      if (typeof window.syncDesignPreviewPdfButton === 'function') {
        window.syncDesignPreviewPdfButton();
      }
    }, 50);
  });

  $(function () {
    if (typeof window.syncDesignPreviewPdfButton === 'function') {
      window.syncDesignPreviewPdfButton();
    }
  });
})(window.jQuery);
</script>
