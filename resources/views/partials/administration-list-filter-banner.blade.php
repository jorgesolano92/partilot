@if(!empty($filterAdministration))
    <div class="alert alert-info py-2 mb-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <span>
            Filtrando por administración: <strong>{{ $filterAdministration->name }}</strong>
        </span>
        @if(!empty($clearFilterUrl))
            <a href="{{ $clearFilterUrl }}" class="btn btn-sm btn-light">Quitar filtro</a>
        @endif
    </div>
@endif
