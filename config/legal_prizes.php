<?php

return [
    'collection' => [
        'version' => env('LEGAL_L6_VERSION', '3'),
        'hash' => env('LEGAL_L6_HASH', 'l6_cobro_premio_v3'),
        'title' => 'Confirmar cobro de premio',
        'irreversibility_warning' => 'Esta operación es irreversible. Una vez confirmada no podrá modificarse ni cancelarse.',
        'confirm_label' => 'Confirmar cobro',
        'confirm_again_label' => 'Pulsa de nuevo para confirmar',
        'double_confirm_message' => '¿Estás seguro? Esta acción no puede deshacerse.',
        'legal_link_label' => 'Ver condiciones de cobro',
    ],
    'donation' => [
        'version' => env('LEGAL_L7_VERSION', '3'),
        'hash' => env('LEGAL_L7_HASH', 'l7_donacion_premio_v3'),
        'title' => 'Confirmar donación de premio',
        'notice_template' => 'El importe donado será transferido íntegramente a :entity_name. La donación es irreversible.',
        'fiscal_certificate_question' => '¿Deseas recibir un certificado de donación con efectos fiscales?',
        'rgpd_notice_template' => 'Tus datos fiscales se transmitirán a :entity_name para la emisión del certificado.',
        'confirm_label' => 'Confirmar donación',
        'confirm_again_label' => 'Pulsa de nuevo para confirmar',
    ],
    'payment_mode' => [
        'version' => env('LEGAL_FLUJO5_VERSION', '3'),
        'hash' => env('LEGAL_FLUJO5_HASH', 'flujo5_modalidad_pago_v3'),
        'irreversibility_warning' => 'Esta elección es irreversible. Una vez confirmada la liquidación, la entidad no podrá cambiar la modalidad de pago de premios para este sorteo.',
        'confirm_checkbox_label' => 'Confirmo la modalidad seleccionada para el cobro de premios de este sorteo.',
        'double_confirm_message' => '¿Confirmas la modalidad de pago? Esta acción no puede deshacerse.',
    ],
];
