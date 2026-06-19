<?php

namespace App\Services\Qa;

class PrizePaymentQaReportWriter
{
    /**
     * @param  list<array{id: string, section: string, name: string, passed: bool, message: string}>  $results
     */
    public function write(string $path, array $results, int $passed, int $failed, array $meta = []): void
    {
        $total = count($results);
        $lines = [];
        $lines[] = '# Resultados QA — Cobro de premios';
        $lines[] = '';
        $lines[] = '| Campo | Valor |';
        $lines[] = '|-------|-------|';
        $lines[] = '| Fecha | '.($meta['date'] ?? now()->format('Y-m-d H:i:s')).' |';
        $lines[] = '| Entorno | '.($meta['env'] ?? app()->environment()).' |';
        $lines[] = '| Comando | `'.($meta['command'] ?? 'qa:prize-payments').'` |';
        $lines[] = '| Bootstrap | '.(($meta['bootstrap'] ?? false) ? 'sí' : 'no').' |';
        $lines[] = '| Resumen | **'.$passed.'/'.$total.' OK**, '.$failed.' fallos |';
        $lines[] = '| Estado global | '.($failed === 0 ? '**PASS**' : '**FAIL**').' |';
        $lines[] = '';

        if ($failed > 0) {
            $lines[] = '## Fallos';
            $lines[] = '';
            foreach ($results as $row) {
                if ($row['passed']) {
                    continue;
                }
                $lines[] = '- **'.$row['id'].'** ('.$row['section'].'): '.$row['name'];
                if ($row['message'] !== '' && $row['message'] !== 'OK') {
                    $lines[] = '  - '.$row['message'];
                }
            }
            $lines[] = '';
        }

        $lines[] = '## Detalle por sección';
        $lines[] = '';
        $lines[] = '| ID | Sección | Check | Resultado | Detalle |';
        $lines[] = '|----|---------|-------|-----------|---------|';

        foreach ($results as $row) {
            $status = $row['passed'] ? 'OK' : 'FAIL';
            $detail = str_replace('|', '\\|', $row['message'] !== 'OK' ? $row['message'] : '—');
            $lines[] = sprintf(
                '| %s | %s | %s | %s | %s |',
                $row['id'],
                $row['section'],
                $row['name'],
                $status,
                $detail
            );
        }

        if (! empty($meta['phpunit'])) {
            $lines[] = '';
            $lines[] = '## PHPUnit (testsuite PrizePayment)';
            $lines[] = '';
            if (isset($meta['phpunit_passed'])) {
                $lines[] = 'Estado: '.($meta['phpunit_passed'] ? '**PASS**' : '**FAIL**');
                $lines[] = '';
            }
            $lines[] = '```';
            $lines[] = trim($meta['phpunit']);
            $lines[] = '```';
        }

        $lines[] = '';
        $lines[] = '_Generado automáticamente. Ver `proceso_cobro/guia_pruebas_cobro_premios.md` sección J._';

        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, implode("\n", $lines)."\n");
    }
}
