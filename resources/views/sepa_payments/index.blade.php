@extends('layouts.layout')

@section('title','Órdenes de Pago SEPA')

@section('content')

<!-- Start Content-->
<div class="container-fluid">
    
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
            	<div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item active">Órdenes de Pago SEPA</li>
                    </ol>
                </div>
                <h4 class="page-title">Órdenes de Pago SEPA</h4>
            </div>
        </div>
    </div>     

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    @if($errors->any())
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                	<div class="{{count($orders) ? '' : 'd-none'}}">
	                    <h4 class="header-title">
	                    	<a href="{{route('sepa-payments.create')}}" style="border-radius: 30px; width: 200px;" class="btn btn-md btn-dark float-end">
                                <i style="position: relative; top: 2px;" class="ri-add-line"></i> Nueva Orden de Pago
                            </a>
	                    </h4>

	                    <div style="clear: both;"></div>

	                    <br>

	                    <table id="example2" class="table table-striped nowrap w-100 selectable-rows">
	                        <thead class="filters">
	                            <tr>
	                                <th>ID</th>
	                                <th>Message ID</th>
	                                <th>Administración</th>
	                                <th>Deudor</th>
	                                <th>Fecha Ejecución</th>
	                                <th>Nº Transacciones</th>
	                                <th>Importe Total</th>
	                                <th>Estado</th>
	                                <th>Archivo XML</th>
	                                <th class="no-filter">Acciones</th>
	                            </tr>
	                        </thead>
	                    
	                        <tbody>
                                @foreach ($orders as $order)
	                            <tr>
	                                <td><a href="{{route('sepa-payments.show', $order->id)}}">#{{ $order->id }}</a></td>
	                                <td>{{ $order->message_id }}</td>
	                                <td>{{ $order->administration->name ?? 'N/A' }}</td>
	                                <td>{{ $order->debtor_name }}</td>
	                                <td>{{ $order->execution_date->format('d/m/Y') }}</td>
	                                <td>{{ $order->number_of_transactions }}</td>
	                                <td>{{ number_format($order->control_sum, 2, ',', '.') }} €</td>
	                                <td>
                                        @if($order->status == 'draft')
                                            <label class="badge bg-warning">Borrador</label>
                                        @elseif(in_array($order->status, ['descargado', 'generated']))
                                            <label class="badge bg-info">Descargado</label>
                                        @elseif($order->status == 'listo')
                                            <label class="badge bg-success">Listo</label>
                                        @elseif($order->status == 'uploaded')
                                            <label class="badge bg-info">Subido</label>
                                        @else
                                            <label class="badge bg-secondary">{{ $order->status }}</label>
                                        @endif
                                    </td>
	                                <td>
                                        @if($order->xml_filename)
                                            <button type="button" class="btn btn-sm btn-primary" title="Descargar XML" data-sepa-confirm-download="{{ $order->id }}">
                                                <i class="ri-download-line"></i> XML
                                            </button>
                                        @else
                                            <button type="button" class="btn btn-sm btn-success" title="Generar XML" data-sepa-confirm-download="{{ $order->id }}">
                                                <i class="ri-file-add-line"></i> Generar
                                            </button>
                                        @endif
                                    </td>
	                                <td>
	                                	<a href="{{route('sepa-payments.show', $order->id)}}" class="btn btn-sm btn-light" title="Ver detalles">
                                            <i class="ri-eye-line"></i>
                                        </a>
                                        @if(in_array($order->status, ['descargado', 'generated']))
                                            <form action="{{route('sepa-payments.mark-ready', $order->id)}}" method="POST" class="d-inline" onsubmit="return confirm('¿Marcar como Listo (pago realizado)?');">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-success" title="Marcar como Listo (pago realizado)">
                                                    <i class="ri-check-line"></i>
                                                </button>
                                            </form>
                                        @endif
	                                	<form action="{{route('sepa-payments.destroy', $order->id)}}" method="POST" class="d-inline" onsubmit="return confirm('¿Está seguro de eliminar esta orden de pago?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger" title="Eliminar">
                                                <i class="ri-delete-bin-6-line"></i>
                                            </button>
                                        </form>
	                                </td>
	                            </tr>
                                @endforeach
	                        </tbody>
	                    </table>

                    </div>

                    <div class="{{count($orders) ? 'd-none' : ''}}">
                        
                        <div class="d-flex align-items-center gap-1">
                        	
                        	<div class="empty-tables">

                        		<div>
                        			<i class="ri-file-transfer-line" style="font-size: 80px; color: #ccc;"></i>
                        		</div>

                        		<h3 class="mb-0">No hay Órdenes de Pago SEPA</h3>

                        		<small>Crea tu primera orden de pago</small>

                        		<br>

                        		<a href="{{route('sepa-payments.create')}}" style="border-radius: 30px; width: 200px;" class="btn btn-md btn-dark mt-2">
                                    <i style="position: relative; top: 2px;" class="ri-add-line"></i> Nueva Orden de Pago
                                </a>
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
      "scrollX": true, 
      "scrollCollapse": true,
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
                            var regexr = '({search})';

                            var cursorPosition = this.selectionStart;
                            // Search the column for that value

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

</script>

@include('partials.sepa-confirm-debtor-modal')
@endsection











