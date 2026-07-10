@extends('layouts.layout')

@section('title','Sorteos')

@section('content')

<!-- Start Content-->
<div class="container-fluid">
    
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item active">Sorteos</li>
                    </ol>
                </div>
                <h4 class="page-title">Sorteos</h4>
            </div>
        </div>
    </div>     

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

                    @include('partials.administration-list-filter-banner', [
                        'filterAdministration' => $filterAdministration ?? null,
                        'clearFilterUrl' => route('lotteries.index'),
                    ])

                    @if($lotteries->count() > 0)
                        <h4 class="header-title">

                            <div class="float-start d-flex align-items-start">
                                <input type="text" class="form-control" style="margin-right: 8px ;" placeholder="Nombre">
                                <input type="text" class="form-control" style="margin-right: 8px ;" placeholder="Tipo">
                                <input type="text" class="form-control" placeholder="Estado">
                            </div>

                            @if($lotteryAccess['canManageLotteries'] ?? false)
                            <a href="{{url('lottery/add')}}" style="border-radius: 30px; width: 150px;" class="btn btn-md btn-dark float-end"><i style="position: relative; top: 2px;" class="ri-add-line"></i> Añadir</a>
                            <button type="button" data-bs-toggle="modal" data-bs-target="#generateLotteriesModal" style="border-radius: 30px; width: 200px; margin-right: 10px;" class="btn btn-md btn-success float-end"><i style="position: relative; top: 2px;" class="ri-calendar-line"></i> Generar Sorteos</button>
                            @endif

                        </h4>

                        <div style="clear: both;"></div>

                        <br>

                        <table id="example2" class="table table-striped nowrap w-100">
                            <thead class="filters">
                                <tr>
                                    <th>Order ID</th>
                                    <th>Número</th>
                                    <th>Nombre del Sorteo</th>
                                    <th>Tipo de Sorteo</th>
                                    <th>Fecha Sorteo</th>
                                    <th>Fecha Límite</th>
                                    <th>Hora Límite</th>
                                    <th>Precio Décimo</th>
                                    <th class="no-filter"></th>
                                </tr>
                            </thead>
                        
                            <tbody>
                                @foreach($lotteries as $lottery)
                                <tr class="row-clickable" data-href="{{url('lottery/view', $lottery->id)}}" style="cursor: pointer;">
                                    <td><a href="{{url('lottery/view', $lottery->id)}}">#SR{{str_pad($lottery->id, 4, '0', STR_PAD_LEFT)}}</a></td>
                                    <td>{{$lottery->name}}</td>
                                    <td>{{$lottery->description}}</td>
                                    <td>{{$lottery->lotteryType->name ?? 'Sin tipo'}}</td>
                                    <td>{{$lottery->draw_date ? \Carbon\Carbon::parse($lottery->draw_date)->format('d/m/Y') : 'No definida'}}</td>
                                    <td>{{$lottery->deadline_date ? \Carbon\Carbon::parse($lottery->deadline_date)->format('d/m/Y') : 'No definida'}}</td>
                                    <td>{{$lottery->deadlineTimeLabel()}}</td>
                                    <td><b>{{number_format($lottery->ticket_price, 2)}}€</b></td>
                                    <td class="text-end no-click" style="cursor: default;">
                                        @if($lotteryAccess['canViewEntityPrizesOnly'] ?? false)
                                        <a href="{{ route('lotteries.show', $lottery->id) }}" class="btn btn-sm btn-light" title="Ver premio de mi entidad"><img src="{{url('assets/form-groups/results.svg')}}" alt="" width="12"></a>
                                        @else
                                        <a href="{{route('lotteries.show', $lottery->id)}}" class="btn btn-sm btn-light" title="Ver sorteo"><img src="{{url('assets/form-groups/eye.svg')}}" alt="" width="12"></a>
                                        @if($lotteryAccess['canViewResultsLists'] ?? false)
                                        <a href="{{route('lottery.show-results', $lottery->id)}}" class="btn btn-sm btn-light" title="Ver Resultados"><img src="{{url('assets/form-groups/results.svg')}}" alt="" width="12"></a>
                                        @endif
                                        @if($lotteryAccess['canEditLotteryFull'] ?? false)
                                        <a href="{{url('lottery/edit', $lottery->id)}}" class="btn btn-sm btn-light" title="Editar"><img src="{{url('assets/form-groups/edit.svg')}}" alt="" width="12"></a>
                                        @endif
                                        @if($lotteryAccess['canRunScrutiny'] ?? false)
                                        <a href="{{ route('lottery.scrutiny', $lottery->id) }}" class="btn btn-sm btn-warning" title="Escrutinio"><i class="ri-search-eye-line"></i></a>
                                        @endif
                                        @if($lotteryAccess['canManageLotteries'] ?? false)
                                        <button class="btn btn-sm btn-danger delete-btn" data-id="{{$lottery->id}}" data-name="{{$lottery->name}}" title="Eliminar"><i class="ri-delete-bin-6-line"></i></button>
                                        @endif
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <br>

                        @if($lotteryAccess['canViewLotteryTypes'] ?? false)
                        <a href="{{url('lottery_types?table=1')}}" style="border-radius: 30px; width: 180px; top: -12px; left: -12px; position: relative;" class="btn btn-md btn-dark">
                            <img src="{{url('icons_/tipos_sorteos.svg')}}" alt="" width="18px" style="position: relative; top: -1px;">
                         Tipos de Sorteo</a>
                        @endif

                         @if($lotteryAccess['canViewResultsLists'] ?? false)
                         <a href="{{url('lottery/administrations')}}" style="border-radius: 30px; width: 180px; top: -12px; left: -12px; position: relative; background-color: #e78307;" class="btn btn-md btn-light">
                            <img src="{{url('assets/form-groups/results.svg')}}" alt="" width="18px" style="position: relative; top: -1px;">
                         Lista Resultados</a>
                         @endif

                         {{-- <a href="{{route('lottery.results-table')}}" style="border-radius: 30px; width: 180px; top: -12px; left: -12px; position: relative; background-color: #28a745;" class="btn btn-md btn-light">
                            <img src="{{url('assets/form-groups/results.svg')}}" alt="" width="18px" style="position: relative; top: -1px;">
                         Tabla Resultados</a> --}}
                    @else
                        @if($lotteryAccess['canViewLotteryTypes'] ?? false)
                        <a href="{{url('lottery_types')}}" style="border-radius: 30px; width: 180px; top: -12px; left: -12px; position: relative;" class="btn btn-md btn-dark float-start">
                            <img src="{{url('icons_/tipos_sorteos.svg')}}" alt="" width="18px" style="position: relative; top: -1px;">
                         Tipos de Sorteo</a>
                         <div style="clear: both;"></div>
                        @endif
                        <div class="d-flex align-items-center gap-1">
                            
                            <div class="empty-tables">

                                <div>
                                    <img src="{{url('icons_/sorteos.svg')}}" alt="" width="80px">
                                </div>

                                @if($lotteryAccess['canViewEntityPrizesOnly'] ?? false)
                                <h3 class="mb-0">No hay sorteos con participación</h3>
                                <small class="text-muted" style="max-width: 420px; display: inline-block;">
                                    Esta cuenta solo muestra sorteos en los que la entidad tiene una <strong>reserva</strong>.
                                    Cuando el gestor cree una reserva, el sorteo aparecerá aquí para consultar premios y resultados.
                                </small>
                                @else
                                <h3 class="mb-0">No hay Sorteos</h3>
                                <small>Añade Sorteos</small>
                                <br>
                                <a href="{{url('lottery/add')}}" style="border-radius: 30px; width: 150px;" class="btn btn-md btn-dark mt-2"><i style="position: relative; top: 2px;" class="ri-add-line"></i> Añadir</a>
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

<!-- Modal para Generar Sorteos -->
<div class="modal fade" id="generateLotteriesModal" tabindex="-1" aria-labelledby="generateLotteriesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="generateLotteriesModalLabel">Generar Sorteos</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('lotteries.generate') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="row">
                        <div class="col-12">
                            <div class="form-group mb-3">
                                <label for="date_from" class="form-label">Fecha Desde</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" required>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group mb-3">
                                <label for="date_to" class="form-label">Fecha Hasta</label>
                                <input type="date" class="form-control" id="date_to" name="date_to" required>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">Generar</button>
                </div>
            </form>
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

  // Eliminar sorteo (delegación: botones en páginas siguientes del DataTable)
  $('#example2').on('click', '.delete-btn', function(e) {
    e.preventDefault();
    e.stopPropagation(); // Evitar que se active el clic de la fila
    var id = $(this).data('id');
    var name = $(this).data('name');
    $('#delete-warning').addClass('d-none');
    $('#delete-message').text('');
    $('#delete-modal').modal('show');
    $('#delete-item-name').text(name);
    $('#confirm-delete').data('id', id).data('type', 'lottery');
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