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
                                        <td><a href="#">#NO{{ str_pad($log->id, 5, '0', STR_PAD_LEFT) }}</a></td>
                                        <td>
                                            <span @if($log->messageTypeKey()) title="Key: {{ $log->messageTypeKey() }}" @endif>{{ $log->displayMessageType() }}</span>
                                            @if($log->messageTypeKey())
                                                <span class="visually-hidden">{{ $log->messageTypeKey() }}</span>
                                            @endif
                                        </td>
                                        <td>{{ $log->sender_type }}</td>
                                        <td>{{ $log->recipient_email }}</td>
                                        <td>{{ $log->recipient_role ?? '-' }}</td>
                                        <td><span class="badge {{ $log->displayStatusBadgeClass() }}">{{ $log->displayStatus() }}</span></td>
                                        <td>{{ $dateText }}</td>
                                        <td class="no-click" style="cursor: default; white-space: nowrap;">
                                            <form method="POST" action="{{ route('communications.resend', $log->id) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-dark" {{ empty($log->mail_class) ? 'disabled' : '' }}>
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

</script>

@endsection
