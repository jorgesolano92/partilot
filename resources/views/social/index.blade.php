@extends('layouts.layout')

@section('title','Web/Social')

@section('content')

<style>

    .empty-tables {
        text-align: center;
        padding: 40px 20px;
    }

    .empty-tables img {
        opacity: 0.3;
        filter: grayscale(100%);
    }

    .empty-tables h3 {
        color: #333;
        font-weight: 600;
        margin: 20px 0 10px 0;
    }

    .empty-tables small {
        color: #666;
        font-size: 0.9em;
    }
    
</style>
<!-- Start Content-->
<div class="container-fluid">
    
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item active">Web Social</li>
                    </ol>
                </div>
                <h4 class="page-title">Web Social</h4>
            </div>
        </div>
    </div>     

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

                    <div class="{{count($socialWebs) ? '' : 'd-none'}}">
                        <h4 class="header-title">

                            <div class="float-start d-flex align-items-start">
                                <input type="text" class="form-control" style="margin-right: 8px ;" placeholder="Provincia">
                                <input type="text" class="form-control" style="margin-right: 8px ;" placeholder="Localidad">
                                <input type="text" class="form-control" style="margin-right: 8px ;" placeholder="Entidad">
                                <input type="text" class="form-control" placeholder="Status">
                            </div>

                            <a href="{{url('social/add')}}" style="border-radius: 30px; width: 150px;" class="btn btn-md btn-dark float-end"><i style="position: relative; top: 2px;" class="ri-add-line"></i> Añadir</a>

                        </h4>

                        <div style="clear: both;"></div>

                        <br>

                        <table id="example2" class="table table-striped nowrap w-100">
                            <thead class="filters">
                                <tr>
                                    <th>Orden ID</th>
                                    <th>Entidad</th>
                                    <th>Título</th>
                                    <th>Descripción</th>
                                    <th>Status</th>
                                    <th class="no-filter"></th>
                                </tr>
                            </thead>
                        
                        
                            <tbody>
                            @foreach($socialWebs as $socialWeb)
                            <tr>
                                <td>#SW{{ str_pad($socialWeb->id, 4, '0', STR_PAD_LEFT) }}</td>
                                <td>{{ $socialWeb->entity ? $socialWeb->entity->name : '-' }}</td>
                                <td>{{ $socialWeb->title }}</td>
                                <td>{{ Str::limit(strip_tags($socialWeb->description), 50) }}</td>
                                <td>
                                    @if($socialWeb->status === 'published')
                                        <label class="badge bg-success">Publicado</label>
                                    @else
                                        <label class="badge bg-warning">Pendiente</label>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ url('social/edit', $socialWeb->id) }}" class="btn btn-sm btn-light"><img src="{{url('assets/form-groups/edit.svg')}}" alt="" width="12"></a>
                                    <button class="btn btn-sm btn-danger" onclick="deleteSocialWeb({{ $socialWeb->id }})"><i class="ri-delete-bin-6-line"></i></button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        </table>

                        <br>

                    </div>

                    <div class="{{count($socialWebs) ? 'd-none' : ''}}">
                        <div class="d-flex align-items-center gap-1">

                            
                            <div class="empty-tables">

                                <div>
                                    @php
                                        $entityImg = null;
                                        if (Auth::check()) {
                                            $user = Auth::user();
                                            if ($user?->panel_account_type === 'entity' && $user?->panel_account_id) {
                                                $entityImg = \App\Models\Entity::where('id', (int) $user->panel_account_id)->value('image');
                                            } else {
                                                $primaryEntityId = $user->managers()
                                                    ->where('is_primary', true)
                                                    ->whereNotNull('entity_id')
                                                    ->value('entity_id');
                                                if ($primaryEntityId) {
                                                    $entityImg = \App\Models\Entity::where('id', (int) $primaryEntityId)->value('image');
                                                }
                                            }
                                        }
                                    @endphp
                                    @if(!empty($entityImg))
                                        <div class="logo-round" style="width:80px;height:80px;border-radius:50%;background-image:url('{{ asset('uploads/' . $entityImg) }}');background-size:cover;background-position:center;"></div>
                                    @else
                                        <img src="{{url('icons_/websocial.svg')}}" alt="" width="80px">
                                    @endif
                                </div>

                                <h3 class="mb-0">No hay Web/s Social/es</h3>

                                <small>Añade Web Social</small>

                                <br>

                                <a href="{{url('social/add')}}" style="border-radius: 30px; width: 150px;" class="btn btn-md btn-dark mt-2"><i style="position: relative; top: 2px;" class="ri-add-line"></i> Nueva</a>
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
                            //         console.log(val)
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

  function deleteSocialWeb(id) {
    var run = function () {
      $.ajax({
        url: '/social/' + id,
        type: 'DELETE',
        headers: {
          'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
          if (response.success) {
            location.reload();
          } else if (typeof window.partilotNotify === 'function') {
            window.partilotNotify('error', 'Error al eliminar la Web Social');
          }
        },
        error: function() {
          if (typeof window.partilotNotify === 'function') {
            window.partilotNotify('error', 'Error al eliminar la Web Social');
          }
        }
      });
    };

    if (typeof window.partilotConfirm === 'function') {
      window.partilotConfirm({
        title: 'Eliminar Web Social',
        message: '¿Estás seguro de que deseas eliminar esta Web Social?',
        confirmText: 'Eliminar'
      }).then(function (ok) { if (ok) run(); });
      return;
    }
    run();
  }

</script>

@endsection