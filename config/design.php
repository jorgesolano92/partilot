<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Vista HTML en lugar de PDF (depuración)
    |--------------------------------------------------------------------------
    |
    | Si es true, las rutas web síncronas de exportación de diseño
    | (participación, portadas, traseras) devuelven el HTML que DomPDF
    | renderizaría, para inspeccionar en el navegador el mismo markup
    | que genera el PDF. Los jobs en cola y saveGridPdfFacadeToPath
    | siguen generando PDF real.
    |
    | .env: DESIGN_PDF_HTML_PREVIEW=true
    |
    */
    'pdf_html_preview' => filter_var(env('DESIGN_PDF_HTML_PREVIEW', false), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | Superadmin: editar sin aprobación de entidad
    |--------------------------------------------------------------------------
    |
    | Si es true, los diseños creados/editados por un superadministrador
    | no requieren aprobación de la entidad (puede continuar a imprenta/QR).
    | Los diseños de administración (no superadmin) siguen exigiendo aprobación.
    |
    | .env: DESIGN_SUPERADMIN_SKIP_ENTITY_APPROVAL=true
    |
    */
    'superadmin_skip_entity_approval' => filter_var(
        env('DESIGN_SUPERADMIN_SKIP_ENTITY_APPROVAL', false),
        FILTER_VALIDATE_BOOLEAN
    ),

];
