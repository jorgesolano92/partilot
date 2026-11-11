@extends('layouts.layout')

@section('title','Editar Resultados del Sorteo')

@section('content')

<style>
	input:disabled, textarea:disabled {
		background-color: #e0e0e0 !important;
	}
	
	.edit-mode {
		background-color: #fff !important;
		border: 2px solid #007bff !important;
	}
	
	.save-btn {
		display: none;
	}
	
	.edit-mode .save-btn {
		display: inline-block;
	}
	
	.loading {
		opacity: 0.6;
		pointer-events: none;
	}
	
	.alert {
		border-radius: 10px;
		margin-bottom: 20px;
	}
</style>

<!-- Start Content-->
<div class="container-fluid">
    
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
            	<div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('lotteries.index') }}">Sorteos</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('lottery.results') }}">Resultados</a></li>
                        <li class="breadcrumb-item active">Editar</li>
                    </ol>
                </div>
                <h4 class="page-title">Editar Resultados del Sorteo</h4>
            </div>
        </div>
    </div>     

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

                	<h4 class="header-title">
                    	Edición Resultados - {{ $lottery->name }}
                    </h4>

                    <br>

                    <!-- Alertas de estado -->
                    <div id="alertContainer"></div>

                    @if(!empty($isNavidad))
                        <div class="row mb-3">
                            <div class="col-12">
                                @if(($pedreasCount ?? 0) > 0)
                                    <div class="alert alert-info mb-2" id="pedreasStatusAlert">
                                        <i class="ri-information-line me-1"></i>
                                        Pedreas cargadas: <strong>{{ $pedreasCount }}</strong>.
                                        Si al reobtener resultados SELAE bloquea el scrape, se conservarán estas pedreas.
                                        Fuente oficial:
                                        <a href="{{ $tablasAlambresUrl }}" target="_blank" rel="noopener">Tablas y alambres</a>
                                        (la API JSON de resultados no incluye pedreas).
                                    </div>
                                @else
                                    <div class="alert alert-warning mb-2" id="pedreasStatusAlert">
                                        <i class="ri-alert-line me-1"></i>
                                        Este sorteo de Navidad <strong>no tiene pedreas</strong> guardadas.
                                        Hay que obtenerlas desde
                                        <a href="{{ $tablasAlambresUrl }}" target="_blank" rel="noopener">tablas y alambres SELAE</a>
                                        (no vienen en la API de resultados). Sin pedreas el escrutinio no las liquidará.
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    <!-- Botón para obtener resultados desde API -->
                    <div class="row mb-3">
                        <div class="col-12">
                            <button type="button" id="fetchResultsBtn" class="btn btn-success" style="border-radius: 30px; padding: 10px 20px;">
                                <i class="ri-download-line me-2"></i>
                                Obtener Resultados desde API
                            </button>
                            <button type="button" id="toggleEditBtn" class="btn btn-primary ms-2" style="border-radius: 30px; padding: 10px 20px;">
                                <i class="ri-edit-line me-2"></i>
                                Modo Edición
                            </button>
                            <button type="button" id="saveAllBtn" class="btn btn-warning ms-2 save-btn" style="border-radius: 30px; padding: 10px 20px;">
                                <i class="ri-save-line me-2"></i>
                                Guardar Cambios
                            </button>
                        </div>
                    </div>

                    <div class="row">
                    	
                    	<div class="col-md-12">
                    		<div class="form-card bs" style="min-height: 658px;">
                    			<h4 class="mb-0 mt-1">
                    				Datos del Sorteo
                    			</h4>
                    			<small><i>Revisa que los datos del sorteo sean los correctos</i></small>

                    			<div class="form-group mt-2 mb-3">

                                    <div class="row show-content">

                                    	<div class="col-2">

                                    		<div style="width: calc(100% - 20px); height: 80px; border-radius: 8px; background-color: silver; float: left; margin-right: 20px; background-image: url({{ $lottery->image ? url('uploads/' . $lottery->image) : '' }}); background-size: cover; background-position: center;">
                                            </div>
                                    		
                                    	</div>

                                    	<div class="col-2">
                    						<div class="form-group mt-2 mb-3">
                    							<label class="label-control">Número de Sorteo</label>

				                    			<div class="input-group input-group-merge group-form">

				                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
				                                        <img src="{{url('assets/form-groups/admin/16.svg')}}" alt="">
				                                    </div>

				                                    <input class="form-control" value="{{ $lottery->name }}" type="text" readonly style="border-radius: 0 30px 30px 0;">
				                                </div>
			                    			</div>
                    					</div>

                    					<div class="col-4">
                    						<div class="form-group mt-2 mb-3">
                    							<label class="label-control">Nombre del Sorteo</label>

				                    			<div class="input-group input-group-merge group-form">

				                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
				                                        <img src="{{url('assets/form-groups/admin/17.svg')}}" alt="">
				                                    </div>

				                                    <input class="form-control" value="{{ $lottery->description }}" type="text" readonly style="border-radius: 0 30px 30px 0;">
				                                </div>
			                    			</div>
                    					</div>

                                    </div>

                                    <div class="row">
                                    	
                                    	<div class="col-4">
	                                        <div class="form-group mt-2 mb-3">
	                                            <label class="label-control">Tipo de Sorteo</label>

	                                            <div class="input-group input-group-merge group-form">

	                                                <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
	                                                    <img src="{{url('assets/form-groups/admin/14.svg')}}" alt="">
	                                                </div>

	                                                <input class="form-control" value="{{ $lottery->lotteryType->name ?? 'N/A' }}" type="text" readonly style="border-radius: 0 30px 30px 0;">
	                                            </div>
	                                        </div>
	                                    </div>

	                                    <div class="col-2">
	                                        <div class="form-group mt-2 mb-3">
	                                            <label class="label-control">Precio décimo</label>

	                                            <div class="input-group input-group-merge group-form">

	                                                <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
	                                                    <img src="{{url('assets/form-groups/admin/15.svg')}}" alt="">
	                                                </div>

	                                                <input class="form-control" value="{{ $lottery->ticket_price }}" type="text" readonly style="border-radius: 0 30px 30px 0;">
	                                            </div>
	                                        </div>
	                                    </div>

	                                    <div class="col-2">
	                                        <div class="form-group mt-2 mb-3">
	                                            <label class="label-control">Fecha Sorteo</label>

	                                            <div class="input-group input-group-merge group-form">

	                                                <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
	                                                    <img src="{{url('assets/form-groups/admin/12.svg')}}" alt="">
	                                                </div>

	                                                <input class="form-control" value="{{ $lottery->draw_date->format('Y-m-d') }}" type="text" readonly style="border-radius: 0 30px 30px 0;">
	                                            </div>
	                                        </div>
	                                    </div>

                                    </div>
                    				
                    			</div>

                                <h4 class="mb-0 mt-1">
                                    Resultados del Sorteo
                                </h4>
                                <small><i>Dejar en blanco los datos que no proceden para este tipo de sorteo. <br> Para extracciones múltiples separar cada número por un guión</i></small>

                                <div id="resultsStatus">
                                    @if($lottery->result)
                                        <!-- Mostrar resultados existentes -->
                                        <div class="row mt-3">
                                            <div class="col-12">
                                                <div class="alert alert-success">
                                                    <i class="ri-check-line me-2"></i>
                                                    <strong>Resultados disponibles</strong> - Fecha: {{ $lottery->result->results_date ? $lottery->result->results_date->format('d/m/Y H:i:s') : 'No disponible' }}
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        <div class="row mt-3">
                                            <div class="col-12">
                                                <div class="alert alert-info">
                                                    <i class="ri-information-line me-2"></i>
                                                    No hay resultados disponibles para este sorteo. Utiliza el botón de arriba para obtener los resultados desde la API.
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                <div id="resultsForm" style="/*min-height: 400px; height: 400px; overflow-y: unset; overflow-x: scroll;*/">

                                    <!-- Premio Especial -->
                                    <div class="row">
                                    	<div class="col-2">
	                                        <div class="form-group mt-2 mb-2">
	                                            <label class="label-control">Premio Especial</label>
	                                            <div class="input-group input-group-merge group-form">
	                                                <input class="form-control result-field" type="text" name="premio_especial" value="{{ $lottery->result && $lottery->result->premio_especial ? (is_array($lottery->result->premio_especial) ? $lottery->result->premio_especial['numero'] : $lottery->result->premio_especial) : '' }}" readonly style="border-radius: 30px;">
	                                            </div>
	                                        </div>
	                                    </div>
                                    </div>

                                    <!-- Primer Premio -->
                                    <div class="row">
                                    	<div class="col-2">
	                                        <div class="form-group mt-2 mb-2">
	                                            <label class="label-control">Primer Premio</label>
	                                            <div class="input-group input-group-merge group-form">
	                                                <input class="form-control result-field" type="text" name="primer_premio" value="{{ $lottery->result && $lottery->result->primer_premio ? (is_array($lottery->result->primer_premio) ? $lottery->result->primer_premio['decimo'] : $lottery->result->primer_premio) : '' }}" readonly style="border-radius: 30px;">
	                                            </div>
	                                        </div>
	                                    </div>

	                                    <div class="col-1">
	                                        <div class="form-group mt-2 mb-2">
	                                            <label class="label-control">Serie</label>
	                                            <div class="input-group input-group-merge group-form">
	                                                <input class="form-control result-field" type="text" name="primer_premio_serie" value="{{ $lottery->result && $lottery->result->primer_premio && is_array($lottery->result->primer_premio) ? ($lottery->result->primer_premio['serie'] ?? '') : '' }}" readonly style="border-radius: 30px;">
	                                            </div>
	                                        </div>
	                                    </div>

	                                    <div class="col-1">
	                                        <div class="form-group mt-2 mb-2">
	                                            <label class="label-control">Fracción</label>
	                                            <div class="input-group input-group-merge group-form">
	                                                <input class="form-control result-field" type="text" name="primer_premio_fraccion" value="{{ $lottery->result && $lottery->result->primer_premio && is_array($lottery->result->primer_premio) ? ($lottery->result->primer_premio['fraccion'] ?? '') : '' }}" readonly style="border-radius: 30px;">
	                                            </div>
	                                        </div>
	                                    </div>

	                                    <div class="col-1">
	                                        <div class="form-group mt-2 mb-2">
	                                            <label class="label-control">Reintegros</label>
	                                            <div class="input-group input-group-merge group-form">
	                                                <input class="form-control result-field" type="text" name="reintegros" value="{{ $lottery->result && $lottery->result->refunds ? implode('-', $lottery->result->refunds) : '' }}" readonly style="border-radius: 30px;">
	                                            </div>
	                                        </div>
	                                    </div>

	                                    <div class="col-4">
	                                        <div class="form-group mt-2 mb-2">
	                                            <label class="label-control">&nbsp;</label> <br>
	                                            <small style="line-height: 1.5; display: block;"><i>Varias extracciones <br> de 1 cifra</i></small>
	                                        </div>
	                                    </div>
                                    </div>

                                    <!-- Segundo Premio -->
                                    <div class="row">
                                    	<div class="col-2">
	                                        <div class="form-group mt-1 mb-2">
	                                            <label class="label-control">Segundo Premio</label>
	                                            <div class="input-group input-group-merge group-form">
	                                                <input class="form-control result-field" type="text" name="segundo_premio" value="{{ $lottery->result && $lottery->result->segundo_premio ? (is_array($lottery->result->segundo_premio) ? $lottery->result->segundo_premio['decimo'] : $lottery->result->segundo_premio) : '' }}" readonly style="border-radius: 30px;">
	                                            </div>
	                                        </div>
	                                    </div>

	                                    <div class="col-4">
	                                        <div class="form-group mt-1 mb-2">
	                                            <label class="label-control">&nbsp;</label> <br>
	                                            <small style="line-height: 1.5; display: block;"><i>Una Extracción <br> de 5 cifras</i></small>
	                                        </div>
	                                    </div>
	                                </div>

	                                <!-- Terceros Premios -->
	                                <div class="row">
                                    	<div class="col-2">
	                                        <div class="form-group mt-1 mb-2">
	                                            <label class="label-control">Terceros Premios</label>
	                                            <div class="input-group input-group-merge group-form">
	                                                <input class="form-control result-field" type="text" name="terceros_premios" value="{{ $lottery->result && $lottery->result->thirds ? implode('-', $lottery->result->thirds) : '' }}" readonly style="border-radius: 30px;">
	                                            </div>
	                                        </div>
	                                    </div>

	                                    <div class="col-4">
	                                        <div class="form-group mt-1 mb-2">
	                                            <label class="label-control">&nbsp;</label> <br>
	                                            <small style="line-height: 1.5; display: block;"><i>Varias Extracciones <br> de 5 cifras</i></small>
	                                        </div>
	                                    </div>
	                                </div>

	                                <!-- Cuartos Premios -->
	                                <div class="row">
                                    	<div class="col-3">
	                                        <div class="form-group mt-1 mb-2">
	                                            <label class="label-control">Cuartos Premios</label>
	                                            <div class="input-group input-group-merge group-form">
	                                                <input class="form-control result-field" type="text" name="cuartos_premios" value="{{ $lottery->result && $lottery->result->fourths ? implode('-', $lottery->result->fourths) : '' }}" readonly style="border-radius: 30px;">
	                                            </div>
	                                        </div>
	                                    </div>

	                                    <div class="col-4">
	                                        <div class="form-group mt-1 mb-2">
	                                            <label class="label-control">&nbsp;</label> <br>
	                                            <small style="line-height: 1.5; display: block;"><i>Varias Extracciones <br> de 5 cifras</i></small>
	                                        </div>
	                                    </div>
	                                </div>

	                                <!-- Quintos Premios -->
	                                <div class="row">
                                    	<div class="col-6">
	                                        <div class="form-group mt-1 mb-2">
	                                            <label class="label-control">Quintos Premios</label>
	                                            <div class="input-group input-group-merge group-form">
	                                                <input class="form-control result-field" type="text" name="quintos_premios" value="{{ $lottery->result && $lottery->result->fifths ? implode('-', $lottery->result->fifths) : '' }}" readonly style="border-radius: 30px;">
	                                            </div>
	                                        </div>
	                                    </div>

	                                    <div class="col-4">
	                                        <div class="form-group mt-1 mb-2">
	                                            <label class="label-control">&nbsp;</label> <br>
	                                            <small style="line-height: 1.5; display: block;"><i>Varias Extracciones <br> de 5 cifras</i></small>
	                                        </div>
	                                    </div>
	                                </div>

	                                <!-- Extracciones 5 Cifras -->
	                                <div class="row">
                                    	<div class="col-6">
	                                        <div class="form-group mt-1 mb-2">
	                                            <label class="label-control">Extracciones 5 Cifras</label>
	                                            <div class="input-group input-group-merge group-form">
	                                                <input class="form-control result-field" type="text" name="extracciones_5_cifras" value="{{ $lottery->result && $lottery->result['5figures'] ? implode('-',$lottery->result['5figures']) : '' }}" readonly style="border-radius: 30px;">
	                                            </div>
	                                        </div>
	                                    </div>

	                                    <div class="col-4">
	                                        <div class="form-group mt-1 mb-2">
	                                            <label class="label-control">&nbsp;</label> <br>
	                                            <small style="line-height: 1.5; display: block;"><i>Varias Extracciones <br> de 5 cifras</i></small>
	                                        </div>
	                                    </div>

	                                    <div class="col-12">
	                                    	<small><i>Solo en los casos en el que existen <b>VARIOS 3º/4º Premios</b> como en los sorteos del NIÑO, S.ILDEFONSO o VACACIONES</i></small>
	                                    </div>
	                                </div>

	                                <!-- Extracciones 4 Cifras -->
	                                <div class="row">
                                    	<div class="col-3">
	                                        <div class="form-group mt-1 mb-2">
	                                            <label class="label-control">Extracciones 4 Cifras</label>
	                                            <div class="input-group input-group-merge group-form">
	                                                <input class="form-control result-field" type="text" name="extracciones_4_cifras" value="{{ $lottery->result && $lottery->result['4figures'] ? implode('-',$lottery->result['4figures']) : '' }}" readonly style="border-radius: 30px;">
	                                            </div>
	                                        </div>
	                                    </div>

	                                    <div class="col-4">
	                                        <div class="form-group mt-1 mb-2">
	                                            <label class="label-control">&nbsp;</label> <br>
	                                            <small style="line-height: 1.5; display: block;"><i>Varias Extracciones <br> de 4 cifras</i></small>
	                                        </div>
	                                    </div>
	                                </div>

	                                <!-- Extracciones 3 Cifras -->
	                                <div class="row">
                                    	<div class="col-6">
	                                        <div class="form-group mt-1 mb-2">
	                                            <label class="label-control">Extracciones 3 Cifras</label>
	                                            <div class="input-group input-group-merge group-form">
	                                                <input class="form-control result-field" type="text" name="extracciones_3_cifras" value="{{ $lottery->result && $lottery->result['3figures'] ? implode('-',$lottery->result['3figures']) : '' }}" readonly style="border-radius: 30px;">
	                                            </div>
	                                        </div>
	                                    </div>

	                                    <div class="col-4">
	                                        <div class="form-group mt-1 mb-2">
	                                            <label class="label-control">&nbsp;</label> <br>
	                                            <small style="line-height: 1.5; display: block;"><i>Varias Extracciones <br> de 3 cifras</i></small>
	                                        </div>
	                                    </div>
	                                </div>

	                                <!-- Extracciones 2 Cifras -->
	                                <div class="row">
                                    	<div class="col-3">
	                                        <div class="form-group mt-1 mb-2">
	                                            <label class="label-control">Extracciones 2 Cifras</label>
	                                            <div class="input-group input-group-merge group-form">
	                                                <input class="form-control result-field" type="text" name="extracciones_2_cifras" value="{{ $lottery->result && $lottery->result['2figures'] ? implode('-',$lottery->result['2figures']) : '' }}" readonly style="border-radius: 30px;">
	                                            </div>
	                                        </div>
	                                    </div>

	                                    <div class="col-4">
	                                        <div class="form-group mt-1 mb-2">
	                                            <label class="label-control">&nbsp;</label> <br>
	                                            <small style="line-height: 1.5; display: block;"><i>Varias Extracciones <br> de 2 cifras</i></small>
	                                        </div>
	                                    </div>
	                                </div>

	                                <!-- Pedrea -->
	                                <div class="row">
                                    	<div class="col-12">
	                                        <div class="form-group mt-1 mb-2">
	                                            <label class="label-control">Pedrea</label>
	                                            <div class="input-group input-group-merge group-form">
	                                            	<textarea class="form-control result-field" name="pedrea" readonly="" style="border-radius: 30px;" rows="6">{{ $lottery->result && $lottery->result['pedreas'] ? implode('-', array_column($lottery->result['pedreas'], 'decimo')) : '' }}</textarea>
	                                            </div>
	                                        </div>
	                                    </div>
	                                </div>

                                </div>

                                <div class="row">

                                    <div class="col-6 text-start">
                                        <a href="{{ route('lottery.results') }}" style="border-radius: 30px; width: 200px; background-color: #333; color: #fff; padding: 8px; font-weight: bolder; position: relative;" class="btn btn-md btn-light mt-2">
                                            <i style="top: 6px; left: 32%; font-size: 18px; position: absolute;" class="ri-arrow-left-circle-line"></i> <span style="display: block; margin-left: 16px;">Atrás</span></a>
                                    </div>
                                    
                                    <div class="col-6 text-end">
                                        <a href="{{ route('lottery.show-results', $lottery->id) }}" style="border-radius: 30px; width: 200px; background-color: #e78307; color: #333; padding: 8px; font-weight: bolder; position: relative;" class="btn btn-md btn-light mt-2">Ver Resultados
                                            <i style="top: 6px; margin-left: 6px; font-size: 18px; position: absolute;" class="ri-eye-line"></i></a>
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
$(document).ready(function() {
    let isEditMode = false;
    
    // Función para mostrar alertas
    function showAlert(message, type = 'success', append = false) {
        const icon = type === 'success' ? 'check' : (type === 'danger' || type === 'error' ? 'close' : (type === 'warning' ? 'alert' : 'information'));
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                <i class="ri-${icon}-line me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        if (append) {
            $('#alertContainer').append(alertHtml);
        } else {
            $('#alertContainer').html(alertHtml);
        }
    }
    
    // Función para actualizar el estado de los resultados
    function updateResultsStatus(hasResults, resultsDate = null) {
        let statusHtml = '';
        if (hasResults) {
            statusHtml = `
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="alert alert-success">
                            <i class="ri-check-line me-2"></i>
                            <strong>Resultados disponibles</strong> - Fecha: ${resultsDate || 'No disponible'}
                        </div>
                    </div>
                </div>
            `;
        } else {
            statusHtml = `
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="alert alert-info">
                            <i class="ri-information-line me-2"></i>
                            No hay resultados disponibles para este sorteo. Utiliza el botón de arriba para obtener los resultados desde la API.
                        </div>
                    </div>
                </div>
            `;
        }
        $('#resultsStatus').html(statusHtml);
    }
    
    // Función para actualizar los campos con nuevos datos
    function updateResultFields(data) {
    	console.log(data.primerPremio)
        // Actualizar campos con los nuevos datos
        $('input[name="premio_especial"]').val((data.premioEspecial) ? data.premioEspecial.numero : '');
        $('input[name="primer_premio"]').val((data.primerPremio) ? data.primerPremio.decimo : '');
        $('input[name="primer_premio_serie"]').val((data.primerPremio) ? (data.primerPremio.serie || '') : '');
        $('input[name="primer_premio_fraccion"]').val((data.primerPremio) ? (data.primerPremio.fraccion || '') : '');
        $('input[name="segundo_premio"]').val((data.segundoPremio) ? data.segundoPremio.decimo : '');
        
        // Procesar arrays de números
        $('input[name="reintegros"]').val(data.reintegros ? data.reintegros.map(item => item.decimo || item).join('-') : '');
        $('input[name="terceros_premios"]').val(data.tercerosPremios ? data.tercerosPremios.map(item => item.decimo || item).join('-') : '');
        $('input[name="cuartos_premios"]').val(data.cuartosPremios ? data.cuartosPremios.map(item => item.decimo || item).join('-') : '');
        $('input[name="quintos_premios"]').val(data.quintosPremios ? data.quintosPremios.map(item => item.decimo || item).join('-') : '');
        $('input[name="extracciones_5_cifras"]').val(data.extraccionesDeCincoCifras ? data.extraccionesDeCincoCifras.map(item => item.decimo || item).join('-') : '');
        $('input[name="extracciones_4_cifras"]').val(data.extraccionesDeCuatroCifras ? data.extraccionesDeCuatroCifras.map(item => item.decimo || item).join('-') : '');
        $('input[name="extracciones_3_cifras"]').val(data.extraccionesDeTresCifras ? data.extraccionesDeTresCifras.map(item => item.decimo || item).join('-') : '');
        $('input[name="extracciones_2_cifras"]').val(data.extraccionesDeDosCifras ? data.extraccionesDeDosCifras.map(item => item.decimo || item).join('-') : '');
        
        // Pedreas: solo actualizar si vienen datos; no vaciar si el scrape falló
        if (data.pedreas && Array.isArray(data.pedreas) && data.pedreas.length > 0) {
            const pedreasText = data.pedreas.map(item => item.decimo || item).join('-');
            $('textarea[name="pedrea"]').val(pedreasText);
            if ($('#pedreasStatusAlert').length) {
                $('#pedreasStatusAlert')
                    .removeClass('alert-warning')
                    .addClass('alert-info')
                    .html('<i class="ri-information-line me-1"></i> Pedreas cargadas: <strong>' + data.pedreas.length + '</strong>.');
            }
        }
    }
    
    // Toggle modo edición
    $('#toggleEditBtn').click(function() {
        isEditMode = !isEditMode;
        
        if (isEditMode) {
            $('.result-field').prop('readonly', false).addClass('edit-mode');
            $(this).html('<i class="ri-eye-line me-2"></i>Modo Visualización');
            $(this).removeClass('btn-primary').addClass('btn-secondary');
            $('.save-btn').show();
        } else {
            $('.result-field').prop('readonly', true).removeClass('edit-mode');
            $(this).html('<i class="ri-edit-line me-2"></i>Modo Edición');
            $(this).removeClass('btn-secondary').addClass('btn-primary');
            $('.save-btn').hide();
        }
    });
    
    // Guardar cambios
    $('#saveAllBtn').click(function() {
        const button = this;
        const originalText = button.innerHTML;
        
        // Cambiar estado del botón
        button.disabled = true;
        button.innerHTML = '<i class="ri-loader-4-line me-2"></i>Guardando...';
        
        // Recopilar datos del formulario
        const formData = {
            lottery_id: {{ $lottery->id }},
            premio_especial: $('input[name="premio_especial"]').val(),
            primer_premio: $('input[name="primer_premio"]').val(),
            primer_premio_serie: $('input[name="primer_premio_serie"]').val(),
            primer_premio_fraccion: $('input[name="primer_premio_fraccion"]').val(),
            segundo_premio: $('input[name="segundo_premio"]').val(),
            reintegros: $('input[name="reintegros"]').val(),
            terceros_premios: $('input[name="terceros_premios"]').val(),
            cuartos_premios: $('input[name="cuartos_premios"]').val(),
            quintos_premios: $('input[name="quintos_premios"]').val(),
            extracciones_5_cifras: $('input[name="extracciones_5_cifras"]').val(),
            extracciones_4_cifras: $('input[name="extracciones_4_cifras"]').val(),
            extracciones_3_cifras: $('input[name="extracciones_3_cifras"]').val(),
            extracciones_2_cifras: $('input[name="extracciones_2_cifras"]').val(),
            pedrea: $('textarea[name="pedrea"]').val()
        };
        
        // Realizar petición AJAX para guardar
        $.ajax({
            url: '{{ route("lottery.save-results") }}',
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json'
            },
            data: JSON.stringify(formData),
            success: function(response) {
                if (response.success) {
                    showAlert('¡Resultados guardados exitosamente!', 'success');
                    updateResultsStatus(true, response.results_date);
                } else {
                    showAlert('Error: ' + response.message, 'danger');
                }
            },
            error: function(xhr) {
                let errorMessage = 'Error al guardar los resultados';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                showAlert(errorMessage, 'danger');
            },
            complete: function() {
                // Restaurar estado del botón
                button.disabled = false;
                button.innerHTML = originalText;
            }
        });
    });
    
    // Obtener resultados desde API (sin recargar página)
    $('#fetchResultsBtn').click(function() {
        const button = this;
        const originalText = button.innerHTML;
        
        // Cambiar estado del botón
        button.disabled = true;
        button.innerHTML = '<i class="ri-loader-4-line me-2"></i>Obteniendo resultados...';
        
        // Agregar clase loading al contenedor
        $('#resultsForm').addClass('loading');
        
        // Realizar petición AJAX
        $.ajax({
            url: '{{ route("lottery.fetch-specific-results") }}',
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json'
            },
            data: JSON.stringify({
                lottery_id: {{ $lottery->id }},
                api_url: '{{ $apiUrl }}'
            }),
            success: function(response) {
                if (response.success) {
                    const pedreasMeta = response.pedreas_meta || {};
                    const alertType = pedreasMeta.ok === false ? 'warning' : 'success';
                    showAlert((pedreasMeta.ok === false ? 'Atención: ' : '¡Éxito! ') + response.message, alertType);

                    // Actualizar los campos con los nuevos datos
                    if (response.data) {
                        updateResultFields(response.data);
                    }

                    if (pedreasMeta.ok === false) {
                        const sources = pedreasMeta.sources || {};
                        let extra = '';
                        if (sources.tablas_url) {
                            extra += ' <a href="' + sources.tablas_url + '" target="_blank" rel="noopener">Abrir tablas y alambres</a>';
                        }
                        if (sources.listado_oficial_url) {
                            extra += ' · <a href="' + sources.listado_oficial_url + '" target="_blank" rel="noopener">Listado oficial PDF</a>';
                        }
                        if (extra) {
                            showAlert('Dónde obtener las pedreas:' + extra, 'info', true);
                        }
                        if ($('#pedreasStatusAlert').length && pedreasMeta.kept_existing) {
                            $('#pedreasStatusAlert')
                                .removeClass('alert-info')
                                .addClass('alert-warning')
                                .html('<i class="ri-alert-line me-1"></i> No se pudieron reobtener pedreas; se han <strong>conservado las ' + (pedreasMeta.count || 0) + ' ya guardadas</strong>.');
                        }
                    } else if (typeof pedreasMeta.count === 'number' && pedreasMeta.count > 0 && $('#pedreasStatusAlert').length) {
                        $('#pedreasStatusAlert')
                            .removeClass('alert-warning')
                            .addClass('alert-info')
                            .html('<i class="ri-information-line me-1"></i> Pedreas cargadas: <strong>' + pedreasMeta.count + '</strong>.');
                    }

                    // Actualizar el estado de los resultados
                    updateResultsStatus(true, new Date().toLocaleString('es-ES'));

                } else {
                    showAlert('Error: ' + response.message, 'danger');
                }
            },
            error: function(xhr) {
                let errorMessage = 'Ocurrió un error al obtener los resultados';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                showAlert(errorMessage, 'danger');
            },
            complete: function() {
                // Restaurar estado del botón
                button.disabled = false;
                button.innerHTML = originalText;
                
                // Remover clase loading
                $('#resultsForm').removeClass('loading');
            }
        });
    });
});
</script>

@endsection