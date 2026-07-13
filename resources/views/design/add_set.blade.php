@extends('layouts.layout')

@section('title','Diseño e Impresión')

@section('content')

<style>
    .design-set-select-panel .design-set-select-table-wrap {
        width: 100%;
        max-width: 100%;
        overflow-x: auto;
        overflow-y: visible;
    }
    .design-set-select-panel .design-set-select-table-wrap .dataTables_wrapper {
        width: 100% !important;
        max-width: 100%;
    }
    .design-set-select-panel .design-set-select-table-wrap .dataTables_scroll {
        width: 100% !important;
        max-width: 100%;
    }
    .design-set-select-panel .design-set-select-table-wrap .dataTables_scrollHead,
    .design-set-select-panel .design-set-select-table-wrap .dataTables_scrollBody {
        width: 100% !important;
        max-width: 100%;
        overflow-x: auto !important;
    }
    .design-set-select-panel .design-set-select-table-wrap table.dataTable {
        width: 100% !important;
        margin-bottom: 0;
    }
    .design-set-select-panel #example2 tbody tr.table-row-selected > td {
        background-color: #e3f2fd !important;
    }
    .design-set-select-panel #example2 tbody tr.table-row-hover > td {
        background-color: #f8f9fa !important;
    }
</style>

<!-- Start Content-->
<div class="container-fluid">
    
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
            	<div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="javascript: void(0);">Diseño e Impresión</a></li>
                        <li class="breadcrumb-item active">Añadir</li>
                    </ol>
                </div>
                <h4 class="page-title">Diseño e Impresión</h4>
            </div>
        </div>
    </div>     

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

            		<h4 class="header-title">

                    	Selección Set

                    </h4>

                    <br>

                    <div class="row">
                    	
                    	<div class="col-md-3" style="position: relative;">
                    		<div class="form-card bs mb-3">

                    			<div class="form-wizard-element">
                    				
                    				<span>
                    					1
                    				</span>

                    				<img src="{{url('assets/entidad.svg')}}" alt="">

                    				<label>
                    					Selec. Entidad
                    				</label>

                    			</div>

                    			<div class="form-wizard-element">
                    				
                    				<span>
                    					2
                    				</span>

                    				<img width="26px" src="{{url('icons_/selec_sorteo.svg')}}" alt="">

                    				<label>
                    					Selec. Sorteo
                    				</label>

                    			</div>

                    			<div class="form-wizard-element active">
                    				
                    				<span>
                    					3
                    				</span>

                    				<img src="{{url('assets/entidad.svg')}}" alt="">

                    				<label>
                    					Selec. Set
                    				</label>

                    			</div>

                    			<div class="form-wizard-element">
                    				
                    				<span>
                    					4
                    				</span>

                    				<img src="{{url('assets/entidad.svg')}}" alt="">

                    				<label>
                    					Diseño Particip.
                    				</label>

                    			</div>
                    			
                    		</div>

                    		<a href="{{url('design/add/lottery')}}" style="border-radius: 30px; width: 200px; background-color: #333; color: #fff; padding: 8px; font-weight: bolder; position: absolute; bottom: 16px;" class="btn btn-md btn-light mt-2">
                    						<i style="top: 6px; left: 32%; font-size: 18px; position: absolute;" class="ri-arrow-left-circle-line"></i> <span style="display: block; margin-left: 16px;">Atrás</span></a>
                    	</div>
                    	<div class="col-md-9">
                    		<div class="form-card bs design-set-select-panel" style="min-height: 658px;">
                    			<form action="{{ route('design.chooseType') }}" method="POST" id="setSelectForm">
                                    @csrf
                    				<h4 class="mb-0 mt-1">
                    					Set en el que asignar participaciones
                    				</h4>
                    				<small><i>Selecciona un set</i></small>
                    				<br>
                    				<br>
                    				<div class="design-set-select-table-wrap">
                    					<table id="example2" class="table table-striped nowrap w-100 mb-0">
                    						<thead class="">
                    							<tr>
                    								<th>ID</th>
                    								<th>Nombre Set</th>
                    								<th>Importe Jugado <br> (por Número)</th>
                    								<th>Importe Donativo</th>
                    								<th>Importe TOTAL</th>
                    								<th>Participaciones Físicas</th>
                    								<th>Participaciones Disponibles</th>
                    								<th>Tipo</th>
                                                    <th></th>
                    								<th class="d-none">Seleccionar</th>
                    							</tr>
                    						</thead>
                    						<tbody>
                    							@foreach($sets as $set)
                                                    @php
                                                        $sl = isset($setLocksBySetId[$set->id]) ? $setLocksBySetId[$set->id] : ['locked' => false];
                                                        $rowLocked = !empty($sl['locked']);
                                                        $avail = $setAvailabilityBySetId[$set->id] ?? [
                                                            'available_for_new_design' => (int) $set->total_participations,
                                                            'has_design' => false,
                                                        ];
                                                        $availableForNew = (int) ($avail['available_for_new_design'] ?? 0);
                                                        $hasDesign = !empty($avail['has_design']);
                                                        $rowTitle = $rowLocked
                                                            ? 'Participaciones comprometidas: no podrás iniciar un diseño nuevo, pero sí reutilizar un diseño existente.'
                                                            : ($hasDesign && $availableForNew === 0
                                                                ? 'Este set ya tiene un diseño con participaciones generadas. Puedes continuar editándolo.'
                                                                : '');
                                                    @endphp
                    							<tr class="selectable-row" data-set-locked="{{ $rowLocked ? '1' : '0' }}" style="cursor: pointer;{{ $rowLocked ? 'opacity:0.85;' : '' }}"
                                                    title="{{ $rowTitle }}">
                    								<td>#SP{{str_pad($set->id, 4, '0', STR_PAD_LEFT)}}</td>
                    								<td>{{$set->set_name}}</td>
                    								<td>{{number_format($set->played_amount, 2)}}€</td>
                    								<td>{{number_format($set->donation_amount, 2)}}€</td>
                    								<td>{{number_format($set->total_amount, 2)}}€</td>
                    								<td>{{$set->physical_participations}}</td>
                    								<td>{{ $availableForNew }}</td>
                    								<td>
                    									@if(($set->physical_participations ?? 0) == 0)
                    										<span class="badge bg-primary">Digital</span>
                    									@elseif(($set->digital_participations ?? 0) == 0)
                    										<span class="badge bg-secondary">Físico</span>
                    									@else
                    										<span class="badge bg-info">Mixto</span>
                    									@endif
                    								</td>
                                                    <td>
                                                        @if($rowLocked)
                                                            <span class="badge bg-secondary rounded-pill">Bloqueado</span>
                                                        @elseif($hasDesign && $availableForNew === 0)
                                                            <span class="badge bg-info text-dark rounded-pill">Con diseño</span>
                                                        @elseif($availableForNew > 0)
                                                            <span class="badge bg-light text-muted border rounded-pill">Libre</span>
                                                        @else
                                                            <span class="badge bg-warning text-dark rounded-pill">Sin disponibilidad</span>
                                                        @endif
                                                    </td>
                    								<td class="d-none">
                    									<div class="form-check">
                    										<input class="form-check-input" type="radio" name="set_id" value="{{$set->id}}" id="set_{{$set->id}}" required>
                    										<label class="form-check-label" for="set_{{$set->id}}">
                    											Seleccionar
                    										</label>
                    									</div>
                    								</td>
                    							</tr>
                    							@endforeach
                    						</tbody>
                    					</table>
                    				</div>
                    				<div class="row">
                    					<div class="col-12 text-end">
                    						<button type="submit" style="border-radius: 30px; width: 200px; background-color: #e78307; color: #333; padding: 8px; font-weight: bolder; position: relative;" class="btn btn-md btn-light mt-2">Siguiente
                    							<i style="top: 6px; margin-left: 6px; font-size: 18px; position: absolute;" class="ri-arrow-right-circle-line"></i></button>
                    					</div>
                    				</div>
                    			</form>
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
    var table = $("#example2").DataTable({

      "select":{style:"single"},

      "ordering": false,
      "sorting": false,
      "autoWidth": false,

      "scrollX": true,
      "scrollCollapse": true,
        orderCellsTop: true,
        fixedHeader: true,
        initComplete: function () {
            var api = this.api();
            api.columns.adjust();
 
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

    $(window).on('resize.designSetSelectTable', function () {
      table.columns.adjust();
    });
  }

  initDatatable();

  setTimeout(()=>{
    $('.filters .inline-fields:first').trigger('keyup');
  },100);
  
  // Hacer las filas clickeables para seleccionar el radio button
  $(document).on('click', '#example2 tbody tr.selectable-row', function(e) {
    // No activar si se hace clic directamente en el radio button o su label
    if ($(e.target).is('input[type="radio"]') || $(e.target).is('label') || $(e.target).closest('label').length) {
      return;
    }
    
    // Seleccionar el radio button de la fila
    $(this).find('input[type="radio"]').prop('checked', true).trigger('change');
  });
  
  // Agregar efecto hover visual
  $(document).on('mouseenter', '#example2 tbody tr.selectable-row', function() {
    if (!$(this).hasClass('table-row-selected')) {
      $(this).addClass('table-row-hover');
    }
  }).on('mouseleave', '#example2 tbody tr.selectable-row', function() {
    $(this).removeClass('table-row-hover');
  });
  
  // Mantener el color cuando está seleccionado (solo celdas visibles, no el ancho total de scroll)
  $(document).on('change', '#example2 tbody tr.selectable-row input[type="radio"]', function() {
    $('#example2 tbody tr.selectable-row').removeClass('table-row-selected');
    if ($(this).is(':checked')) {
      $(this).closest('tr').addClass('table-row-selected');
    }
  });

</script>

@endsection