<?php

return [
    'gestor_responsable' => [
        'version' => env('LEGAL_ROLE_GR_VERSION', '3'),
        'hash' => env('LEGAL_ROLE_GR_HASH', 'role_gr_v3'),
        'screen_title' => 'Tienes una solicitud pendiente',
        'accept_label' => 'Acepto el cargo y el contrato en nombre de la entidad',
        'reject_label' => 'Rechazar esta designación',
        'summary_bullets' => [
            'Serás el único autorizado para ejecutar la liquidación definitiva con la Administración de Lotería. Este acto es irreversible.',
            'Supervisarás todas las operaciones de la Entidad en la Plataforma.',
            'Gestionarás el estado de los Vendedores vinculados a la Entidad.',
            'Si hay premios, serás quien autorice el pago a través de la Plataforma.',
            'No puedes abandonar este cargo sin que haya un sustituto que lo acepte.',
        ],
    ],
    'gestor' => [
        'version' => env('LEGAL_ROLE_GESTOR_VERSION', '3'),
        'hash' => env('LEGAL_ROLE_GESTOR_HASH', 'role_gestor_v3'),
        'screen_title' => 'Invitación a colaborar',
        'accept_label' => 'Aceptar invitación',
        'reject_label' => 'Rechazar invitación',
        'summary_bullets' => [
            'Solo podrás acceder a los datos y funciones que el Gestor Responsable te asigne.',
            'Eres responsable del uso que hagas de los datos de otros usuarios a los que accedas.',
            'Puedes ser desvinculado de este rol en cualquier momento por el Gestor Responsable.',
            'Puedes ser Gestor en varias Entidades a la vez. Esta aceptación solo aplica a la entidad indicada.',
        ],
    ],
    'vendedor' => [
        'version' => env('LEGAL_ROLE_VENDEDOR_VERSION', '3'),
        'hash' => env('LEGAL_ROLE_VENDEDOR_HASH', 'role_vendedor_v3'),
        'screen_title' => 'Invitación como Vendedor',
        'accept_label' => 'Acepto ser Vendedor',
        'reject_label' => 'Rechazar invitación',
        'summary_bullets' => [
            'Desde el momento en que firmes el recibo de las participaciones físicas, eres responsable de ellas.',
            'El dinero que recojas de las ventas es de la Entidad, no tuyo.',
            'Estás obligado a verificar que los compradores son mayores de 18 años.',
            'Los datos de los compradores son confidenciales.',
            'Está terminantemente prohibido fotocopiar o reproducir las participaciones físicas asignadas.',
        ],
    ],
    'recibo_participaciones' => [
        'version' => env('LEGAL_RECIBO_PARTICIPACIONES_VERSION', '1'),
        'hash' => env('LEGAL_RECIBO_PARTICIPACIONES_HASH', 'recibo_participaciones_v1'),
        'accept_label' => 'Acepto recibo de participaciones',
        'summary_bullets' => [
            'Desde el momento en que firmes el recibo de las participaciones físicas, eres responsable de ellas.',
            'El dinero que recojas de las ventas es de la Entidad, no tuyo.',
            'Debes verificar que los compradores son mayores de 18 años.',
            'Está prohibido fotocopiar o reproducir las participaciones físicas.',
            'La Administración puede ver tus datos operativos para resolver incidencias.',
        ],
    ],
];
