<?php

namespace App\Console\Commands;

use App\Models\Administration;
use App\Services\AdministrationContractService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class PreviewAdministrationContractCommand extends Command
{
    protected $signature = 'contracts:preview-administration {id : ID de la administración} {--output= : Ruta de salida del PDF}';

    protected $description = 'Genera una vista previa PDF del contrato SaaS de administración';

    public function handle(AdministrationContractService $service): int
    {
        $administration = Administration::query()->find($this->argument('id'));
        if (! $administration) {
            $this->error('Administración no encontrada.');

            return self::FAILURE;
        }

        $binary = $service->previewPdfBinary($administration);
        $output = $this->option('output')
            ?: storage_path('app/contracts/preview-administration-'.$administration->id.'.pdf');

        File::ensureDirectoryExists(dirname($output));
        File::put($output, $binary);

        $this->info('PDF generado: '.$output);

        return self::SUCCESS;
    }
}
