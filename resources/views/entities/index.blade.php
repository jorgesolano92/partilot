@extends('layouts.layout')

@section('title','Entidades')

@section('content')

<!-- Start Content-->
<div class="container-fluid">
    
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item active">Entidades</li>
                    </ol>
                </div>
                <h4 class="page-title">Entidades</h4>
            </div>
        </div>
    </div>     

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

                    @include('partials.administration-list-filter-banner', [
                        'filterAdministration' => $filterAdministration ?? null,
                        'clearFilterUrl' => route('entities.index'),
                    ])

                    @if($entities->count() > 0)
                        <h4 class="header-title">

                            <div class="float-start d-flex align-items-start">
                                <input type="text" class="form-control" style="margin-right: 8px ;" placeholder="Provincia">
                                <input type="text" class="form-control" style="margin-right: 8px ;" placeholder="Localidad">
                                <input type="text" class="form-control" placeholder="Status">
                            </div>

                            @if($canAddEntity ?? true)
                            <a href="{{url('entities/add')}}" style="border-radius: 30px; width: 150px;" class="btn btn-md btn-dark float-end"><i style="position: relative; top: 2px;" class="ri-add-line"></i> Añadir</a>
                            @endif

                        </h4>

                        <div style="clear: both;"></div>

                        <br>

                        <table id="example2" class="table table-striped nowrap w-100">
                            <thead class="filters">
                                <tr>
                                    <th>Código</th>
                                    <th>Entidad</th>
                                    <th>Provincia</th>
                                    <th>Localidad</th>
                                    <th>Gestor</th>
                                    <th>Teléfono</th>
                                    <th>Email</th>
                                    @if(empty($hideAdministrationColumn))
                                    <th>Administración</th>
                                    @endif
                                    <th>Estado</th>
                                    <th class="no-filter"></th>
                                </tr>
                            </thead>
                        
                        
                            <tbody>
                                @foreach($entities as $entity)
                                <tr class="row-clickable" data-href="{{url('entities/view', $entity->id)}}" style="cursor: pointer;">
                                    <td><a href="{{url('entities/view', $entity->id)}}">#EN{{str_pad($entity->id, 4, '0', STR_PAD_LEFT)}}</a></td>
                                    <td>{{$entity->name ?? 'Sin nombre'}}</td>
                                    <td>{{$entity->province ?? 'Sin provincia'}}</td>
                                    <td>{{$entity->city ?? 'Sin localidad'}}</td>
                                    <td>
                                        @if($entity->manager?->user)
                                            {{ trim($entity->manager->user->name.' '.$entity->manager->user->last_name) }}
                                        @else
                                            <span class="text-muted">Sin gestor principal</span>
                                        @endif
                                    </td>
                                    <td>{{$entity->phone ?? 'Sin teléfono'}}</td>
                                    <td>{{$entity->email ?? 'Sin email'}}</td>
                                    @if(empty($hideAdministrationColumn))
                                    <td>{{$entity->administration ? $entity->administration->name : 'Sin administración'}}</td>
                                    @endif
                                    <td>
                                        @php
                                            $statusValue = $entity->status;
                                            if ($statusValue === null || $statusValue === -1) {
                                                $statusText = 'Pendiente';
                                                $statusClass = 'bg-secondary';
                                            } elseif ($statusValue == 1) {
                                                $statusText = 'Activo';
                                                $statusClass = 'bg-success';
                                            } else {
                                                $statusText = 'Inactivo';
                                                $statusClass = 'bg-danger';
                                            }
                                        @endphp
                                        <label class="badge {{ $statusClass }}">{{ $statusText }}</label>
                                    </td>
                                    <td class="no-click" style="cursor: default;">
                                        <a class="btn btn-sm btn-light" title="Ver entidad" href="{{url('entities/view', $entity->id)}}"><img src="{{url('icons_/persons.svg')}}" alt="" width="12"></a>
                                        <a class="btn btn-sm btn-light" title="Diseños" href="{{ route('design.index', ['entity_id' => $entity->id]) }}"><img src="{{url('icons_/design.svg')}}" alt="" width="12"></a>
                                        <a class="btn btn-sm btn-light" title="Reservas" href="{{ route('reserves.index', ['entity_id' => $entity->id]) }}"><img src="{{url('icons_/reservas.svg')}}" alt="" width="12"></a>
                                        <a class="btn btn-sm btn-light" title="Participaciones" href="{{ route('sets.index', ['entity_id' => $entity->id]) }}"><img src="{{url('icons_/participations.svg')}}" alt="" width="12"></a>
                                        <a class="btn btn-sm btn-light" title="Devoluciones" href="{{ route('devolutions.index', ['entity_id' => $entity->id]) }}"><img src="{{url('icons_/returns.svg')}}" alt="" width="12"></a>
                                        @if($canAddEntity ?? true)
                                        <button type="button" class="btn btn-sm btn-danger delete-btn" title="Eliminar entidad" data-id="{{$entity->id}}" data-name="{{$entity->name}}"><i class="ri-delete-bin-6-line"></i></button>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <div class="d-flex align-items-center gap-1">
                            
                            <div class="empty-tables">

                                <div>
                                    <img src="{{url('icons_/entidades.svg')}}" alt="" width="80px">
                                </div>

                                <h3 class="mb-0">No hay Entidades</h3>

                                <small>Añade Entidades</small>

                                <br>

                                @if($canAddEntity ?? true)
                                <a href="{{url('entities/add')}}" style="border-radius: 30px; width: 150px;" class="btn btn-md btn-dark mt-2"><i style="position: relative; top: 2px;" class="ri-add-line"></i> Añadir</a>
                                @endif
                            </div>

                        </div>
                    @endif
                    
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
                            //     console.log(val)
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

  // Eliminar entidad
  $('.delete-btn').on('click', function(e) {
    e.stopPropagation(); // Evitar que se active el clic de la fila
    var id = $(this).data('id');
    var name = $(this).data('name');
    $('#delete-modal').modal('show');
    $('#delete-item-name').text(name);
    $('#confirm-delete').data('id', id).data('type', 'entity');
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
    error: function() {
      alert('Error al eliminar el elemento.');
    }
  });
}
</script>

@endsection