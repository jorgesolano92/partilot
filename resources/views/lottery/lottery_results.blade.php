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
                <h4 class="page-title">Sorteos</h4>
            </div>
        </div>
    </div>

    @if(!session('selected_administration'))
        <div class="row">
            <div class="col-12">
                <div class="alert alert-warning" role="alert">
                    <i class="ri-alert-line me-2"></i>
                    <strong>Advertencia:</strong> No se ha seleccionado ninguna administración. 
                    <a href="{{ route('lottery.administrations') }}" class="alert-link">Volver a seleccionar administración</a>
                </div>
            </div>
        </div>
    @endif

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

                    <div>
                    	<div class="form-group mt-2 mb-3 admin-box bs">

            				<div class="row">
            					<div class="col-1">
            						
                    				<div class="photo-preview-2">
                    					
                    					<i class="ri-account-circle-fill"></i>

                    				</div>
                    				
                    				<div style="clear: both;"></div>
            					</div>

            					<div class="col-4 text-center">

            						<h4 class="mt-0 mb-0">{{ session('selected_administration.name', 'Administración no seleccionada') }}</h4>

            						<small>{{ session('selected_administration')?->manager?->resolvedContactFullName() ?: 'Gestor no asignado' }}</small> <br>

            						<i style="position: relative; top: 3px; font-size: 16px; color: #333" class="ri-computer-line"></i> {{ session('selected_administration.receiving', 'N/A') }}
            						
            					</div>

            					<div class="col-4">

            						<div class="mt-2">
            							Provincia: {{ session('selected_administration.province', 'N/A') }} <br>
            							Dirección: {{ session('selected_administration.address', 'N/A') }}
            						</div>
            						
            					</div>

            					<div class="col-3">

            						<div class="mt-2">
            							Ciudad: {{ session('selected_administration.city', 'N/A') }} <br>
            							Tel: {{ session('selected_administration.phone', 'N/A') }}
            						</div>
            						
            					</div>
            				</div>
            			</div>
                        <h4 class="header-title">

                            <div class="float-start d-flex align-items-start">
                                <input type="text" class="form-control" style="margin-right: 8px ;" placeholder="Número">
                                <input type="text" class="form-control" style="margin-right: 8px ;" placeholder="Tipo de Sorteo">
                                <input type="text" class="form-control" placeholder="Escrutado">
                            </div>

                            <a href="{{url('lottery/add')}}" style="border-radius: 30px; width: 150px;" class="btn btn-md btn-dark float-end"><i style="position: relative; top: 2px;" class="ri-add-line"></i> Añadir</a>

                        </h4>

                        <div style="clear: both;"></div>

                        <br>

                        <table id="example2" class="table table-striped nowrap w-100">
                            <thead class="filters">
                                <tr>
                                    <th>Número</th>
                                    <th>Fecha Sorteo</th>
                                    <th>1º</th>
                                    <th>2º</th>
                                    <th>3º</th>
                                    <th>Fracción</th>
                                    <th>Serie</th>
                                    <th>Reintegro</th>
                                    <th>Escrutado</th>
                                    <th>Tipo de Sorteo</th>
                                    <th class="no-filter"></th>
                                </tr>
                            </thead>
                        
                        
                            <tbody>
                                @forelse($lotteries as $lottery)
                                <tr>
                                    <td>{{ $lottery->name }}</td>
                                    <td>{{ $lottery->draw_date ? $lottery->draw_date->format('d/m/Y') : 'N/A' }}</td>
                                    <td>
                                        @if($lottery->result && $lottery->result->primer_premio)
                                            @if(is_array($lottery->result->primer_premio))
                                                {{ $lottery->result->primer_premio['decimo'] ?? 'N/A' }}
                                            @else
                                                -
                                            @endif
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        @if($lottery->result && $lottery->result->segundo_premio)
                                            @if(is_array($lottery->result->segundo_premio))
                                                {{ $lottery->result->segundo_premio['decimo'] ?? 'N/A' }}
                                            @else
                                                -
                                            @endif
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        @if($lottery->result && $lottery->result->terceros_premios && count($lottery->result->terceros_premios) > 0)
                                            @foreach($lottery->result->terceros_premios as $premio)
                                                {{ $premio['decimo'] ?? 'N/A' }}
                                            @endforeach
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        @if($lottery->result && $lottery->result->premio_especial)
                                            {{ $lottery->result->premio_especial['fraccion'] ?? '-' }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        @if($lottery->result && $lottery->result->premio_especial)
                                            {{ $lottery->result->premio_especial['serie'] ?? '-' }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        @if($lottery->result && $lottery->result->refunds && count($lottery->result->refunds) > 0)
                                            {{implode('-',$lottery->result->refunds)}}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $isScrutinized = false;
                                            if (session('selected_administration.id')) {
                                                $scrutiny = \App\Models\AdministrationLotteryScrutiny::where('administration_id', session('selected_administration.id'))
                                                    ->where('lottery_id', $lottery->id)
                                                    ->where('is_scrutinized', true)
                                                    ->first();
                                                $isScrutinized = !is_null($scrutiny);
                                            }
                                        @endphp
                                        @if($isScrutinized)
                                            <label class="badge bg-success">SI</label>
                                        @else
                                            <label class="badge bg-danger">NO</label>
                                        @endif
                                    </td>
                                    <td>{{ $lottery->lotteryType->name ?? 'Sin tipo' }}</td>
                                    <td class="text-end">
                                        @php
                                            $isScrutinized = false;
                                            if (session('selected_administration.id')) {
                                                $scrutiny = \App\Models\AdministrationLotteryScrutiny::where('administration_id', session('selected_administration.id'))
                                                    ->where('lottery_id', $lottery->id)
                                                    ->where('is_scrutinized', true)
                                                    ->first();
                                                $isScrutinized = !is_null($scrutiny);
                                            }
                                        @endphp
                                        @if($isScrutinized)
                                            <a href="{{ route('lottery.show-administration-scrutiny', [$lottery->id, session('selected_administration.id')]) }}" class="btn btn-sm btn-light" title="Ver Escrutinio"><img src="{{url('assets/form-groups/results.svg')}}" alt="" width="12"></a>
                                        @elseif($lottery->result && session('selected_administration.id'))
                                            <a href="{{url('lottery/scrutiny', $lottery->id)}}" class="btn btn-sm btn-light" title="Realizar Escrutinio"><img src="{{url('assets/form-groups/escrutinio.svg')}}" alt="" width="12"></a>
                                        @else
                                            <span class="btn btn-sm btn-light disabled" title="Sorteo sin resultados aún"><img src="{{url('assets/form-groups/escrutinio.svg')}}" alt="" width="12" style="opacity: 0.5;"></span>
                                        @endif
                                        <a href="{{url('lottery/results/edit', $lottery->id)}}" class="btn btn-sm btn-light" title="Editar"><img src="{{url('assets/form-groups/edit.svg')}}" alt="" width="12"></a>
                                        <button class="btn btn-sm btn-danger delete-btn" data-id="{{$lottery->id}}" data-name="{{$lottery->name}}" title="Eliminar"><i class="ri-delete-bin-6-line"></i></button>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="11" class="text-center">
                                        <div class="alert alert-info">
                                            <i class="ri-information-line me-2"></i>
                                            No hay sorteos disponibles
                                        </div>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>

                        <br>

                        <div class="d-flex flex-wrap align-items-center gap-2 lottery-list-actions">
                        <a href="{{url('lottery_types?table=1')}}" style="border-radius: 30px; width: 180px; background-color: #1f2430;" class="btn btn-md btn-dark">
                            <img src="{{url('icons_/tipos_sorteos.svg')}}" alt="" width="18px" style="position: relative; top: -1px;">
                         Tipos de Sorteo</a>

                         <a href="{{url('lottery/administrations')}}" style="border-radius: 30px; width: 180px; background-color: #e78307;" class="btn btn-md btn-light">
                            <img src="{{url('assets/form-groups/results.svg')}}" alt="" width="18px" style="position: relative; top: -1px;">
                         Lista Resultados</a>
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

  // Eliminar sorteo
  $('.delete-btn').on('click', function() {
    const id = $(this).data('id');
    const name = $(this).data('name');
    
    if (confirm('¿Estás seguro de que quieres eliminar el sorteo "' + name + '"?')) {
      window.location.href = '{{url("lottery/delete")}}/' + id;
    }
  });

</script>

@endsection