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
    $prize = $ticket['prize_info'] ?? null;
    $drawStatus = (string) ($ticket['draw_status'] ?? '');
    $previewUrl = (string) ($ticket['preview_image_url'] ?? '');

    $drawDate = $lottery['draw_date'] ?? null;
    $drawFormatted = 'N/A';
    if ($drawDate) {
        try {
            // Preferir fecha civil Y-m-d (sin hora) para no restar un día por UTC.
            $raw = (string) $drawDate;
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $raw, $m)) {
                $drawFormatted = $m[3] . '-' . $m[2] . '-' . $m[1];
            } else {
                $drawFormatted = (new DateTimeImmutable($raw))->format('d-m-Y');
            }
        } catch (Exception) {
            $drawFormatted = partilot_escape((string) $drawDate);
        }
    }

    $numbers = $reserve['reservation_numbers'] ?? [];
    $numbersHtml = '';
    foreach ($numbers as $number) {
        $padded = str_pad((string) $number, 5, '0', STR_PAD_LEFT);
        $numbersHtml .= '<div class="number-box">' . $padded . '</div>';
    }

    $drawNumber = trim((string) ($lottery['draw_number'] ?? $lottery['name'] ?? ''));
    if ($drawNumber === '') {
        $drawNumber = 'N/A';
    }

    $played = (float) ($set['played_amount'] ?? 0);
    $donation = (float) ($set['donation_amount'] ?? 0);
    $amountLabel = (string) ($set['amount_label'] ?? '');
    if ($amountLabel === '') {
        $total = (float) ($set['total_amount'] ?? ($played + $donation));
        $amountLabel = number_format($total, 2, ',', '.') . '€';
    }
    // Por si llega una etiqueta antigua con "(4+1)".
    $amountLabel = preg_replace('/\s*\([^)]*\)\s*$/', '', $amountLabel) ?: $amountLabel;

    // Solo <img>: ModSecurity/COMODO (regla 214540) bloquea iframes en la salida HTML.
    $previewHtml = '';
    if ($previewUrl !== '') {
        $safePreview = partilot_escape($previewUrl);
        $previewHtml = '<div class="preview-wrap">'
            . '<img src="' . $safePreview . '" alt="Participación" class="preview-img"'
            . ' onerror="this.closest(\'.preview-wrap\').style.display=\'none\'">'
            . '</div>';
    }

    $detailsHtml = '<div class="details-card">'
        . '<h5>' . partilot_escape($lottery['name'] ?? 'Sorteo') . '</h5>'
        . '<div class="detail-row"><span>Fecha del sorteo</span><strong>' . partilot_escape($drawFormatted) . '</strong></div>'
        . '<div class="detail-row"><span>Entidad</span><strong>' . partilot_escape($reserve['entity']['name'] ?? 'N/A') . '</strong></div>'
        . '<div class="detail-row"><span>Nº de sorteo</span><strong>'
        . partilot_escape($drawNumber)
        . '</strong></div>'
        . '<div class="detail-row"><span>Participación</span><strong>'
        . partilot_escape($data['participation_code'] ?? 'N/A')
        . '</strong></div>'
        . '</div>';

    $amountHtml = '<div class="details-card text-center">'
        . '<h5>Importe de la participación</h5>'
        . '<div class="amount-hero">' . partilot_escape($amountLabel) . '</div>';
    if ($donation > 0 && $played > 0) {
        $amountHtml .= '<p class="amount-sub">Jugado ' . number_format($played, 2, ',', '.')
            . '€ + donativo ' . number_format($donation, 2, ',', '.') . '€</p>';
    }
    $amountHtml .= '</div>';

    $numbersSection = $numbersHtml !== ''
        ? '<div class="numbers-grid" style="justify-content:center;margin-bottom:1rem;">' . $numbersHtml . '</div>'
        : '';

    $statusHtml = '';
    $isPending = in_array($drawStatus, ['pending_celebration', 'pending_results'], true)
        || $prize === null
        || $prize === [];

    if ($isPending) {
        if ($drawStatus === 'pending_celebration') {
            $statusHtml = '<div class="status-box status-pending">'
                . '<h4>Sorteo pendiente de celebración</h4>'
                . '<p>El sorteo aún no se ha celebrado. Vuelve a consultar después de la fecha del sorteo para ver si tu participación tiene premio.</p>'
                . '<span class="badge-pill">SORTEO NO CELEBRADO</span></div>';
        } else {
            $statusHtml = '<div class="status-box status-pending">'
                . '<h4>Resultados pendientes</h4>'
                . '<p>El sorteo ya tiene fecha, pero los resultados aún no están publicados.</p>'
                . '<span class="badge-pill">RESULTADOS PENDIENTES</span></div>';
        }
    } elseif (! empty($prize['has_won'])) {
        $amount = number_format((float) ($prize['prize_amount'] ?? 0), 2, ',', '.');
        $statusHtml = '<div class="status-box status-winner">'
            . '<h4>¡Felicidades!</h4>'
            . '<div class="prize-amount">' . $amount . '€</div>'
            . '<p>Premio por participación</p></div>';
    } else {
        $statusHtml = '<div class="status-box status-no-prize">'
            . '<h4>Sin premio</h4>'
            . '<p>Esta participación no ha resultado premiada en este sorteo.</p></div>';
    }

    $body = $previewHtml . $detailsHtml . $amountHtml . $numbersSection . $statusHtml;

    header('Cache-Control: public, max-age=60');
    partilot_layout('Resultado', $body, $config);
}
