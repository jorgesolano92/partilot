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
    'definitive_liquidation' => [
        'version' => env('LEGAL_L8_VERSION', '3'),
        'hash' => env('LEGAL_L8_HASH', 'l8_liquidacion_definitiva_v3'),
        'title' => 'Confirmar liquidación definitiva',
        'warning' => 'Esta liquidación es definitiva e irreversible. Confirma que deseas cerrar la cuenta con la Administración de Lotería.',
        'confirmation_phrase' => 'CONFIRMO LIQUIDACIÓN',
        'confirmation_label' => 'Escribe exactamente: CONFIRMO LIQUIDACIÓN',
        'confirm_button' => 'Continuar con la liquidación',
    ],
];
