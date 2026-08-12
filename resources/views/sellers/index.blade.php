@extends('layouts.layout')

@section('title','Vendedores/Asignación')

@section('content')

<!-- Start Content-->
<div class="container-fluid">
    
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item active">Vendedores/Asignación</li>
                    </ol>
                </div>
                <h4 class="page-title">Vendedores/Asignación</h4>
            </div>
        </div>
    </div>     

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

                    {{-- @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif --}}

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

                    <div class="{{ count($sellers) > 0 ? '' : 'd-none' }}">
                        <h4 class="header-title d-flex flex-wrap align-items-center gap-2">
                            <select class="form-control form-control-sm" style="max-width: 140px;" id="filter-provincia">
                                <option value="">Provincia</option>
                                @php $provincias = $sellers->pluck('entities')->flatten()->pluck('province')->unique()->filter()->sort()->values(); @endphp
                                @foreach($provincias as $p)
                                    <option value="{{ $p }}">{{ $p }}</option>
                                @endforeach
                            </select>
                            <select class="form-control form-control-sm" style="max-width: 140px;" id="filter-localidad">
                                <option value="">Localidad</option>
                            </select>
                            <select class="form-control form-control-sm" style="max-width: 140px;" id="filter-estatus">
                                <option value="">Estatus</option>
                                <option value="Inactivo">Inactivo</option>
                                <option value="Activo">Activo</option>
                                <option value="Pendiente">Pendiente</option>
                                <option value="Bloqueado">Bloqueado</option>
                            </select>
                            <input type="text" class="form-control form-control-sm" style="max-width: 180px;" id="filter-busqueda" placeholder="Búsqueda">
                            @if($canManageSellers ?? false)
                            <a href="{{ route('sellers.create') }}" style="border-radius: 30px; width: 150px;" class="btn btn-md btn-dark ms-auto"><i class="ri-add-line me-1"></i> Añadir</a>
                            @endif
                        </h4>


                        <div style="clear: both;"></div>

                        <br>

                        <table id="example2" class="table table-striped nowrap w-100">
                            <thead class="filters">
                                <tr>
                                    <th>ID</th>
                                    @if(empty($hideSellerListPersonalData))
                                    <th>Nombre</th>
                                    <th>Email</th>
                                    @endif
                                    <th>T. Vendedor</th>
                                    <th>P. Asig.</th>
                                    <th>P. Ven.</th>
                                    <th>P. Dev.</th>
                                    <th>Deuda</th>
                                    @if(empty($hideEntityColumn))
                                    <th>Entidad</th>
                                    @endif
                                    <th>Provincia</th>
                                    <th>Status</th>
                                    <th class="no-filter">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($sellers as $seller)
                                @php
                                    $entidades = $seller->entities;
                                    $entidadesNombres = $entidades->pluck('name')->join(', ');
                                    $entidadesProvincias = $entidades->pluck('province')->unique()->join(', ');
                                    $entidad = $seller->getPrimaryEntity();
                                    $deuda = (float) ($seller->deuda_pendiente ?? 0);
                                @endphp
                                <tr class="row-clickable" data-href="{{ route('sellers.show', $seller->id) }}" data-provincia="{{ $entidadesProvincias ?: ($entidad?->province ?? '') }}" data-status="{{ $seller->getRawOriginal('status') ?? 0 }}" style="cursor: pointer;">
                                    <td><a href="{{ route('sellers.show', $seller->id) }}" class="text-dark text-decoration-none">#VE{{ str_pad($seller->id, 4, '0', STR_PAD_LEFT) }}</a></td>
                                    @if(empty($hideSellerListPersonalData))
                                    <td>{{ $seller->full_name }}</td>
                                    <td>{{ $seller->display_email ?: '—' }}</td>
                                    @endif
                                    <td>
                                        @if($seller->seller_type === 'partilot')
                                            <span class="badge bg-warning text-dark">Partilot</span>
                                        @else
                                            <span class="badge bg-secondary">Externo</span>
                                        @endif
                                    </td>
                                    <td>{{ $seller->participaciones_asignadas ?? 0 }}</td>
                                    <td>{{ $seller->participaciones_vendidas ?? 0 }}</td>
                                    <td>{{ $seller->participaciones_devueltas ?? 0 }}</td>
                                    <td class="{{ $deuda > 0 ? 'text-danger fw-semibold' : '' }}">{{ number_format($deuda, 2, ',', '.') }}€</td>
                                    @if(empty($hideEntityColumn))
                                    <td title="{{ $entidadesNombres }}">{{ $entidadesNombres ?: '—' }}</td>
                                    @endif
                                    <td title="{{ $entidadesProvincias }}">{{ $entidadesProvincias ?: ($entidad?->province ?? '—') }}</td>
                                    <td>
                                        <span class="badge bg-{{ $seller->status_class }}">{{ $seller->status_text }}</span>
                                    </td>
                                    <td class="no-click" style="cursor: default;">
                                        <a href="{{ route('sellers.show', $seller->id) }}" class="btn btn-sm btn-light" title="Ver ficha"><i class="ri-external-link-line"></i></a>
                                        @if($canEditOrDeleteSellers ?? false)
                                        <a href="{{ route('sellers.edit', $seller->id) }}" class="btn btn-sm btn-light" title="Editar"><i class="ri-pencil-line"></i></a>
                                        <form action="{{ route('sellers.destroy', $seller->id) }}" method="POST" style="display: inline;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger" title="Eliminar" onclick="return confirm('¿Estás seguro de que quieres eliminar este vendedor?')">
                                                <i class="ri-delete-bin-6-line"></i>
                                            </button>
                                        </form>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <br>

                        <a href="{{url('groups?table=1')}}" style="border-radius: 30px; width: 180px; top: -12px; left: -12px; position: relative;" class="btn btn-md btn-dark">
                            <img src="{{url('icons_/grupos.svg')}}" alt="" width="18px" style="position: relative; top: -1px;">
                         Gestión Grupos</a>

                    </div>

                    <div class="{{ count($sellers) > 0 ? 'd-none' : '' }}">
                        <a href="{{url('groups')}}" style="border-radius: 30px; width: 180px; top: -12px; left: -12px; position: relative;" class="btn btn-md btn-dark float-start">
                            <img src="{{url('icons_/grupos.svg')}}" alt="" width="18px" style="position: relative; top: -1px;">
                         Gestión Grupos</a>
                         <div style="clear: both;"></div>
                        <div class="d-flex align-items-center gap-1">

                            
                            <div class="empty-tables">

                                <div>
                                    <img src="{{url('icons_/vendedores.svg')}}" alt="" width="80px">
                                </div>

                                <h3 class="mb-0">No hay Vendedores</h3>

                                <small>Añade Vendedores</small>

                                <br>

                                @if($canManageSellers ?? false)
                                <a href="{{ route('sellers.create') }}" style="border-radius: 30px; width: 150px;" class="btn btn-md btn-dark mt-2"><i style="position: relative; top: 2px;" class="ri-add-line"></i> Añadir</a>
                                @endif
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
  
  // Filtros superiores: aplicar a DataTable
  var table = $('#example2').DataTable();
  $('#filter-provincia').on('change', function() {
    table.column(9).search(this.value).draw();
  });
  $('#filter-estatus').on('change', function() {
    table.column(10).search(this.value).draw();
  });
  $('#filter-busqueda').on('keyup', function() {
    table.search(this.value).draw();
  });

  // Filas clickeables (excepto columna de acciones)
  $(document).on('click', '#example2 tbody tr.row-clickable', function(e) {
    if ($(e.target).closest('td.no-click').length) return;
    if ($(e.target).is('a') || $(e.target).is('button') || $(e.target).closest('a').length || $(e.target).closest('button').length) return;
    var href = $(this).data('href');
    if (href) window.location.href = href;
  });
  $(document).on('mouseenter', '#example2 tbody tr.row-clickable', function() {
    $(this).css('background-color', '#f8f9fa');
  }).on('mouseleave', '#example2 tbody tr.row-clickable', function() {
    $(this).css('background-color', '');
  });

</script>

@endsection