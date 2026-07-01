<?php

return [
    /*
    | Bloquear creación/edición de reservas, sets, diseños, etc. cuando draw_date ya pasó.
    | false = modo depuración (permite operar con sorteos pasados).
    | true  = modo real (recomendado en producción).
    */
    'enforce_draw_date_rules' => filter_var(
        env('LOTTERY_ENFORCE_DRAW_DATE_RULES', true),
        FILTER_VALIDATE_BOOLEAN
    ),

    /** Mensaje genérico si no hay fecha de sorteo en el modelo. */
    'draw_date_passed_message' => 'No se puede continuar: la fecha del sorteo ya ha pasado.',

    /*
    | Cierre automático al vencer la fecha límite de devolución:
    | participaciones físicas no devueltas → vendidas + deuda (devolución entidad→admin).
    |
    | LOTTERY_AUTO_DEADLINE_CLOSURE_ENABLED=false (default) → no ejecuta en cron; usar
    |   php artisan sipart:lottery-deadline-closure --ignore-disabled para pruebas.
    | LOTTERY_ENFORCE_DRAW_DATE_RULES=false → sigue permitiendo operar sorteos pasados en panel
    |   y digitalizar / vincular en cartera fuera del plazo (deadline_date);
    |   el cierre solo mira la fecha límite efectiva, no bloquea por draw_date.
    */
    'auto_deadline_closure' => [
        'enabled' => filter_var(
            env('LOTTERY_AUTO_DEADLINE_CLOSURE_ENABLED', false),
            FILTER_VALIDATE_BOOLEAN
        ),
        'system_user_id' => env('LOTTERY_AUTO_DEADLINE_CLOSURE_USER_ID'),
        'return_reason' => 'Cierre automático por fecha límite de devolución',
    ],

    /*
    | Avisos modal 3/2/1/0 días: superadmin no los recibe por defecto (spec cliente).
    | true = mostrar también a superadmin (depuración / entornos legacy).
    */
    'deadline_reminders' => [
        'superadmin_modal' => filter_var(
            env('LOTTERY_DEADLINE_REMINDER_SUPERADMIN_MODAL', false),
            FILTER_VALIDATE_BOOLEAN
        ),
    ],

    /*
    | Tope de décimos por operación de reserva (0 = sin límite).
    | NEW-F3-04 — configurable vía LOTTERY_MAX_RESERVATION_TICKETS.
    */
    'max_reservation_tickets' => max(0, (int) env('LOTTERY_MAX_RESERVATION_TICKETS', 0)),
];
