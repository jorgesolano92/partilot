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
                        <li class="breadcrumb-item"><a href="{{ route('lotteries.index') }}">Sorteos</a></li>
                        <li class="breadcrumb-item active">Ver Sorteo</li>
                    </ol>
                </div>
                <h4 class="page-title">Sorteos</h4>
            </div>
        </div>
    </div>     

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

                    <div class="d-flex justify-content-between">
                        <h4 class="header-title">
                            Detalles del Sorteo
                        </h4>
                    </div>

                    <br>

                    <form>
                        @csrf
                        <div class="row">
                        
                        <div class="col-md-12">

                            <div class="form-card bs mb-3">

                                <h4 class="mb-0 mt-1">
                                    Datos del Sorteo
                                    @if($lotteryAccess['canEditLotteryFull'] ?? false)
                                    <a href="{{ route('lotteries.edit', $lottery->id) }}" class="btn btn-light float-end" style="border: 1px solid silver; border-radius: 30px;">
                                        <img src="{{url('assets/form-groups/edit.svg')}}" alt="">
                                        Editar
                                    </a>
                                    @endif
                                </h4>
                                <small><i>Información detallada del sorteo</i></small>

                                <div class="form-group mt-2">

                                    <div class="photo-preview" style="width: 200px; background-image: url({{ $lottery->image ? url('uploads/' . $lottery->image) : 'url(https://via.placeholder.com/200)' }});">
                                        @if(!$lottery->image)
                                            <i class="ri-image-add-line"></i>
                                        @endif
                                    </div>
                                    
                                    <div style="clear: both;"></div>

                                    <br>
                                </div>

                                <div class="row">
                                    <div class="col-2">
                                        <div class="form-group mt-2 mb-3">
                                            <label class="label-control">Número del Sorteo</label>
                                            <div class="input-group input-group-merge group-form">
                                                <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                                    <img src="{{url('assets/form-groups/admin/16.svg')}}" alt="">
                                                </div>
                                                <input class="form-control" type="text" name="name" value="{{ $lottery->name }}" style="border-radius: 0 30px 30px 0;" readonly>
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
                                                <input class="form-control" type="text" name="description" value="{{ $lottery->description }}" style="border-radius: 0 30px 30px 0;" readonly>
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
                                                <input class="form-control" type="text" value="{{ $lottery->lotteryType->name ?? 'No especificado' }}" style="border-radius: 0 30px 30px 0;" readonly>
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
                                                <input class="form-control" type="text" value="{{ number_format($lottery->ticket_price, 2) }}€" style="border-radius: 0 30px 30px 0;" readonly>
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
                                                <input class="form-control" type="text" value="{{ \Carbon\Carbon::parse($lottery->draw_date)->format('d/m/Y') }}" style="border-radius: 0 30px 30px 0;" readonly>
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
                                                <input class="form-control" type="text" value="{{ $lottery->deadline_date ? \Carbon\Carbon::parse($lottery->deadline_date)->format('d/m/Y') : 'No especificada' }}" style="border-radius: 0 30px 30px 0;" readonly>
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
                                                <input class="form-control" type="text" value="{{ $lottery->deadlineTimeLabel() }}h" style="border-radius: 0 30px 30px 0;" readonly>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Información adicional -->
                                {{-- <div class="row">
                                     <div class="col-3">
                                        <div class="form-group mt-2 mb-3">
                                            <label class="label-control">Total de boletos</label>
                                            <input class="form-control" type="text" value="{{ $lottery->total_tickets }}" readonly>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="form-group mt-2 mb-3">
                                            <label class="label-control">Boletos vendidos</label>
                                            <input class="form-control" type="text" value="{{ $lottery->sold_tickets }}" readonly>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="form-group mt-2 mb-3">
                                            <label class="label-control">Premio</label>
                                            <input class="form-control" type="text" value="{{ $lottery->prize_description }}" readonly>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="form-group mt-2 mb-3">
                                            <label class="label-control">Valor del Premio</label>
                                            <input class="form-control" type="text" value="{{ number_format($lottery->prize_value, 2) }}€" readonly>
                                        </div>
                                    </div>
                                </div> --}}

                            </div>
                            
                        </div>

                        <div class="col-12 text-start">
                            
                            <a href="{{route('lotteries.index')}}" style="border-radius: 30px; width: 200px; background-color: #333; color: #fff; padding: 8px; font-weight: bolder; position: relative;" class="btn btn-md btn-light mt-2">
                                <i style="top: 6px; left: 32%; font-size: 18px; position: absolute;" class="ri-arrow-left-circle-line"></i> <span style="display: block; margin-left: 16px;">Atrás</span></a>
                        </div>

                    </div>
                    </form>
                    
                </div> <!-- end card body-->
            </div> <!-- end card -->
        </div><!-- end col-->
    </div>
    <!-- end row-->

</div> <!-- container -->

@endsection 