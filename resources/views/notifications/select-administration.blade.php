@extends('layouts.layout')

@section('title','Notificaciones')

@section('content')

<!-- Start Content-->
<div class="container-fluid">
    
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="javascript: void(0);">Notificaciones</a></li>
                        <li class="breadcrumb-item active">Nueva</li>
                        <li class="breadcrumb-item active">Administración</li>
                    </ol>
                </div>
                <h4 class="page-title">Notificaciones</h4>
            </div>
        </div>
    </div>     

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

                    <h4 class="header-title">
                        Selección
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
                                        Selección Tipo
                                    </label>

                                </div>

                                <div class="form-wizard-element active">
                                    
                                    <span>
                                        2
                                    </span>

                                    <img width="26px" src="{{url('icons_/selec_sorteo.svg')}}" alt="">

                                    <label>
                                        Selecc. Administración
                                    </label>

                                </div>

                                <div class="form-wizard-element">
                                    
                                    <span>
                                        3
                                    </span>

                                    <img src="{{url('assets/entidad.svg')}}" alt="">

                                    <label>
                                        Destino
                                    </label>

                                </div>

                                <div class="form-wizard-element">
                                    
                                    <span>
                                        4
                                    </span>

                                    <img src="{{url('assets/entidad.svg')}}" alt="">

                                    <label>
                                        Mensaje
                                    </label>

                                </div>
                                
                            </div>

                            <a href="{{route('notifications.create')}}" style="border-radius: 30px; width: 200px; background-color: #333; color: #fff; padding: 8px; font-weight: bolder; position: absolute; bottom: 16px;" class="btn btn-md btn-light mt-2">
                                <i style="top: 6px; left: 32%; font-size: 18px; position: absolute;" class="ri-arrow-left-circle-line"></i> <span style="display: block; margin-left: 16px;">Atrás</span></a>
                        </div>
                        <div class="col-md-9">
                            <div class="form-card bs" style="min-height: 658px;">
                                <form action="{{ route('notifications.store-administration') }}" method="POST">
                                    @csrf
                                    <h4 class="mb-0 mt-1">
                                        Administración
                                    </h4>
                                    <small><i>Selecciona la Administración</i></small>

                                    <br>
                                    <br>

                                    <div style="min-height: 656px;">
                                        <table id="example2" class="table table-striped nowrap w-100">
                                            <thead class="">
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Nombre Administración</th>
                                                    <th>Provincia</th>
                                                    <th>Localidad</th>
                                                    <th>Estado</th>
                                                    <th class="d-none">Seleccionar</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($administrations as $administration)
                                                <tr class="selectable-row" style="cursor: pointer;">
                                                    <td>#AD{{str_pad($administration->id, 4, '0', STR_PAD_LEFT)}}</td>
                                                    <td>{{$administration->name}}</td>
                                                    <td>{{$administration->province ?? 'Sin provincia'}}</td>
                                                    <td>{{$administration->city ?? 'Sin localidad'}}</td>
                                                    <td><label class="badge bg-success">Activo</label></td>
                                                    <td class="d-none">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="radio" name="administration_id" value="{{$administration->id}}" id="administration_{{$administration->id}}" required>
                                                            <label class="form-check-label" for="administration_{{$administration->id}}">
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
                                            <button type="submit" style="border-radius: 30px; width: 200px; background-color: #e78307; color: #333; padding: 8px; font-weight: bolder; position: relative;" class="btn btn-md btn-light mt-2">Seleccionar
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
        fixedHeader: true
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
