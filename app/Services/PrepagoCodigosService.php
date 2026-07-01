<?php

namespace App\Services;

use App\Models\Administration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PrepagoCodigosService
{
    /**
     * @return array{url: string, apikey: string, prefijo: string, n_codigos: int, tamano_cadena: int, accion: string, source: string}|null
     */
    public function resolveConfig(?Administration $administration): ?array
    {
        if ($administration && $this->hasOwnConfig($administration)) {
            return [
                'url' => trim((string) $administration->prepago_api_url),
                'apikey' => (string) $administration->prepago_api_key,
                'prefijo' => trim((string) $administration->prepago_api_prefix),
                'n_codigos' => (int) config('services.prepago_codigos.n_codigos', 1),
                'tamano_cadena' => (int) config('services.prepago_codigos.tamano_cadena', 8),
                'accion' => (string) config('services.prepago_codigos.accion', 'generarCodigosRnd'),
                'source' => 'administration',
            ];
        }

        if ($administration?->prepago_use_partilot_default && $this->partilotConfigIsComplete()) {
            return $this->partilotConfig();
        }

        return null;
    }

    public function canGenerateCodes(?Administration $administration): bool
    {
        return $this->resolveConfig($administration) !== null;
    }

    public function hasOwnConfig(Administration $administration): bool
    {
        if (! $administration->prepago_integration_enabled) {
            return false;
        }

        return trim((string) $administration->prepago_api_url) !== ''
            && trim((string) $administration->prepago_api_prefix) !== ''
            && trim((string) $administration->prepago_api_key) !== '';
    }

    public function partilotConfigIsComplete(): bool
    {
        $config = $this->partilotConfig();

        return $config !== null;
    }

    /**
     * @return array{url: string, apikey: string, prefijo: string, n_codigos: int, tamano_cadena: int, accion: string, source: string}|null
     */
    private function partilotConfig(): ?array
    {
        $url = trim((string) config('services.prepago_codigos.url'));
        $apikey = trim((string) config('services.prepago_codigos.apikey'));
        $prefijo = trim((string) config('services.prepago_codigos.prefijo', 'c-'));

        if ($url === '' || $apikey === '' || $prefijo === '') {
            return null;
        }

        return [
            'url' => $url,
            'apikey' => $apikey,
            'prefijo' => $prefijo,
            'n_codigos' => (int) config('services.prepago_codigos.n_codigos', 1),
            'tamano_cadena' => (int) config('services.prepago_codigos.tamano_cadena', 8),
            'accion' => (string) config('services.prepago_codigos.accion', 'generarCodigosRnd'),
            'source' => 'partilot',
        ];
    }

    public function generateCode(?Administration $administration, float $importe): ?string
    {
        $importe = round($importe, 2);
        if ($importe <= 0) {
            return null;
        }

        $maxAmount = (float) config('prize_wallet.max_prepago_code_amount', 10000);
        if ($importe > $maxAmount) {
            Log::warning('Prepago códigos: importe supera el límite permitido', [
                'administration_id' => $administration?->id,
                'importe' => $importe,
                'max' => $maxAmount,
            ]);

            return null;
        }

        $config = $this->resolveConfig($administration);
        if (! $config) {
            Log::warning('Prepago códigos: sin configuración para la administración', [
                'administration_id' => $administration?->id,
            ]);

            return null;
        }

        $importeStr = number_format($importe, 2, '.', '');
        $importeStr = rtrim(rtrim($importeStr, '0'), '.');

        $response = Http::timeout(15)->get($config['url'], [
            'apikey' => $config['apikey'],
            'n_codigos' => $config['n_codigos'],
            'tamano_cadena' => $config['tamano_cadena'],
            'importe' => $importeStr,
            'prefijo' => $config['prefijo'],
            'accion' => $config['accion'],
        ]);

        if (! $response->successful()) {
            Log::warning('Prepago códigos: request fallido', [
                'administration_id' => $administration?->id,
                'source' => $config['source'],
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $data = $response->json();
        if (empty($data['status']) || (int) $data['status'] !== 1 || empty($data['codigos']) || ! is_array($data['codigos'])) {
            Log::warning('Prepago códigos: respuesta inválida', [
                'administration_id' => $administration?->id,
                'source' => $config['source'],
                'response' => $data,
            ]);

            return null;
        }

        $codigo = $data['codigos'][0] ?? null;

        return is_string($codigo) && $codigo !== '' ? $codigo : null;
    }
}
