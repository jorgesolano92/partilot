<?php

return [
    // PREMIOS PRINCIPALES
    [
        'nombre_categoria' => 'Primer Premio',
        'key_categoria' => 'primerPremio',
        'numero_arriba' => null,
        'numero_abajo' => null,
        'key_padre' => null,
        'importe_por_tipo' => [
            '3_J' => 300000,      // Sorteo de Jueves 3€
            '6_X' => 600000,      // Sorteo de Sábado 6€
            '12_S' => 1000000,    // Sorteo Extraordinario 12€
            '15_S' => 1500000,    // Sorteo Extraordinario 15€
            '15_S_ESPECIAL' => 1300000, // Sorteo Especial 15€ (130.000€)
            '20_N' => 4000000,    // Sorteo de Navidad
            '20_B' => 2000000,    // Sorteo del Niño
            '20_V' => 2000000,    // Sorteo de Vacaciones
        ],
        'cantidad_premios' => 1
    ],
    
    [
        'nombre_categoria' => 'Segundo Premio',
        'key_categoria' => 'segundoPremio',
        'numero_arriba' => null,
        'numero_abajo' => null,
        'key_padre' => null,
        'importe_por_tipo' => [
            '3_J' => 60000,       // Sorteo de Jueves 3€
            '6_X' => 120000,      // Sorteo de Sábado 6€
            '12_S' => 250000,     // Sorteo Extraordinario 12€
            '15_S' => 300000,     // Sorteo Extraordinario 15€
            '15_S_ESPECIAL' => 250000, // Sorteo Especial 15€
            '20_N' => 1250000,    // Sorteo de Navidad
            '20_B' => 750000,     // Sorteo del Niño
            '20_V' => 600000,     // Sorteo de Vacaciones
        ],
        'cantidad_premios' => 1
    ],
    
    [
        'nombre_categoria' => 'Tercer Premio',
        'key_categoria' => 'tercerosPremios',
        'numero_arriba' => null,
        'numero_abajo' => null,
        'key_padre' => null,
        'importe_por_tipo' => [
            '3_J' => 0,           // NO existe en Jueves
            '6_X' => 0,           // NO existe en Sábado
            '12_S' => 50000,      // Sorteo Extraordinario 12€
            '15_S' => 150000,     // Sorteo Extraordinario 15€
            '15_S_ESPECIAL' => 0, // NO existe en Especial 15€
            '20_N' => 500000,     // Sorteo de Navidad
            '20_B' => 250000,     // Sorteo del Niño
            '20_V' => 200000,     // Sorteo de Vacaciones
        ],
        'cantidad_premios' => 1
    ],

    // CUARTOS PREMIOS (Solo Navidad)
    [
        'nombre_categoria' => 'Cuartos Premios',
        'key_categoria' => 'cuartosPremios',
        'numero_arriba' => null,
        'numero_abajo' => null,
        'key_padre' => null,
        'importe_por_tipo' => [
            '3_J' => 0,           // NO existe
            '6_X' => 0,           // NO existe
            '12_S' => 0,          // NO existe
            '15_S' => 0,          // NO existe
            '15_S_ESPECIAL' => 0, // NO existe
            '20_N' => 200000,     // Solo Sorteo de Navidad
            '20_B' => 0,          // NO existe
            '20_V' => 0,          // NO existe
        ],
        'cantidad_premios' => [
            '3_J' => 0,
            '6_X' => 0,
            '12_S' => 0,
            '15_S' => 0,
            '15_S_ESPECIAL' => 0,
            '20_N' => 2,          // Solo Sorteo de Navidad
            '20_B' => 0,
            '20_V' => 0,
        ]
    ],

    // QUINTOS PREMIOS (Solo Navidad)
    [
        'nombre_categoria' => 'Quintos Premios',
        'key_categoria' => 'quintosPremios',
        'numero_arriba' => null,
        'numero_abajo' => null,
        'key_padre' => null,
        'importe_por_tipo' => [
            '3_J' => 0,           // NO existe
            '6_X' => 0,           // NO existe
            '12_S' => 0,          // NO existe
            '15_S' => 0,          // NO existe
            '15_S_ESPECIAL' => 0, // NO existe
            '20_N' => 60000,      // Solo Sorteo de Navidad
            '20_B' => 0,          // NO existe
            '20_V' => 0,          // NO existe
        ],
        'cantidad_premios' => [
            '3_J' => 0,
            '6_X' => 0,
            '12_S' => 0,
            '15_S' => 0,
            '15_S_ESPECIAL' => 0,
            '20_N' => 8,          // Solo Sorteo de Navidad
            '20_B' => 0,
            '20_V' => 0,
        ]
    ],

    // APROXIMACIONES AL PRIMER PREMIO
    [
        'nombre_categoria' => 'Anterior al Primer Premio',
        'key_categoria' => 'anteriorPrimerPremio',
        'numero_arriba' => null,
        'numero_abajo' => 1,
        'key_padre' => 'primerPremio',
        'importe_por_tipo' => [
            '3_J' => 12000,       // Sorteo de Jueves 3€
            '6_X' => 10000,       // Sorteo de Sábado 6€
            '12_S' => 17300,      // Sorteo Extraordinario 12€
            '15_S' => 21000,      // Sorteo Extraordinario 15€
            '15_S_ESPECIAL' => 24000, // Sorteo Especial 15€
            '20_N' => 20000,      // Sorteo de Navidad
            '20_B' => 12000,      // Sorteo del Niño
            '20_V' => 8000,       // Sorteo de Vacaciones
        ],
        'cantidad_premios' => 1
    ],
    
    [
        'nombre_categoria' => 'Posterior al Primer Premio',
        'key_categoria' => 'posteriorPrimerPremio',
        'numero_arriba' => 1,
        'numero_abajo' => null,
        'key_padre' => 'primerPremio',
        'importe_por_tipo' => [
            '3_J' => 12000,       // Sorteo de Jueves 3€
            '6_X' => 10000,       // Sorteo de Sábado 6€
            '12_S' => 17300,      // Sorteo Extraordinario 12€
            '15_S' => 21000,      // Sorteo Extraordinario 15€
            '15_S_ESPECIAL' => 24000, // Sorteo Especial 15€
            '20_N' => 20000,      // Sorteo de Navidad
            '20_B' => 12000,      // Sorteo del Niño
            '20_V' => 8000,       // Sorteo de Vacaciones
        ],
        'cantidad_premios' => 1
    ],

    // APROXIMACIONES AL SEGUNDO PREMIO
    [
        'nombre_categoria' => 'Anterior al Segundo Premio',
        'key_categoria' => 'anteriorSegundoPremio',
        'numero_arriba' => null,
        'numero_abajo' => 1,
        'key_padre' => 'segundoPremio',
        'importe_por_tipo' => [
            '3_J' => 7470,        // Sorteo de Jueves 3€
            '6_X' => 5540,        // Sorteo de Sábado 6€
            '12_S' => 9080,       // Sorteo Extraordinario 12€
            '15_S' => 12000,      // Sorteo Extraordinario 15€
            '15_S_ESPECIAL' => 15325, // Sorteo Especial 15€
            '20_N' => 12500,      // Sorteo de Navidad
            '20_B' => 6100,       // Sorteo del Niño
            '20_V' => 4300,       // Sorteo de Vacaciones
        ],
        'cantidad_premios' => 1
    ],
    
    [
        'nombre_categoria' => 'Posterior al Segundo Premio',
        'key_categoria' => 'posteriorSegundoPremio',
        'numero_arriba' => 1,
        'numero_abajo' => null,
        'key_padre' => 'segundoPremio',
        'importe_por_tipo' => [
            '3_J' => 7470,        // Sorteo de Jueves 3€ (cantidad: 2)
            '6_X' => 5540,        // Sorteo de Sábado 6€
            '12_S' => 9080,       // Sorteo Extraordinario 12€
            '15_S' => 12000,      // Sorteo Extraordinario 15€
            '15_S_ESPECIAL' => 15325, // Sorteo Especial 15€
            '20_N' => 12500,      // Sorteo de Navidad
            '20_B' => 6100,       // Sorteo del Niño
            '20_V' => 4300,       // Sorteo de Vacaciones
        ],
        'cantidad_premios' => [
            '3_J' => 2,           // Sorteo de Jueves 3€ (2 premios)
            '6_X' => 1,           // Sorteo de Sábado 6€
            '12_S' => 1,          // Sorteo Extraordinario 12€
            '15_S' => 1,          // Sorteo Extraordinario 15€
            '15_S_ESPECIAL' => 1, // Sorteo Especial 15€
            '20_N' => 1,          // Sorteo de Navidad
            '20_B' => 1,          // Sorteo del Niño
            '20_V' => 1,          // Sorteo de Vacaciones
        ]
    ],

    // APROXIMACIONES AL TERCER PREMIO (Solo donde existe 3º premio)
    [
        'nombre_categoria' => 'Anterior al Tercer Premio',
        'key_categoria' => 'anteriorTercerosPremios',
        'numero_arriba' => null,
        'numero_abajo' => 1,
        'key_padre' => 'tercerosPremios',
        'importe_por_tipo' => [
            '3_J' => 0,           // NO existe
            '6_X' => 0,           // NO existe
            '12_S' => 0,          // NO existe
            '15_S' => 6225,       // Sorteo Extraordinario 15€
            '15_S_ESPECIAL' => 0, // NO existe
            '20_N' => 9600,       // Sorteo de Navidad
            '20_B' => 0,          // NO existe
            '20_V' => 0,          // NO existe
        ],
        'cantidad_premios' => 1
    ],
    
    [
        'nombre_categoria' => 'Posterior al Tercer Premio',
        'key_categoria' => 'posteriorTercerosPremios',
        'numero_arriba' => 1,
        'numero_abajo' => null,
        'key_padre' => 'tercerosPremios',
        'importe_por_tipo' => [
            '3_J' => 0,           // NO existe
            '6_X' => 0,           // NO existe
            '12_S' => 0,          // NO existe
            '15_S' => 6225,       // Sorteo Extraordinario 15€
            '15_S_ESPECIAL' => 0, // NO existe
            '20_N' => 9600,       // Sorteo de Navidad
            '20_B' => 0,          // NO existe
            '20_V' => 0,          // NO existe
        ],
        'cantidad_premios' => 1
    ],

    // CENTENAS DEL PRIMER PREMIO
    [
        'nombre_categoria' => 'Centenas del Primer Premio',
        'key_categoria' => 'centenasPrimerPremio',
        'numero_arriba' => null,
        'numero_abajo' => null,
        'key_padre' => 'primerPremio',
        'importe_por_tipo' => [
            '3_J' => 300,         // Sorteo de Jueves 3€
            '6_X' => 300,         // Sorteo de Sábado 6€
            '12_S' => 600,        // Sorteo Extraordinario 12€
            '15_S' => 750,        // Sorteo Extraordinario 15€
            '15_S_ESPECIAL' => 750, // Sorteo Especial 15€
            '20_N' => 1000,       // Sorteo de Navidad
            '20_B' => 1000,       // Sorteo del Niño
            '20_V' => 1000,       // Sorteo de Vacaciones
        ],
        'cantidad_premios' => 99
    ],

    // CENTENAS DEL SEGUNDO PREMIO
    [
        'nombre_categoria' => 'Centenas del Segundo Premio',
        'key_categoria' => 'centenasSegundoPremio',
        'numero_arriba' => null,
        'numero_abajo' => null,
        'key_padre' => 'segundoPremio',
        'importe_por_tipo' => [
            '3_J' => 150,         // Sorteo de Jueves 3€
            '6_X' => 300,         // Sorteo de Sábado 6€
            '12_S' => 600,        // Sorteo Extraordinario 12€
            '15_S' => 750,        // Sorteo Extraordinario 15€
            '15_S_ESPECIAL' => 750, // Sorteo Especial 15€ (cantidad: 1)
            '20_N' => 1000,       // Sorteo de Navidad
            '20_B' => 1000,       // Sorteo del Niño
            '20_V' => 500,        // Sorteo de Vacaciones
        ],
        'cantidad_premios' => [
            '3_J' => 99,
            '6_X' => 99,
            '12_S' => 99,
            '15_S' => 99,
            '15_S_ESPECIAL' => 1, // Solo 1 premio en Especial
            '20_N' => 99,
            '20_B' => 99,
            '20_V' => 99,
        ]
    ],

    // CENTENAS DEL TERCER PREMIO (Solo donde existe 3º premio)
    [
        'nombre_categoria' => 'Centenas del Tercer Premio',
        'key_categoria' => 'centenasTercerosPremios',
        'numero_arriba' => null,
        'numero_abajo' => null,
        'key_padre' => 'tercerosPremios',
        'importe_por_tipo' => [
            '3_J' => 0,           // NO existe
            '6_X' => 0,           // NO existe
            '12_S' => 600,        // Sorteo Extraordinario 12€
            '15_S' => 750,        // Sorteo Extraordinario 15€
            '15_S_ESPECIAL' => 0, // NO existe
            '20_N' => 1000,       // Sorteo de Navidad
            '20_B' => 1000,       // Sorteo del Niño
            '20_V' => 500,        // Sorteo de Vacaciones
        ],
        'cantidad_premios' => [
            '3_J' => 0,
            '6_X' => 0,
            '12_S' => 99,
            '15_S' => 99,
            '15_S_ESPECIAL' => 0,
            '20_N' => 99,
            '20_B' => 99,
            '20_V' => 99,
        ]
    ],

    // CENTENAS DEL CUARTO PREMIO (Solo Navidad)
    [
        'nombre_categoria' => 'Centenas del Cuarto Premio',
        'key_categoria' => 'centenasCuartosPremios',
        'numero_arriba' => null,
        'numero_abajo' => null,
        'key_padre' => 'cuartosPremios',
        'importe_por_tipo' => [
            '3_J' => 0,           // NO existe
            '6_X' => 0,           // NO existe
            '12_S' => 0,          // NO existe
            '15_S' => 0,          // NO existe
            '15_S_ESPECIAL' => 0, // NO existe
            '20_N' => 1000,       // Solo Sorteo de Navidad
            '20_B' => 0,          // NO existe
            '20_V' => 0,          // NO existe
        ],
        'cantidad_premios' => [
            '3_J' => 0,
            '6_X' => 0,
            '12_S' => 0,
            '15_S' => 0,
            '15_S_ESPECIAL' => 0,
            '20_N' => 99,         // Solo Sorteo de Navidad
            '20_B' => 0,
            '20_V' => 0,
        ]
    ],

    // PREMIO A LA FRACCIÓN Y SERIE DEL PRIMER PREMIO (Solo sorteos especiales)
    [
        'nombre_categoria' => 'Premio a la Fracción y Serie del Primer Premio',
        'key_categoria' => 'premioFraccionSeriePrimerPremio',
        'numero_arriba' => null,
        'numero_abajo' => null,
        'key_padre' => 'primerPremio',
        'importe_por_tipo' => [
            '3_J' => 0,           // NO existe
            '6_X' => 0,           // NO existe
            '12_S' => 0,          // NO existe
            '15_S' => 0,          // NO existe
            '15_S_ESPECIAL' => 14870000, // Sorteo Especial 15€
            '20_N' => 0,          // NO existe en Navidad
            '20_B' => 0,          // NO existe
            '20_V' => 19800000,   // Sorteo de Vacaciones
        ],
        'cantidad_premios' => 1
    ],

    // 4 ÚLTIMAS CIFRAS DEL PRIMER PREMIO (Solo 3€)
    [
        'nombre_categoria' => '4 Últimas Cifras del Primer Premio',
        'key_categoria' => 'cuatroUltimasCifrasPrimerPremio',
        'numero_arriba' => null,
        'numero_abajo' => null,
        'key_padre' => 'primerPremio',
        'importe_por_tipo' => [
            '3_J' => 750,         // Solo Sorteo de Jueves 3€
            '6_X' => 0,           // NO existe
            '12_S' => 0,          // NO existe
            '15_S' => 0,          // NO existe
            '15_S_ESPECIAL' => 0, // NO existe
            '20_N' => 0,          // NO existe
            '20_B' => 0,          // NO existe
            '20_V' => 0,          // NO existe
        ],
        'cantidad_premios' => 1
    ],

    // 3 ÚLTIMAS CIFRAS DEL PRIMER PREMIO
    [
        'nombre_categoria' => '3 Últimas Cifras del Primer Premio',
        'key_categoria' => 'tresUltimasCifrasPrimerPremio',
        'numero_arriba' => null,
        'numero_abajo' => null,
        'key_padre' => 'primerPremio',
        'importe_por_tipo' => [
            '3_J' => 150,         // Sorteo de Jueves 3€
            '6_X' => 300,         // Sorteo de Sábado 6€
            '12_S' => 600,        // Sorteo Extraordinario 12€
            '15_S' => 750,        // Sorteo Extraordinario 15€
            '15_S_ESPECIAL' => 750, // Sorteo Especial 15€
            '20_N' => 0,          // NO existe (está en Pedrea)
            '20_B' => 1000,       // Sorteo del Niño
            '20_V' => 1000,       // Sorteo de Vacaciones
        ],
        'cantidad_premios' => 1
    ],

    // 2 ÚLTIMAS CIFRAS DEL PRIMER PREMIO
    [
        'nombre_categoria' => '2 Últimas Cifras del Primer Premio',
        'key_categoria' => 'dosUltimasCifrasPrimerPremio',
        'numero_arriba' => null,
        'numero_abajo' => null,
        'key_padre' => 'primerPremio',
        'importe_por_tipo' => [
            '3_J' => 60,          // Sorteo de Jueves 3€
            '6_X' => 120,         // Sorteo de Sábado 6€
            '12_S' => 240,        // Sorteo Extraordinario 12€
            '15_S' => 300,        // Sorteo Extraordinario 15€
            '15_S_ESPECIAL' => 750, // Sorteo Especial 15€
            '20_N' => 1000,       // Sorteo de Navidad
            '20_B' => 1000,       // Sorteo del Niño
            '20_V' => 400,        // Sorteo de Vacaciones
        ],
        'cantidad_premios' => 1
    ],

    // ÚLTIMA CIFRA DEL PRIMER PREMIO
    [
        'nombre_categoria' => 'Última Cifra del Primer Premio',
        'key_categoria' => 'ultimaCifraPrimerPremio',
        'numero_arriba' => null,
        'numero_abajo' => null,
        'key_padre' => 'primerPremio',
        'importe_por_tipo' => [
            '3_J' => 30,          // Sorteo de Jueves 3€
            '6_X' => 60,          // Sorteo de Sábado 6€
            '12_S' => 120,        // Sorteo Extraordinario 12€
            '15_S' => 150,        // Sorteo Extraordinario 15€
            '15_S_ESPECIAL' => 150, // Sorteo Especial 15€
            '20_N' => 200,        // Sorteo de Navidad
            '20_B' => 200,        // Sorteo del Niño
            '20_V' => 200,        // Sorteo de Vacaciones
        ],
        'cantidad_premios' => 1
    ],

    // 3 ÚLTIMAS CIFRAS DEL SEGUNDO PREMIO (Niño y Vacaciones)
    [
        'nombre_categoria' => '3 Últimas Cifras del Segundo Premio',
        'key_categoria' => 'tresUltimasCifrasSegundoPremio',
        'numero_arriba' => null,
        'numero_abajo' => null,
        'key_padre' => 'segundoPremio',
        'importe_por_tipo' => [
            '3_J' => 0,           // NO existe
            '6_X' => 0,           // NO existe
            '12_S' => 0,          // NO existe
            '15_S' => 0,          // NO existe
            '15_S_ESPECIAL' => 0, // NO existe
            '20_N' => 0,          // NO existe
            '20_B' => 1000,       // Sorteo del Niño
            '20_V' => 1000,       // Sorteo de Vacaciones
        ],
        'cantidad_premios' => 1
    ],

    // 2 ÚLTIMAS CIFRAS DEL SEGUNDO PREMIO (Solo Navidad)
    [
        'nombre_categoria' => '2 Últimas Cifras del Segundo Premio',
        'key_categoria' => 'dosUltimasCifrasSegundoPremio',
        'numero_arriba' => null,
        'numero_abajo' => null,
        'key_padre' => 'segundoPremio',
        'importe_por_tipo' => [
            '3_J' => 0,           // NO existe
            '6_X' => 0,           // NO existe
            '12_S' => 0,          // NO existe
            '15_S' => 0,          // NO existe
            '15_S_ESPECIAL' => 0, // NO existe
            '20_N' => 1000,       // Solo Sorteo de Navidad
            '20_B' => 0,          // NO existe
            '20_V' => 0,          // NO existe
        ],
        'cantidad_premios' => 1
    ],

    // 2 ÚLTIMAS CIFRAS DEL TERCER PREMIO (Solo Navidad)
    [
        'nombre_categoria' => '2 Últimas Cifras del Tercer Premio',
        'key_categoria' => 'dosUltimasCifrasTercerPremio',
        'numero_arriba' => null,
        'numero_abajo' => null,
        'key_padre' => 'tercerosPremios',
        'importe_por_tipo' => [
            '3_J' => 0,           // NO existe
            '6_X' => 0,           // NO existe
            '12_S' => 0,          // NO existe
            '15_S' => 0,          // NO existe
            '15_S_ESPECIAL' => 0, // NO existe
            '20_N' => 1000,       // Solo Sorteo de Navidad
            '20_B' => 0,          // NO existe
            '20_V' => 0,          // NO existe
        ],
        'cantidad_premios' => 1
    ],

    // EXTRACCIONES DE 5 CIFRAS (Solo 15€ extraordinarios)
    [
        'nombre_categoria' => 'Extracciones de 5 cifras',
        'key_categoria' => 'extraccionesDeCincoCifras',
        'numero_arriba' => null,
        'numero_abajo' => null,
        'key_padre' => null,
        'importe_por_tipo' => [
            '3_J' => 0,           // NO existe
            '6_X' => 0,           // NO existe
            '12_S' => 0,          // NO existe
            '15_S' => 75000,      // Solo Sorteo Extraordinario 15€
            '15_S_ESPECIAL' => 0, // NO existe
            '20_N' => 0,          // NO existe (está en Pedrea)
            '20_B' => 0,          // NO existe
            '20_V' => 0,          // NO existe
        ],
        'cantidad_premios' => [
            '3_J' => 0,
            '6_X' => 0,
            '12_S' => 0,
            '15_S' => 12,         // Solo Sorteo Extraordinario 15€
            '15_S_ESPECIAL' => 0,
            '20_N' => 0,
            '20_B' => 0,
            '20_V' => 0,
        ]
    ],

    // EXTRACCIONES DE 4 CIFRAS
    [
        'nombre_categoria' => 'Extracciones de 4 cifras',
        'key_categoria' => 'extraccionesDeCuatroCifras',
        'numero_arriba' => null,
        'numero_abajo' => null,
        'key_padre' => null,
        'importe_por_tipo' => [
            '3_J' => 750,         // Sorteo de Jueves 3€
            '6_X' => 1500,        // Sorteo de Sábado 6€
            '12_S' => 3000,       // Sorteo Extraordinario 12€
            '15_S' => 3750,       // Sorteo Extraordinario 15€
            '15_S_ESPECIAL' => 3750, // Sorteo Especial 15€
            '20_N' => 0,          // NO existe (está en Pedrea)
            '20_B' => 3500,       // Sorteo del Niño
            '20_V' => 0,          // NO existe
        ],
        'cantidad_premios' => [
            '3_J' => 4,
            '6_X' => 4,
            '12_S' => 5,
            '15_S' => 4,
            '15_S_ESPECIAL' => 5,
            '20_N' => 0,
            '20_B' => 2,
            '20_V' => 0,
        ]
    ],

    // EXTRACCIONES DE 3 CIFRAS
    [
        'nombre_categoria' => 'Extracciones de 3 cifras',
        'key_categoria' => 'extraccionesDeTresCifras',
        'numero_arriba' => null,
        'numero_abajo' => null,
        'key_padre' => null,
        'importe_por_tipo' => [
            '3_J' => 150,         // Sorteo de Jueves 3€
            '6_X' => 300,         // Sorteo de Sábado 6€
            '12_S' => 600,        // Sorteo Extraordinario 12€
            '15_S' => 750,        // Sorteo Extraordinario 15€
            '15_S_ESPECIAL' => 750, // Sorteo Especial 15€
            '20_N' => 0,          // NO existe (está en Pedrea)
            '20_B' => 1000,       // Sorteo del Niño
            '20_V' => 1000,       // Sorteo de Vacaciones
        ],
        'cantidad_premios' => [
            '3_J' => 7,
            '6_X' => 10,
            '12_S' => 11,
            '15_S' => 11,
            '15_S_ESPECIAL' => 15,
            '20_N' => 0,
            '20_B' => 14,
            '20_V' => 8,
        ]
    ],

    // EXTRACCIONES DE 2 CIFRAS
    [
        'nombre_categoria' => 'Extracciones de 2 cifras',
        'key_categoria' => 'extraccionesDeDosCifras',
        'numero_arriba' => null,
        'numero_abajo' => null,
        'key_padre' => null,
        'importe_por_tipo' => [
            '3_J' => 60,          // Sorteo de Jueves 3€
            '6_X' => 120,         // Sorteo de Sábado 6€
            '12_S' => 240,        // Sorteo Extraordinario 12€
            '15_S' => 300,        // Sorteo Extraordinario 15€
            '15_S_ESPECIAL' => 300, // Sorteo Especial 15€
            '20_N' => 0,          // NO existe (está en Pedrea)
            '20_B' => 400,        // Sorteo del Niño
            '20_V' => 400,        // Sorteo de Vacaciones
        ],
        'cantidad_premios' => [
            '3_J' => 9,
            '6_X' => 9,
            '12_S' => 9,
            '15_S' => 5,
            '15_S_ESPECIAL' => 2,
            '20_N' => 0,
            '20_B' => 5,
            '20_V' => 4,
        ]
    ],

    // REINTEGROS (ÚLTIMA CIFRA)
    [
        'nombre_categoria' => 'Reintegros',
        'key_categoria' => 'reintegros',
        'numero_arriba' => null,
        'numero_abajo' => null,
        'key_padre' => null,
        'importe_por_tipo' => [
            '3_J' => 30,          // Sorteo de Jueves 3€
            '6_X' => 60,          // Sorteo de Sábado 6€
            '12_S' => 120,        // Sorteo Extraordinario 12€
            '15_S' => 150,        // Sorteo Extraordinario 15€
            '15_S_ESPECIAL' => 150, // Sorteo Especial 15€
            '20_N' => 200,        // Sorteo de Navidad
            '20_B' => 200,        // Sorteo del Niño
            '20_V' => 200,        // Sorteo de Vacaciones
        ],
        'cantidad_premios' => [
            '3_J' => 2,
            '6_X' => 2,
            '12_S' => 2,
            '15_S' => 2,
            '15_S_ESPECIAL' => 2,
            '20_N' => 1,          // Solo 1 en Navidad
            '20_B' => 2,
            '20_V' => 2,
        ]
    ],

    // PEDREA (Solo Navidad)
    [
        'nombre_categoria' => 'Pedrea',
        'key_categoria' => 'pedrea',
        'numero_arriba' => null,
        'numero_abajo' => null,
        'key_padre' => null,
        'importe_por_tipo' => [
            '3_J' => 0,           // NO existe
            '6_X' => 0,           // NO existe
            '12_S' => 0,          // NO existe
            '15_S' => 0,          // NO existe
            '15_S_ESPECIAL' => 0, // NO existe
            '20_N' => 1000,       // Solo Sorteo de Navidad
            '20_B' => 0,          // NO existe
            '20_V' => 0,          // NO existe
        ],
        'cantidad_premios' => [
            '3_J' => 0,
            '6_X' => 0,
            '12_S' => 0,
            '15_S' => 0,
            '15_S_ESPECIAL' => 0,
            '20_N' => 1794,       // Solo Sorteo de Navidad
            '20_B' => 0,
            '20_V' => 0,
        ]
    ],
];