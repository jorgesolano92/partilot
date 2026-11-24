@extends('layouts.layout')

@section('title','Detalles Orden de Pago SEPA')

@section('content')

<!-- Start Content-->
<div class="container-fluid">
    
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
            	<div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{route('sepa-payments.index')}}">Órdenes de Pago SEPA</a></li>
                        <li class="breadcrumb-item active">Detalles</li>
                    </ol>
                </div>
                <h4 class="page-title">Detalles Orden de Pago SEPA #{{$sepaPaymentOrder->id}}</h4>
            </div>
        </div>
    </div>     

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

                    <!-- Información General -->
                    <h5 class="mb-3">Información General</h5>
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <strong>Message ID:</strong><br>
                            <span>{{$sepaPaymentOrder->message_id}}</span>
                        </div>
                        <div class="col-md-3">
                            <strong>Administración:</strong><br>
                            <span>{{$sepaPaymentOrder->administration->name ?? 'N/A'}}</span>
                        </div>
                        <div class="col-md-3">
                            <strong>Fecha de Creación:</strong><br>
                            <span>{{$sepaPaymentOrder->creation_date->format('d/m/Y H:i:s')}}</span>
                        </div>
                        <div class="col-md-3">
                            <strong>Fecha de Ejecución:</strong><br>
                            <span>{{$sepaPaymentOrder->execution_date->format('d/m/Y')}}</span>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-3">
                            <strong>Número de Transacciones:</strong><br>
                            <span>{{$sepaPaymentOrder->number_of_transactions}}</span>
                        </div>
                        <div class="col-md-3">
                            <strong>Importe Total:</strong><br>
                            <span class="h5">{{number_format($sepaPaymentOrder->control_sum, 2, ',', '.')}} €</span>
                        </div>
                        <div class="col-md-3">
                            <strong>Estado:</strong><br>
                            @if($sepaPaymentOrder->status == 'draft')
                                <label class="badge bg-warning">Borrador</label>
                            @elseif(in_array($sepaPaymentOrder->status, ['descargado', 'generated']))
                                <label class="badge bg-info">Descargado</label>
                            @elseif($sepaPaymentOrder->status == 'listo')
                                <label class="badge bg-success">Listo</label>
                            @elseif($sepaPaymentOrder->status == 'uploaded')
                                <label class="badge bg-info">Subido</label>
                            @else
                                <label class="badge bg-secondary">{{$sepaPaymentOrder->status}}</label>
                            @endif
                        </div>
                        <div class="col-md-3">
                            <strong>Archivo XML:</strong><br>
                            @if($sepaPaymentOrder->xml_filename)
                                <button type="button" class="btn btn-sm btn-primary" data-sepa-confirm-download="{{ $sepaPaymentOrder->id }}">
                                    <i class="ri-download-line"></i> Descargar XML
                                </button>
                            @else
                                <button type="button" class="btn btn-sm btn-success" data-sepa-confirm-download="{{ $sepaPaymentOrder->id }}">
                                    <i class="ri-file-add-line"></i> Generar XML
                                </button>
                            @endif
                        </div>
                    </div>

                    @if($sepaPaymentOrder->notes)
                    <div class="row mb-4">
                        <div class="col-12">
                            <strong>Notas:</strong><br>
                            <span>{{$sepaPaymentOrder->notes}}</span>
                        </div>
                    </div>
                    @endif

                    <!-- Datos del Deudor -->
                    <h5 class="mb-3 mt-4">Datos del Deudor (Pagador)</h5>
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <strong>Nombre:</strong><br>
                            <span>{{$sepaPaymentOrder->debtor_name}}</span>
                        </div>
                        <div class="col-md-4">
                            <strong>NIF/CIF:</strong><br>
                            <span>{{$sepaPaymentOrder->debtor_nif_cif ?? 'N/A'}}</span>
                        </div>
                        <div class="col-md-4">
                            <strong>IBAN:</strong><br>
                            <span>{{$sepaPaymentOrder->debtor_iban}}</span>
                        </div>
                    </div>
                    @if($sepaPaymentOrder->debtor_address)
                    <div class="row mb-4">
                        <div class="col-12">
                            <strong>Dirección:</strong><br>
                            <span>{{$sepaPaymentOrder->debtor_address}}</span>
                        </div>
                    </div>
                    @endif

                    <!-- Beneficiarios -->
                    @php
                        $hasPendingBeneficiaries = $sepaPaymentOrder->beneficiaries->contains(fn($b) => ($b->status ?? 'pending') === 'pending');
                        $canManagePayments = $hasPendingBeneficiaries && in_array($sepaPaymentOrder->status ?? '', ['descargado', 'generated', 'listo']);
                    @endphp
                    <h5 class="mb-3 mt-4">Beneficiarios</h5>
                    <div class="table-responsive">
                        <form id="form-beneficiarios-acciones" method="POST">
                            @csrf
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        @if($canManagePayments)
                                            <th style="width: 40px;">
                                                <input type="checkbox" class="form-check-input" id="check-all-beneficiaries" title="Seleccionar todos">
                                            </th>
                                        @endif
                                        <th>End to End ID</th>
                                        <th>Nombre</th>
                                        <th>NIF/CIF</th>
                                        <th>IBAN</th>
                                        <th>Importe</th>
                                        <th>Moneda</th>
                                        <th>Código Propósito</th>
                                        <th>Remesa</th>
                                        <th>Estado pago</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($sepaPaymentOrder->beneficiaries as $beneficiary)
                                    @php $isPending = ($beneficiary->status ?? 'pending') === 'pending'; @endphp
                                    <tr>
                                        @if($canManagePayments)
                                            <td>
                                                @if($isPending)
                                                    <input type="checkbox" class="form-check-input beneficiary-checkbox" name="beneficiary_ids[]" value="{{ $beneficiary->id }}">
                                                @endif
                                            </td>
                                        @endif
                                        <td>{{$beneficiary->end_to_end_id}}</td>
                                        <td>{{$beneficiary->creditor_name}}</td>
                                        <td>{{$beneficiary->creditor_nif_cif ?? 'N/A'}}</td>
                                        <td>{{$beneficiary->creditor_iban}}</td>
                                        <td>{{number_format($beneficiary->amount, 2, ',', '.')}}</td>
                                        <td>{{$beneficiary->currency}}</td>
                                        <td>{{$beneficiary->purpose_code}}</td>
                                        <td>{{$beneficiary->remittance_info ?? 'N/A'}}</td>
                                        <td>
                                            <span class="badge bg-{{ $beneficiary->statusBadgeClass() }}">{{ $beneficiary->statusLabel() }}</span>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="{{ $canManagePayments ? 5 : 4 }}" class="text-end">Total:</th>
                                        <th>{{number_format($sepaPaymentOrder->control_sum, 2, ',', '.')}} €</th>
                                        <th colspan="4"></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </form>
                    </div>

                    <!-- Botones de acción -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <a href="{{route('sepa-payments.index')}}" class="btn btn-secondary">Volver</a>
                            @if($sepaPaymentOrder->xml_filename)
                                <button type="button" class="btn btn-primary" data-sepa-confirm-download="{{ $sepaPaymentOrder->id }}">
                                    <i class="ri-download-line"></i> Descargar XML
                                </button>
                            @else
                                <button type="button" class="btn btn-success" data-sepa-confirm-download="{{ $sepaPaymentOrder->id }}">
                                    <i class="ri-file-add-line"></i> Generar XML
                                </button>
                            @endif
                            @if($canManagePayments)
                                <button type="button" id="btn-marcar-pagados" class="btn btn-success" disabled>
                                    <i class="ri-check-line"></i> Marcar seleccionados como pagados
                                </button>
                                <button type="button" id="btn-revertir-cobrable" class="btn btn-outline-warning" disabled>
                                    <i class="ri-arrow-go-back-line"></i> Revertir a cobrable (error banco)
                                </button>
                                <form action="{{route('sepa-payments.mark-ready', $sepaPaymentOrder->id)}}" method="POST" class="d-inline" onsubmit="return confirm('¿Marcar TODOS los beneficiarios pendientes como pagados?');">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-success">
                                        <i class="ri-check-double-line"></i> Marcar todos como pagados
                                    </button>
                                </form>
                            @endif
                            <form action="{{route('sepa-payments.destroy', $sepaPaymentOrder->id)}}" method="POST" class="d-inline" onsubmit="return confirm('¿Está seguro de eliminar esta orden de pago?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger">
                                    <i class="ri-delete-bin-line"></i> Eliminar
                                </button>
                            </form>
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
@if($canManagePayments ?? false)
<script>
document.addEventListener('DOMContentLoaded', function() {
    var checkAll = document.getElementById('check-all-beneficiaries');
    var checkboxes = document.querySelectorAll('.beneficiary-checkbox');
    var btnMarcar = document.getElementById('btn-marcar-pagados');
    var btnRevertir = document.getElementById('btn-revertir-cobrable');
    var formAcciones = document.getElementById('form-beneficiarios-acciones');

    function updateActionButtons() {
        var checked = document.querySelectorAll('.beneficiary-checkbox:checked').length;
        if (btnMarcar) btnMarcar.disabled = checked === 0;
        if (btnRevertir) btnRevertir.disabled = checked === 0;
    }

    if (checkAll) {
        checkAll.addEventListener('change', function() {
            checkboxes.forEach(function(cb) { cb.checked = checkAll.checked; });
            updateActionButtons();
        });
    }
    checkboxes.forEach(function(cb) {
        cb.addEventListener('change', updateActionButtons);
    });

    function submitBeneficiaryAction(action, confirmMsg) {
        var checked = document.querySelectorAll('.beneficiary-checkbox:checked');
        if (checked.length === 0) return;
        if (!confirm(confirmMsg)) return;
        formAcciones.action = action;
        formAcciones.submit();
    }

    if (btnMarcar && formAcciones) {
        btnMarcar.addEventListener('click', function() {
            submitBeneficiaryAction(
                '{{ route("sepa-payments.mark-beneficiaries-paid", $sepaPaymentOrder->id) }}',
                '¿Marcar los beneficiarios seleccionados como pagados?'
            );
        });
    }
    if (btnRevertir && formAcciones) {
        btnRevertir.addEventListener('click', function() {
            submitBeneficiaryAction(
                '{{ route("sepa-payments.revert-beneficiaries", $sepaPaymentOrder->id) }}',
                '¿Revertir los beneficiarios seleccionados? Las participaciones volverán a estar cobrables y la solicitud dejará de aparecer en pendientes de gestionar.'
            );
        });
    }
});
</script>
@endif

@include('partials.sepa-confirm-debtor-modal')
@endsection











