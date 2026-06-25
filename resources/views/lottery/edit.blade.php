@extends('layouts.layout')

@section('title','Editar Sorteo')

@section('content')

<!-- Start Content-->
<div class="container-fluid">
    
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('lotteries.index') }}">Sorteos</a></li>
                        <li class="breadcrumb-item active">Editar Sorteo</li>
                    </ol>
                </div>
                <h4 class="page-title">Editar Sorteo</h4>
            </div>
        </div>
    </div>     

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

                    <h4 class="header-title">
                        Editando Sorteo
                    </h4>

                    <br>

                    <form action="{{ route('lotteries.update', $lottery->id) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
                        <div class="row">
                        
                        <div class="col-md-12">

                            <div class="form-card bs">

                                <h4 class="mb-0 mt-1">
                                    Datos del Sorteo
                                </h4>
                                <small><i>Modifica los datos del sorteo</i></small>

                                <div class="form-group mt-2 mb-3">
                                    <div class="photo-preview" style="width: 200px; background-image: url({{ $lottery->image ? url('uploads/' . $lottery->image) : '' }});">
                                        @if(!$lottery->image)
                                            <i class="ri-image-add-line"></i>
                                        @endif
                                    </div>

                                    <div>
                                        <small><i>Imágen</i></small><br>
                                        <b>Décimo</b><br>
                                        <label style="border-radius: 30px; width: 150px; background-color: #333;" class="btn btn-md btn-dark mt-2">
                                            <small>Subir Imágen</small>
                                            <input type="file" id="imagenInput" name="image" style="display: none;" accept="image/*">
                                        </label>
                                        @if($lottery->image)
                                        <a href="#" class="btn btn-md mt-2" onclick="event.preventDefault(); document.getElementById('delete-image-form').submit();" style="border-radius: 30px; width: 150px; background-color: transparent; color: #333;"><small>Eliminar Imágen</small></a>
                                        @endif
                                    </div>
                                    <div style="clear: both;"></div>
                                </div>

                                <div class="row">
                                    @php
                                        // Extraer num_sorteo y año del name actual (formato: 056/25)
                                        $nameParts = explode('/', $lottery->name);
                                        $currentNumSorteo = isset($nameParts[0]) ? ltrim($nameParts[0], '0') : '';
                                        $currentAnyo = isset($nameParts[1]) ? '20' . $nameParts[1] : date('Y');
                                    @endphp

                                    <div class="col-1">
                                        <div class="form-group mt-2 mb-3">
                                            <label class="label-control">Nº Sorteo</label>
                                            <div class="input-group input-group-merge group-form">
                                                <input class="form-control" type="text" id="num_sorteo" placeholder="56" style="border-radius: 30px;" value="{{ old('num_sorteo', $currentNumSorteo) }}" required>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-1">
                                        <div class="form-group mt-2 mb-3">
                                            <label class="label-control">Año</label>
                                            <div class="input-group input-group-merge group-form">
                                                <input class="form-control" type="text" id="anyo" placeholder="2025" style="border-radius: 30px;" value="{{ old('anyo', $currentAnyo) }}" required>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-2">
                                        <div class="form-group mt-2 mb-3">
                                            <label class="label-control">Código (Generado)</label>
                                            <div class="input-group input-group-merge group-form">
                                                <input class="form-control" type="text" name="name" id="name_generated" value="{{ old('name', $lottery->name) }}" style="border-radius: 30px; background-color: #f0f0f0;" readonly required>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-6">
                                        <div class="form-group mt-2 mb-3">
                                            <label class="label-control">Nombre del Sorteo</label>
                                            <div class="input-group input-group-merge group-form">
                                                <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                                    <img src="{{url('assets/form-groups/admin/17.svg')}}" alt="">
                                                </div>
                                                <input class="form-control" type="text" name="description" value="{{ old('description', $lottery->description) }}" style="border-radius: 0 30px 30px 0;">
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
                                                <select class="form-control" name="lottery_type_id" style="border-radius: 0 30px 30px 0;">
                                                    @foreach($lotteryTypes as $type)
                                                        <option value="{{ $type->id }}" {{ (old('lottery_type_id', $lottery->lottery_type_id) == $type->id) ? 'selected' : '' }}>{{ $type->name }}</option>
                                                    @endforeach
                                                </select>
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
                                                <input class="form-control" type="number" step="0.01" name="ticket_price" value="{{ old('ticket_price', $lottery->ticket_price) }}" style="border-radius: 0 30px 30px 0;">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-2 d-none">
                                        <div class="form-group mt-2 mb-3">
                                            <label class="label-control">Código Sorteo</label>

                                            <div class="input-group input-group-merge group-form">

                                                <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                                    <img src="{{url('assets/form-groups/admin/14.svg')}}" alt="">
                                                </div>

                                                <select class="form-control" name="lottery_type_code" style="border-radius: 0 30px 30px 0;">
                                                    <option value="">Seleccionar código</option>
                                                    <option value="J" {{ old('lottery_type_code', $lottery->lottery_type_code) == 'J' ? 'selected' : '' }}>J - Jueves</option>
                                                    <option value="X" {{ old('lottery_type_code', $lottery->lottery_type_code) == 'X' ? 'selected' : '' }}>X - Sábado</option>
                                                    <option value="S" {{ old('lottery_type_code', $lottery->lottery_type_code) == 'S' ? 'selected' : '' }}>S - Extraordinario</option>
                                                    <option value="N" {{ old('lottery_type_code', $lottery->lottery_type_code) == 'N' ? 'selected' : '' }}>N - Navidad</option>
                                                    <option value="B" {{ old('lottery_type_code', $lottery->lottery_type_code) == 'B' ? 'selected' : '' }}>B - Niño</option>
                                                    <option value="V" {{ old('lottery_type_code', $lottery->lottery_type_code) == 'V' ? 'selected' : '' }}>V - Vacaciones</option>
                                                </select>
                                            </div>
                                            @error('lottery_type_code')
                                                <small class="text-danger">{{ $message }}</small>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="col-2 d-none">
                                        <div class="form-group mt-2 mb-3">
                                            <label class="label-control">¿Es Especial?</label>

                                            <div style="padding-top: 8px;">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" name="is_special" value="1" id="is_special_edit" {{ old('is_special', $lottery->is_special) ? 'checked' : '' }}>
                                                    <label class="form-check-label" for="is_special_edit">
                                                        Sorteo Especial
                                                    </label>
                                                </div>
                                            </div>
                                            @error('is_special')
                                                <small class="text-danger">{{ $message }}</small>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="col-2">
                                        <div class="form-group mt-2 mb-3">
                                            <label class="label-control">Fecha Sorteo</label>
                                            <div class="input-group input-group-merge group-form">
                                                <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                                    <img src="{{url('assets/form-groups/admin/12.svg')}}" alt="">
                                                </div>
                                                <input class="form-control" type="date" id="draw_date" name="draw_date" value="{{ old('draw_date', $lottery->draw_date->format('Y-m-d')) }}" style="border-radius: 0 30px 30px 0;">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-2">
                                        <div class="form-group mt-2 mb-3">
                                            <label class="label-control">Fecha Límite</label>
                                            <div class="input-group input-group-merge group-form">
                                                <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                                    <img src="{{url('assets/form-groups/admin/12.svg')}}" alt="">
                                                </div>
                                                <input class="form-control" type="date" id="deadline_date" name="deadline_date" value="{{ old('deadline_date', $lottery->deadline_date->format('Y-m-d')) }}" style="border-radius: 0 30px 30px 0;" max="">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-2">
                                        <div class="form-group mt-2 mb-3">
                                            <label class="label-control">Hora Límite</label>
                                            <div class="input-group input-group-merge group-form">
                                                <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                                    <img src="{{url('assets/form-groups/admin/18.svg')}}" alt="">
                                                </div>
                                                <input class="form-control" type="time" name="draw_time" value="{{ old('draw_time', $lottery->draw_time->format('H:i')) }}" style="border-radius: 0 30px 30px 0;">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group mt-2 mb-3">
                                            <label class="label-control">Total de boletos</label>
                                            <input class="form-control" type="number" name="total_tickets" value="{{ old('total_tickets', $lottery->total_tickets) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group mt-2 mb-3">
                                            <label class="label-control">Descripción del Premio</label>
                                            <input class="form-control" type="text" name="prize_description" value="{{ old('prize_description', $lottery->prize_description) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group mt-2 mb-3">
                                            <label class="label-control">Valor del Premio</label>
                                            <input class="form-control" type="number" step="0.01" name="prize_value" value="{{ old('prize_value', $lottery->prize_value) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group mt-2 mb-3">
                                            <label class="label-control">Estado</label>
                                            <select class="form-control" name="status">
                                                <option value="1" {{ old('status', $lottery->status) == 1 ? 'selected' : '' }}>Activo</option>
                                                <option value="2" {{ old('status', $lottery->status) == 2 ? 'selected' : '' }}>Inactivo</option>
                                                <option value="3" {{ old('status', $lottery->status) == 3 ? 'selected' : '' }}>Completado</option>
                                                <option value="4" {{ old('status', $lottery->status) == 4 ? 'selected' : '' }}>Cancelado</option>
                                            </select>
                                        </div>
                                    </div> --}}
                                <br>
                            </div>
                        </div>



                        <div class="row">

                        <div class="col-6 text-start">
                            <a href="{{route('lotteries.show', $lottery->id)}}" style="border-radius: 30px; width: 200px; background-color: #333; color: #fff; padding: 8px; font-weight: bolder; position: relative;" class="btn btn-md btn-light mt-2">
                                <i style="top: 6px; left: 32%; font-size: 18px; position: absolute;" class="ri-arrow-left-circle-line"></i> <span style="display: block; margin-left: 16px;">Atrás</span></a>
                        </div>
                        <div class="col-6 text-end">
                            <button type="submit" style="border-radius: 30px; width: 200px; background-color: #e78307; color: #333; padding: 8px; font-weight: bolder; position: relative;" class="btn btn-md btn-light mt-2">Guardar
                                <i style="top: 6px; margin-left: 6px; font-size: 18px; position: absolute;" class="ri-save-line"></i></button>
                        </div>
                        </div>

                    </div>
                    </form>

                    <form id="delete-image-form" action="{{ route('lotteries.delete-image', $lottery->id) }}" method="POST" style="display: none;">
                        @csrf
                        @method('DELETE')
                    </form>
                    
                </div> <!-- end card body-->
            </div> <!-- end card -->
        </div><!-- end col-->
    </div>
    <!-- end row-->

</div> <!-- container -->

@endsection

@section('scripts')
<script>
    document.getElementById('imagenInput').addEventListener('change', function(event) {
        const archivo = event.target.files[0];
        if (archivo) {
            const lector = new FileReader();
            lector.onload = function(e) {
                $('.photo-preview').css('background-image', 'url(' + e.target.result + ')');
                // Guardar en localStorage para persistencia
                localStorage.setItem('image_lottery_edit_{{ $lottery->id }}', e.target.result);
            }
            lector.readAsDataURL(archivo);
        } else {
            localStorage.removeItem('image_lottery_edit_{{ $lottery->id }}');
        }
    });

    // Restaurar imagen si hay error de validación
    document.addEventListener('DOMContentLoaded', function() {
        const savedImage = localStorage.getItem('image_lottery_edit_{{ $lottery->id }}');
        if (savedImage) {
            $('.photo-preview').css('background-image', 'url('+savedImage+')');
        }
    });

    // Limpiar localStorage al enviar exitosamente
    $('form[action*="lotteries"]').on('submit', function() {
        setTimeout(() => {
            localStorage.removeItem('image_lottery_edit_{{ $lottery->id }}');
        }, 1000);
    });

    // Función para generar el nombre del sorteo automáticamente
    function generarNombreSorteo() {
        const numSorteo = document.getElementById('num_sorteo').value;
        const anyo = document.getElementById('anyo').value;
        
        if (numSorteo && anyo) {
            // Rellenar con ceros a la izquierda (3 dígitos)
            const numSorteoPadded = numSorteo.padStart(3, '0');
            // Últimas 2 cifras del año
            const anyoCortado = anyo.slice(-2);
            // Generar nombre
            const nombreGenerado = numSorteoPadded + '/' + anyoCortado;
            
            document.getElementById('name_generated').value = nombreGenerado;
        }
    }

    // Event listeners para generar nombre automáticamente
    document.getElementById('num_sorteo').addEventListener('input', generarNombreSorteo);
    document.getElementById('anyo').addEventListener('input', generarNombreSorteo);

    // Generar al cargar la página
    generarNombreSorteo();

    // Fecha límite: máximo el día anterior al sorteo (23:59)
    const drawDateInput = document.getElementById('draw_date');
    const deadlineDateInput = document.getElementById('deadline_date');
    if (drawDateInput && deadlineDateInput) {
        function getMaxDeadlineDate() {
            if (!drawDateInput.value) return null;
            const d = new Date(drawDateInput.value + 'T12:00:00');
            d.setDate(d.getDate() - 1);
            return d.toISOString().split('T')[0];
        }
        function syncDeadlineMax() {
            const maxDate = getMaxDeadlineDate();
            if (maxDate) {
                deadlineDateInput.setAttribute('max', maxDate);
                if (deadlineDateInput.value && deadlineDateInput.value > maxDate) {
                    deadlineDateInput.value = maxDate;
                }
            } else {
                deadlineDateInput.removeAttribute('max');
            }
        }
        drawDateInput.addEventListener('change', syncDeadlineMax);
        drawDateInput.addEventListener('input', syncDeadlineMax);
        syncDeadlineMax();
        document.querySelector('form[action*="lotteries"]').addEventListener('submit', function(e) {
            const maxDate = getMaxDeadlineDate();
            if (maxDate && deadlineDateInput.value && deadlineDateInput.value > maxDate) {
                e.preventDefault();
                deadlineDateInput.focus();
                alert('La fecha límite debe ser como máximo el día anterior al sorteo (23:59).');
                return false;
            }
        });
    }

</script>
@endsection