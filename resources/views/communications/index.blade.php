@extends('layouts.layout')

@section('title','Comunicaciones')

@section('content')

<!-- Start Content-->
<div class="container-fluid">
    
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item active">Comunicaciones</li>
                    </ol>
                </div>
                <h4 class="page-title">Comunicaciones (Emails)</h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

                    <div class="{{ count($logs) ? '' : 'd-none' }}">
                        <h4 class="header-title">

                            <div class="float-start d-flex align-items-start">
                                <input type="text" class="form-control" style="margin-right: 8px;" placeholder="Tipo">
                                <input type="text" class="form-control" style="margin-right: 8px;" placeholder="Email">
                                <input type="text" class="form-control" placeholder="Status">
                            </div>

                        </h4>

                        <div style="clear: both;"></div>

                        <br>

                        <table id="example2" class="table table-striped nowrap w-100">
                            <thead class="filters">
                                <tr>
                                    <th>Id</th>
                                    <th>Tipo</th>
                                    <th>Enviado por</th>
                                    <th>Email</th>
                                    <th>Rol</th>
                                    <th>Status</th>
                                    <th>Fecha</th>
                                    <th class="no-filter"></th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach($logs as $log)
                                    @php
                                        $effectiveDate = $log->displayEffectiveDate();
                                        $dateText = $effectiveDate ? $effectiveDate->format('d/m/Y H:i') : 'N/A';
                                    @endphp
                                    <tr id="email-log-{{ $log->id }}">
                                        <td><a href="#" class="view-email-log" data-log-id="{{ $log->id }}">#NO{{ str_pad($log->id, 5, '0', STR_PAD_LEFT) }}</a></td>
                                        <td>
                                            <span @if($log->messageTypeKey()) title="Key: {{ $log->messageTypeKey() }}" @endif>{{ $log->displayMessageType() }}</span>
                                            @if($log->messageTypeKey())
                                                <span class="visually-hidden">{{ $log->messageTypeKey() }}</span>
                                            @endif
                                        </td>
                                        <td>{{ $log->sender_type }}</td>
                                        <td>
                                            @if($log->mail_class)
                                                <a href="#" class="view-email-log text-dark text-decoration-underline" data-log-id="{{ $log->id }}">{{ $log->recipient_email }}</a>
                                            @else
                                                {{ $log->recipient_email }}
                                            @endif
                                        </td>
                                        <td>{{ $log->recipient_role ?? '-' }}</td>
                                        <td><span class="badge {{ $log->displayStatusBadgeClass() }}">{{ $log->displayStatus() }}</span></td>
                                        <td>{{ $dateText }}</td>
                                        <td class="no-click" style="cursor: default; white-space: nowrap;">
                                            @if($log->mail_class)
                                                <button type="button" class="btn btn-sm btn-light view-email-log" data-log-id="{{ $log->id }}" title="Ver contenido">
                                                    <img src="{{ url('assets/form-groups/eye.svg') }}" alt="" width="12">
                                                </button>
                                            @endif
                                            <form method="POST" action="{{ route('communications.resend', $log->id) }}" class="d-inline resend-email-form" data-recipient="{{ $log->recipient_email }}" data-type="{{ $log->displayMessageType() }}">
                                                @csrf
                                                <button type="button" class="btn btn-sm btn-dark resend-email-log" {{ empty($log->mail_class) ? 'disabled' : '' }} title="Reenviar email">
                                                    <i class="ri-refresh-line"></i>
                                                </button>
                                            </form>
                                            <button type="button" class="btn btn-sm btn-danger ms-1" onclick="deleteEmailLog({{ $log->id }})">
                                                <i class="ri-delete-bin-6-line"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                    </div>

                    <div class="{{ count($logs) ? 'd-none' : '' }}">

                        <div class="d-flex align-items-center gap-1">

                            <div class="empty-tables">

                                <div>
                                    <img src="{{ url('icons_/comunicados.svg') }}" alt="" width="80px">
                                </div>

                                <h3 class="mb-0">No hay emails registrados</h3>

                                <small>A medida que se envíen comunicaciones, aparecerán aquí.</small>

                            </div>

                        </div>
                    </div>
                    
                </div> <!-- end card body-->
            </div> <!-- end card -->
        </div><!-- end col-->
    </div>
    <!-- end row-->

</div> <!-- container -->

<div class="modal fade" id="emailPreviewModal" tabindex="-1" aria-labelledby="emailPreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="emailPreviewModalLabel">Contenido del email</h5>
                    <small class="text-muted" id="emailPreviewMeta"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-0">
                <div id="emailPreviewLoading" class="p-4 text-center text-muted">Cargando contenido...</div>
                <div id="emailPreviewError" class="p-4 text-danger d-none"></div>
                <iframe id="emailPreviewFrame" title="Vista previa del email" class="d-none" style="width:100%; min-height:520px; border:0; background:#fff;"></iframe>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="emailResendModal" tabindex="-1" aria-labelledby="emailResendModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="emailResendModalLabel">Confirmar reenvío</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">¿Deseas reenviar este email?</p>
                <p class="mb-1"><strong>Tipo:</strong> <span id="emailResendType"></span></p>
                <p class="mb-0"><strong>Destinatario:</strong> <span id="emailResendRecipient"></span></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-dark" id="emailResendConfirmBtn">Reenviar</button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')

<script>

  function initDatatable() 
  {
    $("#example2").DataTable({

      "ordering": false,
      "sorting": false,

      "scrollX": true, "scrollCollapse": true,
        orderCellsTop: true,
        fixedHeader: true,
        initComplete: function () {
            var api = this.api();
 
            api
                .columns()
                .eq(0)
                .each(function (colIdx) {
                    var cell = $('.filters th').eq(
                        $(api.column(colIdx).header()).index()
                    );
                    var title = $(cell).text();
                    if ($(cell).hasClass('no-filter')) {
                      $(cell).addClass('sorting_disabled').html(title);
                    }else{
                      $(cell).addClass('sorting_disabled').html('<input type="text" class="inline-fields" placeholder="' + title + '" />');
                    }
 
                    $(
                        'input',
                        $('.filters th').eq($(api.column(colIdx).header()).index())
                    )
                        .off('keyup change')
                        .on('keyup change', function (e) {
                            e.stopPropagation();
 
                            $(this).attr('title', $(this).val());
                            var regexr = '({search})';
 
                            var cursorPosition = this.selectionStart;
                            let wSelect = false;
                            $.each(api.column(colIdx).data(), function(index, val) {
                               if (val.indexOf('<select') == -1) {
                                wSelect = false;
                               }else{
                                wSelect = true;
                               }
                            });

                            api
                                .column(colIdx)
                                .search(

                                  (wSelect ?
                                      (this.value != ''
                                        ? regexr.replace('{search}', '(((selected' + this.value + ')))')
                                        : '')
                                    :
                                      (this.value != ''
                                        ? regexr.replace('{search}', '(((' + this.value + ')))')
                                        : '')),

                                    this.value != '',
                                    this.value == ''
                                ).draw()
 
                            $(this)
                                .focus()[0]
                                .setSelectionRange(cursorPosition, cursorPosition);
                        });
                });
        }
    });
  }

  initDatatable();

  setTimeout(()=>{
    $('.filters .inline-fields:first').trigger('keyup');
  },100);

  function deleteEmailLog(id) {
    if (!confirm('¿Eliminar de forma permanente este registro de comunicación?')) {
        return;
    }

    fetch('{{ url('/') }}/communications/' + id, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'X-Requested-With': 'XMLHttpRequest'
        }
    }).then(r => {
        if (!r.ok) {
            throw new Error('HTTP ' + r.status);
        }
        window.location.reload();
    }).catch(e => {
        alert('No se pudo eliminar: ' + e.message);
    });
  }

  const emailPreviewModal = document.getElementById('emailPreviewModal');
  const emailPreviewFrame = document.getElementById('emailPreviewFrame');
  const emailPreviewLoading = document.getElementById('emailPreviewLoading');
  const emailPreviewError = document.getElementById('emailPreviewError');
  const emailPreviewMeta = document.getElementById('emailPreviewMeta');

  function openEmailPreview(logId) {
    emailPreviewLoading.classList.remove('d-none');
    emailPreviewError.classList.add('d-none');
    emailPreviewFrame.classList.add('d-none');
    emailPreviewMeta.textContent = '';
    emailPreviewFrame.srcdoc = '';

    const modal = bootstrap.Modal.getOrCreateInstance(emailPreviewModal);
    modal.show();

    fetch('{{ url('/') }}/communications/' + logId + '/preview', {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    }).then(async (response) => {
        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'No se pudo cargar el contenido.');
        }

        document.getElementById('emailPreviewModalLabel').textContent = data.subject || 'Contenido del email';
        emailPreviewMeta.textContent = [
            data.message_type || '',
            data.recipient_email || '',
            data.date || ''
        ].filter(Boolean).join(' · ');

        emailPreviewFrame.srcdoc = data.html || '';
        emailPreviewLoading.classList.add('d-none');
        emailPreviewFrame.classList.remove('d-none');
    }).catch((error) => {
        emailPreviewLoading.classList.add('d-none');
        emailPreviewError.textContent = error.message;
        emailPreviewError.classList.remove('d-none');
    });
  }

  $(document).on('click', '.view-email-log', function(event) {
    event.preventDefault();
    const logId = $(this).data('log-id');
    if (logId) {
        openEmailPreview(logId);
    }
  });

  const emailResendModal = document.getElementById('emailResendModal');
  const emailResendConfirmBtn = document.getElementById('emailResendConfirmBtn');
  let pendingResendForm = null;

  $(document).on('click', '.resend-email-log', function() {
    pendingResendForm = $(this).closest('.resend-email-form').get(0);
    if (!pendingResendForm) {
        return;
    }

    document.getElementById('emailResendType').textContent = pendingResendForm.dataset.type || '—';
    document.getElementById('emailResendRecipient').textContent = pendingResendForm.dataset.recipient || '—';

    bootstrap.Modal.getOrCreateInstance(emailResendModal).show();
  });

  emailResendConfirmBtn.addEventListener('click', function() {
    if (!pendingResendForm) {
        return;
    }

    emailResendConfirmBtn.disabled = true;
    pendingResendForm.submit();
  });

  emailResendModal.addEventListener('hidden.bs.modal', function() {
    pendingResendForm = null;
    emailResendConfirmBtn.disabled = false;
  });

</script>

@endsection
