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
                        <li class="breadcrumb-item"><a href="javascript: void(0);">Sorteos</a></li>
                        <li class="breadcrumb-item active">Resultados</li>
                    </ol>
                </div>
                <h4 class="page-title">Selección Administración</h4>
            </div>
        </div>
    </div>     

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

                	<div>
	                    <h4 class="header-title">

	                    	{{-- <div class="float-start d-flex align-items-start">
	                    		<input type="text" class="form-control" style="margin-right: 8px ;" placeholder="Provincia">
	                    		<input type="text" class="form-control" style="margin-right: 8px ;" placeholder="Localidad">
	                    		<input type="text" class="form-control" placeholder="Status">
	                    	</div> --}}

	                    	<a href="{{url('administrations/add')}}" style="border-radius: 30px; width: 150px;" class="btn btn-md btn-dark float-end"><i style="position: relative; top: 2px;" class="ri-add-line"></i> Añadir</a>

	                    </h4>

	                    <div style="clear: both;"></div>

	                    <br>

	                    <form id="administrationForm" action="{{ route('lottery.select-administration') }}" method="POST">
                        @csrf
                        <table id="example2" class="table table-striped nowrap w-100">
                            <thead class="filters">
                                <tr>
                                    <th>ID</th>
                                    <th>Administración</th>
                                    <th>Nº Receptor</th>
                                    <th>Provincia</th>
                                    <th>Localidad</th>
                                    <th>Status</th>
                                    <th class="d-none">Seleccionar</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($administrations as $administration)
                                    <tr class="administration-row selectable-row" style="cursor: pointer;">
                                        <td>#{{ str_pad($administration->id, 6, '0', STR_PAD_LEFT) }}</td>
                                        <td>{{ $administration->name }}</td>
                                        <td>{{ $administration->receiving }}</td>
                                        <td>{{ $administration->province }}</td>
                                        <td>{{ $administration->city }}</td>
                                        <td>
                                            <label class="badge bg-{{ $administration->status_class }}">{{ $administration->status_text }}</label>
                                        </td>
                                        <td class="d-none">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="administration_id" 
                                                       id="admin_{{ $administration->id }}" value="{{ $administration->id }}" required>
                                                <label class="form-check-label" for="admin_{{ $administration->id }}">
                                                    Seleccionar
                                                </label>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center">No hay administraciones disponibles</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </form>

	                    <br>

	                    <div class="row">

            				<div class="col-6 text-start">
            					<a href="{{url('lottery?table=1')}}" style="border-radius: 30px; width: 200px; padding: 8px; font-weight: bolder; position: relative; background-color: #333; color: #fff;" class="btn btn-md btn-dark mt-2"><i style="top: 6px; left: 32%; font-size: 18px; position: absolute;" class="ri-arrow-left-circle-line"></i> <span style="display: block; margin-left: 16px;">Atrás</span></a>
            				</div>

            				<div class="col-6 text-end">
            					<button type="submit" form="administrationForm" style="border-radius: 30px; width: 200px; background-color: #e78307; color: #333; padding: 8px; font-weight: bolder; position: relative;" class="btn btn-md btn-light mt-2">Siguiente
            						<i style="top: 6px; margin-left: 6px; font-size: 18px; position: absolute;" class="ri-arrow-right-circle-line"></i></button>
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

@section('styles')
<style>
    .administration-row:hover {
        background-color: #f8f9fa !important;
        transition: background-color 0.2s ease;
    }
    
    .administration-row.selected {
        background-color: #e3f2fd !important;
    }
    
    .form-check-input:checked + .form-check-label {
        font-weight: bold;
        color: #007bff;
    }
</style>
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

  // Validación del formulario
  $('#administrationForm').on('submit', function(e) {
    var selectedAdmin = $('input[name="administration_id"]:checked').val();
    if (!selectedAdmin) {
      e.preventDefault();
      alert('Por favor, selecciona una administración antes de continuar.');
      return false;
    }
  });

  // Mejorar la experiencia de usuario al hacer clic en la fila
  $('#example2 tbody tr').on('click', function(e) {
    // No activar si se hace clic en el radio button directamente
    if ($(e.target).is('input[type="radio"]') || $(e.target).is('label')) {
      return;
    }
    
    // Seleccionar el radio button de la fila
    $(this).find('input[type="radio"]').prop('checked', true);
    
    // Actualizar clases visuales
    $('.administration-row').removeClass('selected');
    $(this).addClass('selected');
  });

  // Actualizar clases cuando se selecciona un radio button directamente
  $('input[name="administration_id"]').on('change', function() {
    $('.administration-row').removeClass('selected');
    $(this).closest('tr').addClass('selected');
  });

</script>

@endsection