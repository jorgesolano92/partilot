<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\NavidadScrapingService;

class TestNavidadScraping extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:navidad-scraping {drawId}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Probar el scraping de pedreas para sorteos de Navidad';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $drawId = $this->argument('drawId');
        
        $this->info("Probando scraping de pedreas para sorteo de Navidad: $drawId");
        
        $scrapingService = new NavidadScrapingService();
        $obtained = $scrapingService->obtainPedreas($drawId);

        $this->line('Tablas URL: ' . ($obtained['sources']['tablas_url'] ?? '-'));
        $this->line('Listado oficial: ' . ($obtained['sources']['listado_oficial_url'] ?? 'no disponible'));

        if (!$obtained['ok']) {
            $this->error('Error: ' . ($obtained['error'] ?? 'desconocido'));
            return 1;
        }

        $pedreas = $obtained['pedreas'];
        $this->info('Pedreas encontradas: ' . count($pedreas));

        if (count($pedreas) > 0) {
            $this->info('Primeras 10 pedreas:');
            foreach (array_slice($pedreas, 0, 10) as $index => $pedrea) {
                $this->line('  ' . ($index + 1) . '. ' . $pedrea);
            }

            if (count($pedreas) > 10) {
                $this->info('... y ' . (count($pedreas) - 10) . ' más');
            }
        } else {
            $this->warn('No se encontraron pedreas');
        }

        $formattedPedreas = $scrapingService->formatPedreasForSystem($pedreas);
        $this->info('Pedreas formateadas: ' . count($formattedPedreas));

        if (count($formattedPedreas) > 0) {
            $this->info('Primeras 5 pedreas formateadas:');
            foreach (array_slice($formattedPedreas, 0, 5) as $index => $pedrea) {
                $this->line('  ' . ($index + 1) . '. Número: ' . $pedrea['decimo'] . ', Premio: ' . $pedrea['prize'] . '€');
            }
        }
        
        return 0;
    }
}
