@extends('layouts.layout')

@section('title','Notificaciones')

@section('content')

<!-- Start Content-->
<div class="container-fluid">
    
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item active">Notificaciones</li>
                    </ol>
                </div>
                <h4 class="page-title">Notificaciones</h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

                    <div class="{{ count($notifications) ? '' : 'd-none' }}">
                        <h4 class="header-title">

                            <div class="float-start d-flex align-items-start">
                                <input type="text" class="form-control" style="margin-right: 8px;" placeholder="Provincia">
                                <input type="text" class="form-control" style="margin-right: 8px;" placeholder="Localidad">
                                <input type="text" class="form-control" style="margin-right: 8px;" placeholder="Entidad">
                                <input type="text" class="form-control" placeholder="Estatus">
                            </div>

                            <a href="{{ route('notifications.create') }}" style="border-radius: 30px; width: 150px;" class="btn btn-md btn-dark float-end"><i style="position: relative; top: 2px;" class="ri-add-line"></i> Nueva</a>

                        </h4>

                        <div style="clear: both;"></div>

                        <br>

                        <table id="example2" class="table table-striped nowrap w-100">
                            <thead class="filters">
                                <tr>
                                    <th>Orden ID</th>
                                    <th>Título Notificación</th>
                                    <th>Notificación</th>
                                    <th>Usuario Email</th>
                                    <th>Emisor</th>
                                    <th>Provincia</th>
                                    <th>Localidad</th>
                                    <th>Fecha</th>
                                    <th>Hora</th>
                                    <th class="no-filter"></th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach($notifications as $notification)
                                <tr id="notification-{{ $notification->id }}">
                                    <td><a href="#">#NO{{ str_pad($notification->id, 5, '0', STR_PAD_LEFT) }}</a></td>
                                    <td>{{ $notification->title }}</td>
                                    <td>{{ Str::limit($notification->message, 50) }}</td>
                                    <td>{{ $notification->entity?->manager?->user?->email ?? ($notification->entity ? 'Sin email' : 'N/A') }}</td>
                                    <td>{{ $notification->sender->name ?? 'N/A' }}</td>
                                    <td>{{ $notification->entity?->province ?? 'N/A' }}</td>
                                    <td>{{ $notification->entity?->city ?? 'N/A' }}</td>
                                    <td>{{ $notification->sent_at ? $notification->sent_at->format('d/m/Y') : $notification->created_at->format('d/m/Y') }}</td>
                                    <td>{{ $notification->sent_at ? $notification->sent_at->format('H:i') : $notification->created_at->format('H:i') }}</td>
                                    <td class="no-click" style="cursor: default;">
                                        <button type="button" class="btn btn-sm btn-danger" onclick="deleteNotification({{ $notification->id }})"><i class="ri-delete-bin-6-line"></i></button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>

                    </div>

                    <div class="{{ count($notifications) ? 'd-none' : '' }}">

                        <div class="d-flex align-items-center gap-1">

                            <div class="empty-tables">

                                <div>
                                    <img src="{{ url('icons_/notificaciones.svg') }}" alt="" width="80px">
                                </div>

                                <h3 class="mb-0">No hay Notificaciones</h3>

                                <small>Añade Notificaciones</small>

                                <br>

                                <a href="{{ route('notifications.create') }}" style="border-radius: 30px; width: 150px;" class="btn btn-md btn-dark mt-2"><i style="position: relative; top: 2px;" class="ri-add-line"></i> Nueva</a>
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

  function deleteNotification(id) {
    var run = function () {
        $.ajax({
          url: '{{ url('/') }}/notifications/delete/' + id,
          type: 'DELETE',
        headers: {
          'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
          location.reload();
        },
        error: function(xhr) {
            location.reload();
        }
      });
    };
    if (typeof window.partilotConfirm === 'function') {
      window.partilotConfirm({
        title: 'Eliminar notificación',
        message: '¿Estás seguro de que quieres eliminar esta notificación?',
        confirmText: 'Eliminar'
      }).then(function (ok) { if (ok) run(); });
      return;
    }
    run();
  }

</script>

@endsection
