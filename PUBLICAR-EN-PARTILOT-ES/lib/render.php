<?php

declare(strict_types=1);

function partilot_escape(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function partilot_layout(string $title, string $bodyHtml, array $config): void
{
    $package = partilot_escape((string) ($config['app_package'] ?? 'com.partilot.app'));
    $play = partilot_escape((string) ($config['play_store_url'] ?? ''));
    $appStore = partilot_escape((string) ($config['app_store_url'] ?? ''));

    echo '<!DOCTYPE html><html lang="es"><head>';
    echo '<meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>' . partilot_escape($title) . ' - PARTILOT</title>';
    echo '<meta name="apple-itunes-app" content="app-id=' . $package . '">';
    echo '<style>';
    readfile(__DIR__ . '/styles.css');
    echo '</style></head><body><div class="container"><div class="ticket-container">';
    echo '<div class="ticket-header"><h1>Verificación de Participación</h1>';
    echo '<p class="mb-0">Sistema de Verificación PARTILOT</p></div>';
    echo '<div class="ticket-body">' . $bodyHtml . '</div></div></div></body></html>';
}

function partilot_render_landing(array $config): void
{
    $play = partilot_escape((string) ($config['play_store_url'] ?? ''));
    $appStore = partilot_escape((string) ($config['app_store_url'] ?? ''));

    $body = '<div class="text-center">'
        . '<h4>Consulta tu premio con PARTILOT</h4>'
        . '<p>Para saber si tu participación tiene premio, <strong>escanea el código QR</strong> impreso en tu participación con el móvil '
        . 'o descarga la aplicación PARTILOT.</p>'
        . '<p class="text-muted">No es posible comprobar el premio escribiendo la referencia manualmente en esta web.</p>'
        . '<div class="store-buttons">'
        . '<a class="btn btn-verify" href="' . $play . '" target="_blank" rel="noopener">Google Play</a>'
        . '<a class="btn btn-verify" href="' . $appStore . '" target="_blank" rel="noopener">App Store</a>'
        . '</div></div>';

    partilot_layout('Consulta de premio', $body, $config);
}

function partilot_render_blocked(string $message, array $config): void
{
    $body = '<div class="error-container"><h4>Acceso restringido</h4><p>' . partilot_escape($message) . '</p></div>';
    partilot_layout('Acceso restringido', $body, $config);
}

function partilot_render_error(string $message, array $config): void
{
    $body = '<div class="error-container"><h4>Error</h4><p>' . partilot_escape($message) . '</p></div>';
    partilot_layout('Error', $body, $config);
}

function partilot_render_ticket(array $ticket, array $config): void
{
    $lottery = $ticket['lottery'] ?? [];
    $reserve = $ticket['reserve'] ?? [];
    $data = $ticket['data'] ?? [];
    $set = $ticket['set'] ?? [];
    $prize = $ticket['prize_info'] ?? [];

    $drawDate = $lottery['draw_date'] ?? null;
    $drawFormatted = 'N/A';
    if ($drawDate) {
        try {
            $drawFormatted = (new DateTimeImmutable((string) $drawDate))->format('d-m-Y');
        } catch (Exception) {
            $drawFormatted = partilot_escape((string) $drawDate);
        }
    }

    $numbers = $reserve['reservation_numbers'] ?? [];
    $numbersHtml = '';
    foreach ($numbers as $number) {
        $numbersHtml .= '<div class="number-box">' . str_pad((string) $number, 5, '0', STR_PAD_LEFT) . '</div>';
    }

    $prizeHtml = '';
    if (! empty($prize)) {
        if (! empty($prize['has_won'])) {
            $amount = number_format((float) ($prize['prize_amount'] ?? 0), 2);
            $prizeHtml = '<div class="prize-info text-center"><h3>¡FELICIDADES!</h3>'
                . '<div class="prize-amount">' . $amount . '€</div>'
                . '<p><strong>Premio por Participación</strong></p>'
                . '<span class="status-badge status-winner">GANADOR</span></div>';
        } else {
            $prizeHtml = '<div class="prize-info text-center"><h4>Sin Premio</h4>'
                . '<p>Esta participación no ha resultado premiada en este sorteo.</p>'
                . '<span class="status-badge status-no-prize">SIN PREMIO</span></div>';
        }
    } else {
        $prizeHtml = '<div class="text-center"><h4>Resultados Pendientes</h4>'
            . '<p>Los resultados de este sorteo aún no han sido publicados.</p>'
            . '<span class="status-badge status-pending">PENDIENTE</span></div>';
    }

    $body = '<div class="lottery-info">'
        . '<h4>' . partilot_escape($lottery['name'] ?? 'Sorteo') . '</h4>'
        . '<p class="mb-1"><strong>Fecha del Sorteo:</strong> ' . partilot_escape($drawFormatted) . '</p>'
        . '<p class="mb-0"><strong>Entidad:</strong> ' . partilot_escape($reserve['entity']['name'] ?? 'N/A') . '</p>'
        . '</div>'
        . '<div class="row">'
        . '<div class="col-md-6"><h5>Números de la Participación</h5><div class="numbers-grid">' . $numbersHtml . '</div></div>'
        . '<div class="col-md-6"><h5>Información del Ticket</h5>'
        . '<p><strong>Referencia:</strong> ' . partilot_escape($data['participation_number'] ?? 'N/A') . '</p>'
        . '<p><strong>Participación:</strong> ' . partilot_escape($data['participation_code'] ?? 'N/A') . '</p>'
        . '<p><strong>Precio:</strong> ' . number_format((float) ($set['played_amount'] ?? 0), 2) . '€</p>'
        . '</div></div>'
        . $prizeHtml;

    header('Cache-Control: public, max-age=60');
    partilot_layout('Resultado', $body, $config);
}
