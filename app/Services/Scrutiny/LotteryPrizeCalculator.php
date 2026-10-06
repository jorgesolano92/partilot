<?php

namespace App\Services\Scrutiny;

/**
 * Reglas de escrutinio de Lotería Nacional para un número de 5 cifras.
 *
 * Importes en € al billete (serie), tal como están en config/lotteryCategories.php;
 * el premio al décimo es el importe / 10.
 */
class LotteryPrizeCalculator
{
    public const TYPE_MAIN = 'main';
    public const TYPE_DERIVED = 'derived';
    public const TYPE_EXTRACTION = 'extraction';
    public const TYPE_REINTEGRO = 'reintegro';
    public const TYPE_PEDREA = 'pedrea';

    private const MAIN_PRIZES = [
        'primer_premio' => ['key' => 'primerPremio', 'name' => 'Primer Premio', 'multiple' => false],
        'segundo_premio' => ['key' => 'segundoPremio', 'name' => 'Segundo Premio', 'multiple' => false],
        'terceros_premios' => ['key' => 'tercerosPremios', 'name' => 'Tercer Premio', 'multiple' => true],
        'cuartos_premios' => ['key' => 'cuartosPremios', 'name' => 'Cuarto Premio', 'multiple' => true],
        'quintos_premios' => ['key' => 'quintosPremios', 'name' => 'Quinto Premio', 'multiple' => true],
    ];

    private const NEIGHBOURS = [
        'primer_premio' => ['anteriorPrimerPremio', 'posteriorPrimerPremio', 'Primer'],
        'segundo_premio' => ['anteriorSegundoPremio', 'posteriorSegundoPremio', 'Segundo'],
        'terceros_premios' => ['anteriorTercerosPremios', 'posteriorTercerosPremios', 'Tercer'],
        'cuartos_premios' => ['anteriorCuartosPremios', 'posteriorCuartosPremios', 'Cuarto'],
        'quintos_premios' => ['anteriorQuintosPremios', 'posteriorQuintosPremios', 'Quinto'],
    ];

    private const CENTENAS = [
        'primer_premio' => ['centenasPrimerPremio', 'Centenas del Primer Premio'],
        'segundo_premio' => ['centenasSegundoPremio', 'Centenas del Segundo Premio'],
        'terceros_premios' => ['centenasTercerosPremios', 'Centenas del Tercer Premio'],
        'cuartos_premios' => ['centenasCuartosPremios', 'Centenas del Cuarto Premio'],
    ];

    /** [campo del resultado, cifras, clave de config, nombre] */
    private const TERMINACIONES = [
        ['primer_premio', 4, 'cuatroUltimasCifrasPrimerPremio', '4 Últimas Cifras del Primer Premio'],
        ['primer_premio', 3, 'tresUltimasCifrasPrimerPremio', '3 Últimas Cifras del Primer Premio'],
        ['primer_premio', 2, 'dosUltimasCifrasPrimerPremio', '2 Últimas Cifras del Primer Premio'],
        ['primer_premio', 1, 'ultimaCifraPrimerPremio', 'Última Cifra del Primer Premio'],
        ['segundo_premio', 3, 'tresUltimasCifrasSegundoPremio', '3 Últimas Cifras del Segundo Premio'],
        ['segundo_premio', 2, 'dosUltimasCifrasSegundoPremio', '2 Últimas Cifras del Segundo Premio'],
        ['terceros_premios', 2, 'dosUltimasCifrasTercerPremio', '2 Últimas Cifras del Tercer Premio'],
    ];

    private const EXTRACTIONS = [
        'extracciones_cinco_cifras' => [5, 'extraccionesDeCincoCifras', 'Extracción de 5 Cifras'],
        'extracciones_cuatro_cifras' => [4, 'extraccionesDeCuatroCifras', 'Extracción de 4 Cifras'],
        'extracciones_tres_cifras' => [3, 'extraccionesDeTresCifras', 'Extracción de 3 Cifras'],
        'extracciones_dos_cifras' => [2, 'extraccionesDeDosCifras', 'Extracción de 2 Cifras'],
    ];

    /** @var array<string, array<string, int|float>> */
    private array $amounts = [];

    private ?object $preparedFor = null;

    private array $prepared = [];

    public function __construct(?array $categories = null)
    {
        foreach ($categories ?? config('lotteryCategories', []) as $category) {
            if (isset($category['key_categoria'])) {
                $this->amounts[$category['key_categoria']] = $category['importe_por_tipo'] ?? [];
            }
        }
    }

    public function amount(string $key, string $typeIdentifier): float
    {
        return (float) ($this->amounts[$key][$typeIdentifier] ?? 0);
    }

    /**
     * @param  object  $result  LotteryResult (o cualquier objeto con los mismos atributos)
     * @return array{number: string, total_prize: float, prizes: list<array{key: string, name: string, type: string, amount: float}>}
     */
    public function calculate($number, object $result, string $typeIdentifier): array
    {
        $number = self::pad($number);
        $draw = $this->prepare($result);
        $prizes = [];

        $add = function (string $key, string $name, string $type) use (&$prizes, $typeIdentifier) {
            $amount = $this->amount($key, $typeIdentifier);
            if ($amount > 0) {
                $prizes[] = ['key' => $key, 'name' => $name, 'type' => $type, 'amount' => $amount];
            }
        };

        // Premios principales: no acumulan entre sí
        foreach (self::MAIN_PRIZES as $field => $meta) {
            if (in_array($number, $draw['prizes'][$field], true)) {
                $add($meta['key'], $meta['name'], self::TYPE_MAIN);
                break;
            }
        }

        foreach (self::CENTENAS as $field => [$key, $name]) {
            foreach ($draw['prizes'][$field] as $prizeNumber) {
                if ($number !== $prizeNumber && substr($number, 0, 3) === substr($prizeNumber, 0, 3)) {
                    $add($key, $name, self::TYPE_DERIVED);
                }
            }
        }

        foreach (self::NEIGHBOURS as $field => [$beforeKey, $afterKey, $label]) {
            foreach ($draw['prizes'][$field] as $prizeNumber) {
                if ($number === self::shift($prizeNumber, -1)) {
                    $add($beforeKey, "Anterior al {$label} Premio", self::TYPE_DERIVED);
                }
                if ($number === self::shift($prizeNumber, 1)) {
                    $add($afterKey, "Posterior al {$label} Premio", self::TYPE_DERIVED);
                }
            }
        }

        $hasUltimaCifra = false;
        foreach (self::TERMINACIONES as [$field, $digits, $key, $name]) {
            foreach ($draw['prizes'][$field] as $prizeNumber) {
                if ($number !== $prizeNumber && substr($number, -$digits) === substr($prizeNumber, -$digits)) {
                    $add($key, $name, self::TYPE_DERIVED);
                    if ($key === 'ultimaCifraPrimerPremio') {
                        $hasUltimaCifra = true;
                    }
                }
            }
        }

        // Cada extracción cuenta aunque la bola salga repetida
        foreach (self::EXTRACTIONS as $field => [$digits, $key, $name]) {
            foreach ($draw['extractions'][$field] as $extraction) {
                if (substr($number, -$digits) === $extraction) {
                    $add($key, $name, self::TYPE_EXTRACTION);
                }
            }
        }

        // El reintegro no se suma al primer premio ni a quien ya cobra la última cifra del primer premio
        $isFirstPrize = in_array($number, $draw['prizes']['primer_premio'], true);
        if (! $isFirstPrize && ! $hasUltimaCifra && isset($draw['reintegros'][substr($number, -1)])) {
            $add('reintegros', 'Reintegro', self::TYPE_REINTEGRO);
        }

        if (isset($draw['pedreas'][$number])) {
            $add('pedrea', 'Pedrea', self::TYPE_PEDREA);
        }

        return [
            'number' => $number,
            'total_prize' => array_sum(array_column($prizes, 'amount')),
            'prizes' => $prizes,
        ];
    }

    /**
     * Números que pueden tener algún premio; el resto del bombo no cobra nada.
     *
     * @return list<string>
     */
    public function candidateNumbers(object $result): array
    {
        $numbers = [];

        foreach (array_keys(self::MAIN_PRIZES) as $field) {
            foreach ($this->prizeNumbers($result, $field) as $prizeNumber) {
                $numbers[$prizeNumber] = true;
                $numbers[self::shift($prizeNumber, -1)] = true;
                $numbers[self::shift($prizeNumber, 1)] = true;
            }
        }

        foreach (array_keys(self::CENTENAS) as $field) {
            foreach ($this->prizeNumbers($result, $field) as $prizeNumber) {
                $this->addEndingWith($numbers, '', substr($prizeNumber, 0, 3));
            }
        }

        foreach (self::TERMINACIONES as [$field, $digits]) {
            foreach ($this->prizeNumbers($result, $field) as $prizeNumber) {
                $this->addEndingWith($numbers, substr($prizeNumber, -$digits));
            }
        }

        foreach (self::EXTRACTIONS as $field => [$digits]) {
            foreach ($this->list($result, $field) as $extraction) {
                if (isset($extraction['decimo'])) {
                    $this->addEndingWith($numbers, self::digits($extraction['decimo'], $digits));
                }
            }
        }

        foreach ($this->list($result, 'reintegros') as $reintegro) {
            if (isset($reintegro['decimo'])) {
                $this->addEndingWith($numbers, substr((string) $reintegro['decimo'], -1));
            }
        }

        foreach ($this->list($result, 'pedreas') as $pedrea) {
            if (isset($pedrea['decimo'])) {
                $numbers[self::pad($pedrea['decimo'])] = true;
            }
        }

        $numbers = array_map('strval', array_keys($numbers));
        sort($numbers, SORT_STRING);

        return $numbers;
    }

    public static function pad($number): string
    {
        return str_pad((string) (int) $number, 5, '0', STR_PAD_LEFT);
    }

    /** Número contiguo en el bombo circular 00000-99999. */
    public static function shift(string $number, int $offset): string
    {
        return self::pad(((int) $number + $offset + 100000) % 100000);
    }

    private static function digits($value, int $digits): string
    {
        return str_pad((string) $value, $digits, '0', STR_PAD_LEFT);
    }

    /** Resultado normalizado; se calcula una vez por resultado porque el listado evalúa miles de números. */
    private function prepare(object $result): array
    {
        if ($this->preparedFor === $result) {
            return $this->prepared;
        }

        $draw = ['prizes' => [], 'extractions' => [], 'reintegros' => [], 'pedreas' => []];
        foreach (array_keys(self::MAIN_PRIZES) as $field) {
            $draw['prizes'][$field] = $this->prizeNumbers($result, $field);
        }
        foreach (self::EXTRACTIONS as $field => [$digits]) {
            $draw['extractions'][$field] = [];
            foreach ($this->list($result, $field) as $extraction) {
                if (isset($extraction['decimo'])) {
                    $draw['extractions'][$field][] = self::digits($extraction['decimo'], $digits);
                }
            }
        }
        foreach ($this->list($result, 'reintegros') as $reintegro) {
            if (isset($reintegro['decimo'])) {
                $draw['reintegros'][substr((string) $reintegro['decimo'], -1)] = true;
            }
        }
        foreach ($this->list($result, 'pedreas') as $pedrea) {
            if (isset($pedrea['decimo'])) {
                $draw['pedreas'][self::pad($pedrea['decimo'])] = true;
            }
        }

        $this->preparedFor = $result;

        return $this->prepared = $draw;
    }

    /** @return list<string> */
    private function prizeNumbers(object $result, string $field): array
    {
        $data = $result->{$field} ?? null;
        if (! is_array($data) || $data === []) {
            return [];
        }

        $entries = self::MAIN_PRIZES[$field]['multiple'] ? $data : [$data];
        $numbers = [];
        foreach ($entries as $entry) {
            if (is_array($entry) && isset($entry['decimo']) && $entry['decimo'] !== '') {
                $numbers[] = self::pad($entry['decimo']);
            }
        }

        return $numbers;
    }

    private function list(object $result, string $field): array
    {
        $data = $result->{$field} ?? null;

        return is_array($data) ? $data : [];
    }

    /** Añade todos los números de 5 cifras que empiezan por $prefix y terminan en $suffix. */
    private function addEndingWith(array &$numbers, string $suffix, string $prefix = ''): void
    {
        $free = 5 - strlen($prefix) - strlen($suffix);
        $limit = 10 ** $free;
        for ($i = 0; $i < $limit; $i++) {
            $numbers[$prefix . ($free > 0 ? str_pad((string) $i, $free, '0', STR_PAD_LEFT) : '') . $suffix] = true;
        }
    }
}
