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
                        <li class="breadcrumb-item"><a href="javascript: void(0);">Entidades</a></li>
                        <li class="breadcrumb-item active">Añadir</li>
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

                    				<img src="{{url('assets/admin.svg')}}" alt="">

                    				<label>
                    					Selec. Administración
                    				</label>

                    			</div>

                    			<div class="form-wizard-element">
                    				
                    				<span>
                    					2
                    				</span>

                    				<img src="{{url('assets/entidad.svg')}}" alt="">

                    				<label>
                    					Datos Entidad
                    				</label>

                    			</div>

                    			<div class="form-wizard-element">
                    				
                    				<span>
                    					3
                    				</span>

                    				<img src="{{url('assets/gestor.svg')}}" alt="">

                    				<label>
                    					Datos Gestor
                    				</label>

                    			</div>
                    			
                    		</div>

                    		<a href="{{url('entities')}}" style="border-radius: 30px; width: 200px; background-color: #333; color: #fff; padding: 8px; font-weight: bolder; position: absolute; bottom: 16px;" class="btn btn-md btn-light mt-2">
                    						<i style="top: 6px; left: 32%; font-size: 18px; position: absolute;" class="ri-arrow-left-circle-line"></i> <span style="display: block; margin-left: 16px;">Atrás</span></a>
                    	</div>
                    	<div class="col-md-9">
                    		<div class="form-card bs" style="min-height: 658px;">
                    			<form action="{{url('entities/store-administration')}}" method="POST">
                    				@csrf()
                    				<h4 class="mb-0 mt-1">
                    					Administración a la que pertenece la entidad
                    				</h4>
                    				<small><i>Selecciona la administración</i></small>

                    				<br>
                    				<br>

                    				<div style="min-height: 656px;">

		                    			<table id="example2" class="table table-striped nowrap w-100">
				                            <thead class="filters">
					                            <tr>
					                                <th>Order ID</th>
					                                <th>Administración</th>
					                                <th>Nº Receptor</th>
					                                <th>Provincia</th>
					                                <th>Localidad</th>
					                                <th>Status</th>
					                                <th class="d-none">Seleccionar</th>
					                            </tr>
					                        </thead>
				                    
				                    
				                        <tbody>
				                            @foreach($administrations as $administration)
				                            <tr class="selectable-row" style="cursor: pointer;">
				                                <td>#AD{{str_pad($administration->id, 4, '0', STR_PAD_LEFT)}}</td>
				                                <td>{{$administration->name}}</td>
				                                <td>{{$administration->receiving}}</td>
				                                <td>{{$administration->province}}</td>
				                                <td>{{$administration->city}}</td>
				                                <td><label class="badge bg-{{ $administration->status_class }}">{{ $administration->status_text }}</label></td>
				                                <td class="d-none">
				                                    <div class="form-check">
				                                        <input class="form-check-input" type="radio" name="administration_id" value="{{$administration->id}}" id="admin_{{$administration->id}}" required>
				                                        <label class="form-check-label" for="admin_{{$administration->id}}">
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
                    					<button type="submit" style="border-radius: 30px; width: 200px; background-color: #e78307; color: #333; padding: 8px; font-weight: bolder; position: relative;" class="btn btn-md btn-light mt-2 btn-next">Siguiente
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
                            api
                                .column(colIdx)
                                .search(
                                    this.value != ''
                                        ? regexr.replace('{search}', '(((' + this.value + ')))')
                                        : '',
                                    this.value != '',
                                    this.value == ''
                                )
                                .draw();
 
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
  });

</script>

@endsection