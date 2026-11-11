<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use DOMDocument;
use DOMXPath;

class NavidadScrapingService
{
    public static function tablasAlambresUrl($drawId): string
    {
        return 'https://www.loteriasyapuestas.es/es/loteria-nacional/tablas-y-alambres?drawId=' . urlencode((string) $drawId);
    }

    /**
     * Intenta obtener pedreas y metadatos de fuentes oficiales SELAE.
     *
     * La API JSON de resultados (resultados2) NO incluye pedreas; la fuente usable
     * es el HTML de tablas-y-alambres (SSR). El listado oficial PDF sirve de respaldo manual.
     *
     * @return array{ok: bool, pedreas: array, error: ?string, sources: array{tablas_url: string, listado_oficial_url: ?string}}
     */
    public function obtainPedreas($drawId): array
    {
        $sources = [
            'tablas_url' => self::tablasAlambresUrl($drawId),
            'listado_oficial_url' => $this->resolveListadoOficialUrl($drawId),
        ];

        try {
            $pedreas = $this->getPedreasFromNavidadSorteo($drawId);

            if (count($pedreas) === 0) {
                return [
                    'ok' => false,
                    'pedreas' => [],
                    'error' => 'La página de tablas y alambres respondió pero no se extrajo ninguna pedrea.',
                    'sources' => $sources,
                ];
            }

            return [
                'ok' => true,
                'pedreas' => $pedreas,
                'error' => null,
                'sources' => $sources,
            ];
        } catch (\Exception $e) {
            return [
                'ok' => false,
                'pedreas' => [],
                'error' => $e->getMessage(),
                'sources' => $sources,
            ];
        }
    }

    /**
     * Obtener pedreas de un sorteo de Navidad mediante web scraping
     */
    public function getPedreasFromNavidadSorteo($drawId)
    {
        try {
            Log::info("Iniciando scraping de pedreas para sorteo de Navidad: $drawId");

            $url = self::tablasAlambresUrl($drawId);

            // Realizar petición HTTP
            $response = Http::withOptions(['verify' => config('app.http_verify_ssl')])
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language' => 'es-ES,es;q=0.9,en;q=0.8',
                'Referer' => 'https://www.loteriasyapuestas.es/',
                'Connection' => 'keep-alive',
                'Upgrade-Insecure-Requests' => '1',
            ])
            ->timeout(30)
            ->get($url);

            if (!$response->successful()) {
                $hint = $response->status() === 403
                    ? ' (SELAE/Akamai bloquea la petición desde el servidor; use el enlace de tablas y alambres o el listado oficial PDF)'
                    : '';
                throw new \Exception('Error HTTP: ' . $response->status() . $hint);
            }

            $html = $response->body();
            Log::info('HTML obtenido, longitud: ' . strlen($html) . ' caracteres');

            // Parsear HTML para extraer pedreas
            $pedreas = $this->parsePedreasFromHTML($html);

            Log::info('Pedreas extraídas: ' . count($pedreas));

            return $pedreas;

        } catch (\Exception $e) {
            Log::error('Error en scraping de pedreas: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * URL del PDF de listado oficial (si resultados2 es accesible).
     */
    public function resolveListadoOficialUrl($drawId): ?string
    {
        try {
            $response = Http::withOptions(['verify' => config('app.http_verify_ssl')])
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept' => 'application/json, text/plain, */*',
                    'Accept-Language' => 'es-ES,es;q=0.9',
                    'Referer' => 'https://www.loteriasyapuestas.es/',
                ])
                ->timeout(15)
                ->get('https://www.loteriasyapuestas.es/servicios/resultados2', [
                    'idsorteo' => $drawId,
                ]);

            if (!$response->successful()) {
                return null;
            }

            $data = $response->json();
            $path = $data['urlListadoOficial'] ?? null;
            if (!is_string($path) || $path === '') {
                return null;
            }

            if (str_starts_with($path, 'http')) {
                return $path;
            }

            return 'https://www.loteriasyapuestas.es' . $path;
        } catch (\Throwable $e) {
            Log::info('No se pudo resolver urlListadoOficial: ' . $e->getMessage());

            return null;
        }
    }
    
    /**
     * Parsear HTML para extraer las pedreas
     */
    private function parsePedreasFromHTML($html)
    {
        $pedreas = [];
        
        try {
            // Crear DOMDocument
            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadHTML($html);
            libxml_clear_errors();
            
            $xpath = new DOMXPath($dom);
            
            // Buscar diferentes patrones de pedreas
            $pedreas = array_merge(
                $this->extractPedreasFromTables($xpath),
                $this->extractPedreasFromLists($xpath),
                $this->extractPedreasFromDivs($xpath)
            );
            
            // Eliminar duplicados
            $pedreas = array_unique($pedreas, SORT_REGULAR);
            
            Log::info("Pedreas parseadas: " . count($pedreas));
            
        } catch (\Exception $e) {
            Log::error("Error parseando HTML: " . $e->getMessage());
        }
        
        return $pedreas;
    }
    
    /**
     * Extraer pedreas de tablas HTML
     */
    private function extractPedreasFromTables($xpath)
    {
        $pedreas = [];
        
        // Buscar números de 5 dígitos en tablas
        $tableSelectors = [
            '//table//td',
            '//table//th',
            '//div[contains(@class, "tabla")]//td',
            '//div[contains(@class, "alambre")]//td',
        ];
        
        foreach ($tableSelectors as $selector) {
            $nodes = $xpath->query($selector);
            foreach ($nodes as $node) {
                $text = trim($node->textContent);
                
                // Buscar números de 5 dígitos
                if (preg_match_all('/\b\d{5}\b/', $text, $matches)) {
                    foreach ($matches[0] as $numero) {
                        if ($this->isValidPedreaNumber($numero)) {
                            $pedreas[] = $numero;
                        }
                    }
                }
            }
        }
        
        return $pedreas;
    }
    
    /**
     * Extraer pedreas de listas HTML
     */
    private function extractPedreasFromLists($xpath)
    {
        $pedreas = [];
        
        // Buscar en listas
        $listSelectors = [
            '//ul//li[contains(@class, "pedrea")]',
            '//ol//li[contains(@class, "pedrea")]',
            '//ul//li[contains(text(), "pedrea")]',
            '//ol//li[contains(text(), "pedrea")]',
        ];
        
        foreach ($listSelectors as $selector) {
            $nodes = $xpath->query($selector);
            foreach ($nodes as $node) {
                $text = trim($node->textContent);
                if ($this->isValidPedrea($text)) {
                    $pedreas[] = $text;
                }
            }
        }
        
        return $pedreas;
    }
    
    /**
     * Extraer pedreas de divs HTML
     */
    private function extractPedreasFromDivs($xpath)
    {
        $pedreas = [];
        
        // Buscar en divs
        $divSelectors = [
            '//div[contains(@class, "pedrea")]',
            '//div[contains(@class, "premio-menor")]',
            '//div[contains(@class, "alambre")]',
            '//div[contains(@id, "pedrea")]',
            '//div[contains(@id, "premios-menores")]',
        ];
        
        foreach ($divSelectors as $selector) {
            $nodes = $xpath->query($selector);
            foreach ($nodes as $node) {
                $text = trim($node->textContent);
                if ($this->isValidPedrea($text)) {
                    $pedreas[] = $text;
                }
            }
        }
        
        return $pedreas;
    }
    
    /**
     * Validar si un texto es una pedrea válida
     */
    private function isValidPedrea($text)
    {
        // Limpiar texto
        $text = trim($text);
        
        // Verificar que no esté vacío
        if (empty($text)) {
            return false;
        }
        
        // Verificar que contenga números (las pedreas tienen números)
        if (!preg_match('/\d/', $text)) {
            return false;
        }
        
        // Verificar que no sea un premio principal
        $premiosPrincipales = [
            '1er Premio', '1º Premio', 'Primer Premio',
            '2º Premio', 'Segundo Premio',
            '3º Premio', 'Tercer Premio',
            '4º Premio', 'Cuarto Premio',
            '5º Premio', 'Quinto Premio',
            'Reintegro'
        ];
        
        foreach ($premiosPrincipales as $premio) {
            if (stripos($text, $premio) !== false) {
                return false;
            }
        }
        
        // Verificar que sea una pedrea (premio menor)
        $indicadoresPedrea = [
            'pedrea', 'alambre', 'premio menor', 'premio-menor',
            'menor', 'pequeño', 'pequeña'
        ];
        
        foreach ($indicadoresPedrea as $indicador) {
            if (stripos($text, $indicador) !== false) {
                return true;
            }
        }
        
        // Si contiene solo números y es un premio pequeño, podría ser pedrea
        if (preg_match('/^\d+$/', $text) && strlen($text) <= 5) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Validar si un número es una pedrea válida
     */
    private function isValidPedreaNumber($numero)
    {
        // Verificar que sea un número de 5 dígitos
        if (!preg_match('/^\d{5}$/', $numero)) {
            return false;
        }
        
        // No filtrar aquí los premios principales, se hará en el controlador
        // para tener acceso a los datos reales del sorteo
        return true;
    }
    
    /**
     * Formatear pedreas para el formato esperado por el sistema
     */
    public function formatPedreasForSystem($pedreas)
    {
        $formattedPedreas = [];
        
        foreach ($pedreas as $pedrea) {
            // Si ya es un número de 5 dígitos, usarlo directamente
            if (preg_match('/^\d{5}$/', $pedrea)) {
                $numero = $pedrea;
            } else {
                // Extraer número de la pedrea
                if (preg_match('/(\d{5})/', $pedrea, $matches)) {
                    $numero = $matches[1];
                } else {
                    continue; // Saltar si no se puede extraer un número
                }
            }
            
            // Las pedreas suelen tener premio de 1.000€
            $premio = 1000;
            
            $formattedPedreas[] = [
                'decimo' => $numero,
                'prize' => $premio,
                'tipo' => 'pedrea',
                'categoria' => 'Pedrea',
                'literalPremio' => [
                    'es' => 'Pedrea',
                    'ca' => 'Pedrea',
                    'en' => 'Pedrea',
                    'eu' => 'Pedrea',
                    'gl' => 'Pedrea',
                    'va' => 'Pedrea'
                ]
            ];
        }
        
        return $formattedPedreas;
    }
}
