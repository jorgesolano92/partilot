@extends('layouts.layout')

@section('title','Notificaciones')

@section('content')

<div class="container-fluid">

    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="javascript: void(0);">Notificaciones</a></li>
                        <li class="breadcrumb-item active">Nueva</li>
                        <li class="breadcrumb-item active">Entidad</li>
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

                    <h4 class="header-title">Selección</h4>
                    <br>

                    <div class="row">
                        <div class="col-md-3" style="position: relative;">
                            <div class="form-card bs mb-3">
                                <div class="form-wizard-element">
                                    <span>1</span>
                                    <img src="{{url('assets/entidad.svg')}}" alt="">
                                    <label>Selección Tipo</label>
                                </div>
                                <div class="form-wizard-element">
                                    <span>2</span>
                                    <img width="26px" src="{{url('icons_/selec_sorteo.svg')}}" alt="">
                                    <label>Selecc. Administración</label>
                                </div>
                                <div class="form-wizard-element">
                                    <span>3</span>
                                    <img src="{{url('assets/entidad.svg')}}" alt="">
                                    <label>Destino</label>
                                </div>
                                <div class="form-wizard-element active">
                                    <span>4</span>
                                    <img src="{{url('assets/entidad.svg')}}" alt="">
                                    <label>Selección Entidad/es</label>
                                </div>
                            </div>

                            <a href="{{route('notifications.select-administration-target')}}" style="border-radius: 30px; width: 200px; background-color: #333; color: #fff; padding: 8px; font-weight: bolder; position: absolute; bottom: 16px;" class="btn btn-md btn-light mt-2">
                                <i style="top: 6px; left: 32%; font-size: 18px; position: absolute;" class="ri-arrow-left-circle-line"></i> <span style="display: block; margin-left: 16px;">Atrás</span></a>
                        </div>
                        <div class="col-md-9">
                            <div class="form-card bs" style="min-height: 658px;">
                                <form action="{{ route('notifications.store-administration-entities') }}" method="POST">
                                    @csrf
                                    <h4 class="mb-0 mt-1">Entidades</h4>
                                    <small><i>Administración: {{ $administration->name ?? '' }} — marca las entidades</i></small>

                                    <br><br>

                                    <div class="row mb-3">
                                        <div class="col-12 text-end">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="selectAllSwitch" name="send_to_all" value="1">
                                                <label class="form-check-label" for="selectAllSwitch">
                                                    Seleccionar todas
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <div style="min-height: 500px;">
                                        <table id="example2" class="table table-striped nowrap w-100">
                                            <thead>
                                                <tr>
                                                    <th style="width: 48px;">Sel.</th>
                                                    <th>ID</th>
                                                    <th>Nombre Entidad</th>
                                                    <th>Provincia</th>
                                                    <th>Localidad</th>
                                                    <th>Estado</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($entities as $entity)
                                                <tr class="entity-pick-row" style="cursor: pointer;">
                                                    <td>
                                                        <div class="form-check">
                                                            <input class="form-check-input entity-checkbox" type="checkbox" name="entity_ids[]" value="{{$entity->id}}" id="entity_{{$entity->id}}">
                                                        </div>
                                                    </td>
                                                    <td>#EN{{str_pad($entity->id, 4, '0', STR_PAD_LEFT)}}</td>
                                                    <td>{{$entity->name}}</td>
                                                    <td>{{$entity->province ?? 'Sin provincia'}}</td>
                                                    <td>{{$entity->city ?? 'Sin localidad'}}</td>
                                                    <td><label class="badge bg-success">Activo</label></td>
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

                </div>
            </div>
        </div>
    </div>

</div>

@endsection

@section('scripts')
<script>
function initDatatable() {
    $("#example2").DataTable({
      "ordering": false,
      "scrollX": true,
      "scrollCollapse": true,
      orderCellsTop: true,
      fixedHeader: true
  });
}

$(document).ready(function() {
    initDatatable();

    function syncRowHighlight($row) {
        var $cb = $row.find('.entity-checkbox');
        if ($cb.is(':checked') && !$cb.is(':disabled')) {
            $row.css('background-color', '#e3f2fd');
        } else {
            $row.css('background-color', '');
        }
    }

    $(document).on('click', '#example2 tbody tr.entity-pick-row', function(e) {
        if ($(e.target).is('input[type="checkbox"]') || $(e.target).closest('label').length) {
            return;
        }
        var $cb = $(this).find('.entity-checkbox');
        if ($cb.is(':disabled')) {
            return;
        }
        $cb.prop('checked', !$cb.is(':checked')).trigger('change');
    });

    $(document).on('change', '.entity-checkbox', function() {
        if ($(this).is(':checked')) {
            $('#selectAllSwitch').prop('checked', false);
        }
        syncRowHighlight($(this).closest('tr'));
    });

    $('#selectAllSwitch').change(function() {
        if ($(this).is(':checked')) {
            $('.entity-checkbox').prop('disabled', true).prop('checked', false);
            $('#example2 tbody tr.entity-pick-row').css('background-color', '');
        } else {
            $('.entity-checkbox').prop('disabled', false);
        }
    });

    $('form').submit(function(e) {
        if (!$('#selectAllSwitch').is(':checked') && $('.entity-checkbox:checked').length === 0) {
            e.preventDefault();
            alert('Por favor selecciona al menos una entidad o marca "Seleccionar todas"');
        }
    });
});
</script>
@endsection
