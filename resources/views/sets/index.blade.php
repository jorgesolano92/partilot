@extends('layouts.layout')

@section('title','Sets de Participaciones')

@section('content')

<!-- Start Content-->
<div class="container-fluid">
    
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
            	<div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item active">Sets de Participaciones</li>
                    </ol>
                </div>
                <h4 class="page-title">Sets de Participaciones</h4>
            </div>
        </div>
    </div>     

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

                    @include('partials.administration-list-filter-banner', [
                        'filterAdministration' => $filterAdministration ?? null,
                        'clearFilterUrl' => route('sets.index'),
                    ])

                    <div class="{{$sets->count() > 0 ? '' : 'd-none'}}">
                        <h4 class="header-title">

                            <div class="float-start d-flex align-items-start">
                                <input type="text" class="form-control" style="margin-right: 8px ;" placeholder="Provincia">
                                <input type="text" class="form-control" style="margin-right: 8px ;" placeholder="Localidad">
                                <input type="text" class="form-control" placeholder="Status">
                            </div>

                            <a href="{{url('sets/add')}}" style="border-radius: 30px; width: 150px;" class="btn btn-md btn-dark float-end"><i style="position: relative; top: 2px;" class="ri-add-line"></i> Añadir</a>

                        </h4>

                        <div style="clear: both;"></div>

                        <br>

                        <table id="example2" class="table table-striped nowrap w-100">
                            <thead class="filters">
                                <tr>
                                    <th>Order ID</th>
                                    <th>Nombre Set</th>
                                    <th>N.Sorteo</th>
                                    <th>Número/s</th>
                                    <th>Importe Jugado (por Número)</th>
                                    <th>Importe Donativo</th>
                                    <th>Importe Total</th>
                                    <th>Participaciones TOTAL</th>
                                    <th>Tipo</th>
                                    <th>Entidad</th>
                                    <th>Provincia</th>
                                    <th class="no-filter"></th>
                                </tr>
                            </thead>
                        
                        
                            <tbody>
                                @foreach($sets as $set)
                                <tr class="row-clickable" data-href="{{url('sets/view', $set->id)}}" style="cursor: pointer;">
                                    <td><a href="{{url('sets/view', $set->id)}}">#SP{{str_pad($set->id, 4, '0', STR_PAD_LEFT)}}</a></td>
                                    <td>{{$set->set_name}}</td>
                                    <td>{{$set->reserve->lottery ? $set->reserve->lottery->name : 'Sin sorteo'}}</td>
                                    <td>
                                        @if($set->reserve->reservation_numbers)
                                            @foreach($set->reserve->reservation_numbers as $number)
                                                {{$number}}{{!$loop->last ? ' - ' : ''}}
                                            @endforeach
                                        @else
                                            <span class="text-muted">Sin números</span>
                                        @endif
                                    </td>
                                    <td>{{number_format($set->played_amount ?? 0, 2)}}€</td>
                                    <td>{{number_format($set->donation_amount ?? 0, 2)}}€</td>
                                    <td><b>{{number_format($set->total_amount, 2)}}€</b></td>
                                    <td>{{$set->total_participations}}</td>
                                    <td>
                                        @if(($set->physical_participations ?? 0) == 0)
                                            <span class="badge bg-primary">Digital</span>
                                        @elseif(($set->digital_participations ?? 0) == 0)
                                            <span class="badge bg-secondary">Físico</span>
                                        @else
                                            <span class="badge bg-info">Mixto</span>
                                        @endif
                                    </td>
                                    <td>{{$set->entity->name ?? 'Sin entidad'}}</td>
                                    <td>{{$set->entity->province ?? 'Sin provincia'}}</td>
                                    <td class="no-click" style="cursor: default;">
                                        <a href="{{ route('design.openEditor', $set->id) }}" class="btn btn-sm btn-light" title="Diseño e impresión"><img src="{{url('icons_/diseno.svg')}}" alt="" width="12"></a>
                                        @if(auth()->user()?->isSuperAdmin())
                                        <a href="{{ route('sets.download-xml', $set->id) }}" class="btn btn-sm btn-light" title="Descargar XML"><i class="ri-download-line"></i></a>
                                        @endif
                                        <a href="{{url('sets/edit', $set->id)}}" class="btn btn-sm btn-light"><img src="{{url('assets/form-groups/edit.svg')}}" alt="" width="12"></a>
                                        <button class="btn btn-sm btn-danger delete-btn" data-id="{{$set->id}}" data-name="set #{{$set->id}}"><i class="ri-delete-bin-6-line"></i></button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>

                    </div>

                    <div class="{{$sets->count() > 0 ? 'd-none' : ''}}">
                        <div class="d-flex align-items-center gap-1">
                            
                            <div class="empty-tables">

                                <div>
                                    <img src="{{url('icons_/sets.svg')}}" alt="" width="80px" style="margin-top: 10px;">
                                </div>

                                <h3 class="mb-0">No hay Sets de Participaciones</h3>

                                <small>Añade Sets</small>

                                <br>

                                <a href="{{url('sets/add')}}" style="border-radius: 30px; width: 150px;" class="btn btn-md btn-dark mt-2"><i style="position: relative; top: 2px;" class="ri-add-line"></i> Añadir</a>
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
 
            // For each column
            api
                .columns()
                .eq(0)
                .each(function (colIdx) {
                    // Set the header cell to contain the input element
                    var cell = $('.filters th').eq(
                        $(api.column(colIdx).header()).index()
                    );
                    var title = $(cell).text();
                    if ($(cell).hasClass('no-filter')) {
                      $(cell).addClass('sorting_disabled').html(title);
                    }else{
                      $(cell).addClass('sorting_disabled').html('<input type="text" class="inline-fields" placeholder="' + title + '" />');
                    }
 
                    // On every keypress in this input
                    $(
                        'input',
                        $('.filters th').eq($(api.column(colIdx).header()).index())
                    )
                        .off('keyup change')
                        .on('keyup change', function (e) {
                            e.stopPropagation();
 
                            // Get the search value
                            $(this).attr('title', $(this).val());
                            var regexr = '({search})'; //$(this).parents('th').find('select').val();
 
                            var cursorPosition = this.selectionStart;
                            // Search the column for that value

                            // console.log(val.replace(/<select[\s\S]*?<\/select>/,''));
                            let wSelect = false;
                            $.each(api.column(colIdx).data(), function(index, val) {
                               if (val.indexOf('<select') == -1) {
                                wSelect = false;
                               }else{
                                wSelect = true;
                               }
                            });

                            // $.each(api
                            //     .column(colIdx).data(), function(index, val) {
                            //         console.log(val)
                            // });

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
  
  // Hacer las filas clickeables (excepto la última columna de acciones)
  $(document).on('click', '#example2 tbody tr.row-clickable', function(e) {
    // No activar si se hace clic en la última columna o en sus elementos
    if ($(e.target).closest('td.no-click').length || $(e.target).closest('td.no-click').length) {
      return;
    }
    
    // No activar si se hace clic directamente en un enlace o botón
    if ($(e.target).is('a') || $(e.target).is('button') || $(e.target).closest('a').length || $(e.target).closest('button').length) {
      return;
    }
    
    // Redirigir a la URL de la fila
    var href = $(this).data('href');
    if (href) {
      window.location.href = href;
    }
  });
  
  // Agregar efecto hover visual
  $(document).on('mouseenter', '#example2 tbody tr.row-clickable', function() {
    $(this).css('background-color', '#f8f9fa');
  }).on('mouseleave', '#example2 tbody tr.row-clickable', function() {
    $(this).css('background-color', '');
  });
  
  // Evitar que los botones de acción activen el clic de la fila
  $('.delete-btn').on('click', function(e) {
    e.stopPropagation();
    var id = $(this).data('id');
    var name = $(this).data('name');
    $('#delete-modal').modal('show');
    $('#delete-item-name').text(name);
    $('#confirm-delete').data('id', id).data('type', 'set');
  });

</script>

<!-- Modal de confirmación de eliminación -->
<div class="modal fade" id="delete-modal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="deleteModalLabel">Confirmar eliminación</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>¿Estás seguro de que quieres eliminar <strong id="delete-item-name"></strong>?</p>
        <div id="delete-warning" class="alert alert-warning d-none" role="alert">
          <strong>Advertencia:</strong> <span id="delete-message"></span>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-danger" id="confirm-delete">Eliminar</button>
      </div>
    </div>
  </div>
</div>

<script>
$('#confirm-delete').on('click', function() {
  var id = $(this).data('id');
  var type = $(this).data('type');
  // Llamar a la función de verificación y eliminación
  checkAndDelete(type, id);
});

function checkAndDelete(type, id) {
  $.ajax({
    url: '/api/check-delete/' + type + '/' + id,
    method: 'GET',
    success: function(response) {
      if (response.can_delete) {
        // Proceder a eliminar
        deleteItem(type, id);
      } else {
        // Mostrar mensaje de advertencia
        $('#delete-message').text(response.message);
        $('#delete-warning').removeClass('d-none');
      }
    },
    error: function() {
      alert('Error al verificar eliminación.');
    }
  });
}

function deleteItem(type, id) {
  $.ajax({
    url: '/api/delete/' + type + '/' + id,
    method: 'DELETE',
    headers: {
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    },
    success: function(response) {
      $('#delete-modal').modal('hide');
      location.reload(); // Recargar la página
    },
    error: function(xhr) {
      var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Error al eliminar el elemento.';
      alert(msg);
    }
  });
}
</script>

@endsection