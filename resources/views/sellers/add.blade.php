@extends('layouts.layout')

@section('title','Vendedores/Asignación')

@section('content')

<!-- Start Content-->
<div class="container-fluid">
    
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
            	<div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="javascript: void(0);">Vendedores/Asignación</a></li>
                        <li class="breadcrumb-item active">Añadir</li>
                    </ol>
                </div>
                <h4 class="page-title">Vendedores/Asignación</h4>
            </div>
        </div>
    </div>     

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

            		<h4 class="header-title">

                    	Selección Entidad

                    </h4>

                    <br>

                    <div class="row">
                    	
                    	<div class="col-md-3" style="position: relative;">
                    		<div class="form-card bs mb-3">

                    			<div class="form-wizard-element active">
                    				
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

                    				<img src="{{url('icons_/vendedores.svg')}}" alt="">

                    				<label>
                    					Dat. Vendedor
                    				</label>

                    			</div>
                    			
                    		</div>

                    		<a href="{{ route('sellers.index') }}" style="border-radius: 30px; width: 200px; background-color: #333; color: #fff; padding: 8px; font-weight: bolder; position: absolute; bottom: 16px;" class="btn btn-md btn-light mt-2">
                    						<i style="top: 6px; left: 32%; font-size: 18px; position: absolute;" class="ri-arrow-left-circle-line"></i> <span style="display: block; margin-left: 16px;">Atrás</span></a>
                    	</div>
                    	<div class="col-md-9">
                    		<div class="form-card bs wizard-entity-step" style="min-height: 658px;">
                    			<h4 class="mb-0 mt-1">
                    				Entidad en la que realizar la búsqueda
                    			</h4>
                    			<small><i>Selecciona la entidad</i></small>

                    			<br>
                    			<br>

                    			<div class="wizard-table-scroll">

	                    			<table id="example2" class="table table-striped nowrap w-100">
			                            <thead class="">
				                            <tr>
				                                <th>ID</th>
				                                <th>Nombre</th>
				                                <th>Provincia</th>
				                                <th>Localidad</th>
				                                <th>Administración</th>
				                                <th>Estado</th>
				                                <th class="d-none">Seleccionar</th>
				                            </tr>
				                        </thead>
				                    
				                        <tbody>
				                            @foreach($entities as $entity)
				                            @php $isActive = $entity->status == 1; @endphp
				                            <tr class="selectable-row {{ $isActive ? '' : 'entity-inactive' }}" style="cursor: {{ $isActive ? 'pointer' : 'not-allowed' }};" data-entity-status="{{ $entity->status }}">
				                                <td>#{{ $entity->id }}</td>
				                                <td>{{ $entity->name }}</td>
				                                <td>{{ $entity->province }}</td>
				                                <td>{{ $entity->city }}</td>
				                                <td>{{ $entity->administration->name ?? 'N/A' }}</td>
				                                <td>
				                                    <span class="badge bg-{{ $entity->status == 1 ? 'success' : ($entity->status == 0 ? 'danger' : 'secondary') }}">
				                                        {{ $entity->status_text }}
				                                    </span>
				                                </td>
				                                <td class="d-none">
                                                    <label>
				                                        <input type="radio" name="entity_id" value="{{ $entity->id }}" class="form-check-input" {{ $isActive ? '' : 'disabled' }} {{ $isActive ? 'required' : '' }}> Seleccionar
                                                    </label>
				                                </td>
				                            </tr>
				                            @endforeach
				                        </tbody>
			                        </table>

		                        </div>

                    			<div class="row wizard-form-actions">

                    				<div class="col-12 text-end">
                    					<form action="{{ route('sellers.store-entity') }}" method="POST" id="entity-form">
                    						@csrf
                    						<input type="hidden" name="entity_id" id="selected-entity-id">
                    						<button type="submit" style="border-radius: 30px; width: 200px; background-color: #e78307; color: #333; padding: 8px; font-weight: bolder; position: relative;" class="btn btn-md btn-light mt-2" id="next-button" disabled>Siguiente
                    							<i style="top: 6px; margin-left: 6px; font-size: 18px; position: absolute;" class="ri-arrow-right-circle-line"></i></button>
                    					</form>
                    				</div>

                    			</div>

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

      "select":{style:"single"},

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

                            if (wSelect) {
                                api
                                    .column(colIdx)
                                    .search(
                                        this.value != ''
                                            ? regexr.replace('{search}', '(((' + this.value.replace(/[.,\/+*?|{}()\[\]\\]/g, '\\$&') + ')))')
                                            : '',
                                        this.value != '',
                                        this.value == ''
                                    )
                                    .draw();
                            } else {
                                api
                                    .column(colIdx)
                                    .search(
                                        this.value != ''
                                            ? regexr.replace('{search}', '(((' + this.value.replace(/[.,\/+*?|{}()\[\]\\]/g, '\\$&') + ')))')
                                            : '',
                                        this.value != '',
                                        this.value == ''
                                    )
                                    .draw();
                            }
 
                            $(this)
                                .focus()[0]
                                .setSelectionRange(cursorPosition, cursorPosition);
                        });
                });
        },
    });
  }

  $(document).ready(function() {
    initDatatable();
    
    // Hacer las filas clickeables para seleccionar el radio button (solo entidades activas)
    $(document).on('click', '#example2 tbody tr.selectable-row', function(e) {
      if ($(this).hasClass('entity-inactive')) return;
      // No activar si se hace clic directamente en el radio button o su label
      if ($(e.target).is('input[type="radio"]') || $(e.target).is('label') || $(e.target).closest('label').length) {
        return;
      }
      
      // Seleccionar el radio button de la fila
      var radio = $(this).find('input[type="radio"]');
      radio.prop('checked', true).trigger('change');
    });
    
    // Agregar efecto hover visual
    $(document).on('mouseenter', '#example2 tbody tr.selectable-row', function() {
      $(this).css('background-color', '#f8f9fa');
    }).on('mouseleave', '#example2 tbody tr.selectable-row', function() {
      if (!$(this).find('input[type="radio"]').is(':checked')) {
        $(this).css('background-color', '');
      }
    });
    
    // Mantener el color cuando está seleccionado
    $(document).on('change', '#example2 tbody tr.selectable-row input[type="radio"]', function() {
      $('#example2 tbody tr.selectable-row').css('background-color', '');
      if ($(this).is(':checked')) {
        $(this).closest('tr').css('background-color', '#e3f2fd');
      }
    });
    
    // Manejar selección de radio buttons
    $('input[name="entity_id"]').change(function() {
        $('#selected-entity-id').val($(this).val());
        $('#next-button').prop('disabled', false);
    });
  });

</script>

@endsection