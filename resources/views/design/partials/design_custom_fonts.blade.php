{{-- Fuentes personalizadas del diseño (editor + DomPDF) --}}
@php
    $asgonlaeFile = public_path('Asgonlae.ttf');
    if (! empty($designPdfFonts) && is_readable($asgonlaeFile)) {
        // DomPDF: data-URI (evita fallos de file:// / chroot en Windows y subcarpetas)
        $asgonlaeSrc = 'data:font/truetype;base64,'.base64_encode((string) file_get_contents($asgonlaeFile));
    } else {
        // Navegador: ruta relativa al CSS público (independiente de APP_URL / subcarpeta)
        $asgonlaeSrc = asset('Asgonlae.ttf');
    }
@endphp
@font-face {
    font-family: 'Asgonlae';
    font-style: normal;
    font-weight: normal;
    src: url('{{ $asgonlaeSrc }}') format('truetype');
}
@font-face {
    font-family: 'Asgonlae';
    font-style: normal;
    font-weight: bold;
    src: url('{{ $asgonlaeSrc }}') format('truetype');
}
