{{-- Fuentes personalizadas del diseño (solo navegador/editor).
     DomPDF registra Asgonlae vía FontMetrics::registerFont (DesignController),
     no con @font-face: el parse CSS intenta escribir .ufm en storage/fonts y
     en prod puede tumbar el PDF con Permission denied. --}}
@if(empty($designPdfFonts))
@php
    $asgonlaeFile = public_path('Asgonlae.ttf');
    $asgonlaeSrc = is_readable($asgonlaeFile) ? asset('Asgonlae.ttf') : null;
@endphp
@if($asgonlaeSrc)
@@font-face {
    font-family: 'Asgonlae';
    font-style: normal;
    font-weight: normal;
    src: url('{{ $asgonlaeSrc }}') format('truetype');
}
@@font-face {
    font-family: 'Asgonlae';
    font-style: normal;
    font-weight: bold;
    src: url('{{ $asgonlaeSrc }}') format('truetype');
}
@endif
@endif
