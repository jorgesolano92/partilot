<?php

namespace App\Console\Commands;

use App\Services\Qa\PrizePaymentQaReportWriter;
use App\Services\Qa\PrizePaymentQaRunner;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class QaPrizePaymentsCommand extends Command
{
    protected $signature = 'qa:prize-payments
                            {--bootstrap : Crear datos temporales QA y ejecutar pruebas completas}
                            {--entity= : ID entidad (modo datos existentes)}
                            {--lottery= : ID sorteo (modo datos existentes)}
                            {--user= : ID usuario cartera (modo datos existentes)}
                            {--report= : Ruta del informe markdown (por defecto proceso_cobro/resultados_qa_cobro_premios.md)}
                            {--with-phpunit : Incluir salida de php artisan test --testsuite=PrizePayment en el informe}';

    protected $description = 'Ejecuta checks automatizados de la guía de cobro de premios (proceso_cobro/guia_pruebas_cobro_premios.md)';

    public function handle(PrizePaymentQaRunner $runner, PrizePaymentQaReportWriter $writer): int
    {
        $bootstrap = (bool) $this->option('bootstrap');
        $entity = $this->option('entity');
        $lottery = $this->option('lottery');
        $user = $this->option('user');
        $reportPath = $this->option('report')
            ?: base_path('proceso_cobro/resultados_qa_cobro_premios.md');

        if (! $bootstrap && (! $entity || ! $lottery)) {
            $this->warn('Modo recomendado: php artisan qa:prize-payments --bootstrap');
            $this->line('Modo datos existentes: --entity=ID --lottery=ID [--user=ID]');
        }

        $this->info('=== QA Cobro de premios ===');
        $this->newLine();

        $results = $runner->run([
            'bootstrap' => $bootstrap,
            'entity' => $entity,
            'lottery' => $lottery,
            'user' => $user,
        ]);

        $currentSection = null;
        foreach ($results as $row) {
            if ($row['section'] !== $currentSection) {
                $currentSection = $row['section'];
                $this->line("<fg=cyan>[{$currentSection}]</>");
            }

            $status = $row['passed'] ? '<fg=green>[OK]</>' : '<fg=red>[FAIL]</>';
            $this->line("  {$status}  {$row['id']}  {$row['name']}");
            if ($row['message'] !== '' && $row['message'] !== 'OK') {
                $this->line("         {$row['message']}");
            }
        }

        $this->newLine();
        $passed = $runner->passedCount();
        $failed = $runner->failedCount();
        $total = count($results);

        $this->info("Resultado: {$passed}/{$total} OK, {$failed} fallos");

        $phpunitOutput = null;
        $phpunitExit = 0;
        if ($this->option('with-phpunit')) {
            $this->newLine();
            $this->info('Ejecutando PHPUnit (testsuite PrizePayment)...');
            $process = new Process(
                [PHP_BINARY, base_path('vendor/bin/phpunit'), '--testsuite', 'PrizePayment', '--colors=never'],
                base_path(),
                null,
                null,
                300
            );
            $process->run();
            $phpunitOutput = trim($process->getOutput()."\n".$process->getErrorOutput());
            $phpunitExit = $process->getExitCode() ?? 1;
            $this->line($phpunitOutput);
        }

        $writer->write($reportPath, $results, $passed, $failed, [
            'date' => now()->format('Y-m-d H:i:s'),
            'env' => app()->environment(),
            'command' => 'php artisan qa:prize-payments'.($bootstrap ? ' --bootstrap' : '').($this->option('with-phpunit') ? ' --with-phpunit' : ''),
            'bootstrap' => $bootstrap,
            'phpunit' => $phpunitOutput,
            'phpunit_passed' => $this->option('with-phpunit') ? ($phpunitExit === 0) : null,
        ]);

        $this->newLine();
        $this->info("Informe guardado en: {$reportPath}");

        $exitFailed = $failed > 0 || ($this->option('with-phpunit') && $phpunitExit !== 0);

        return $exitFailed ? self::FAILURE : self::SUCCESS;
    }
}
