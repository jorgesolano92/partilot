<?php

namespace Tests\Unit;

use App\Services\Scrutiny\LotteryPrizeCalculator;
use Tests\TestCase;

/**
 * Casos oficiales SELAE de los informes de auditorÃ­a de escrutinio (importes al dÃ©cimo).
 */
class LotteryPrizeCalculatorTest extends TestCase
{
    private static function n(string ...$numbers): array
    {
        return array_map(fn ($number) => ['decimo' => $number], $numbers);
    }

    private static function draw(array $data): object
    {
        return (object) array_merge([
            'primer_premio' => null,
            'segundo_premio' => null,
            'terceros_premios' => [],
            'cuartos_premios' => [],
            'quintos_premios' => [],
            'extracciones_cinco_cifras' => [],
            'extracciones_cuatro_cifras' => [],
            'extracciones_tres_cifras' => [],
            'extracciones_dos_cifras' => [],
            'reintegros' => [],
            'pedreas' => [],
        ], $data);
    }

    public static function officialDraws(): array
    {
        return [
            'Jueves 041/26' => ['3_J', self::draw([
                'primer_premio' => ['decimo' => '45780'],
                'segundo_premio' => ['decimo' => '38140'],
                'extracciones_cuatro_cifras' => self::n('6153'),
                'extracciones_tres_cifras' => self::n('530', '640', '398'),
                'extracciones_dos_cifras' => self::n('02', '21', '21', '25', '52', '77', '79', '89', '93'),
                'reintegros' => self::n('0', '3', '8'),
            ]), [
                '45780' => 30000, '38140' => 6003, '45779' => 1236, '45781' => 1230, '38139' => 762,
                '38141' => 762, '45700' => 33, '45701' => 30, '45702' => 36, '38100' => 18, '38101' => 15,
                '38121' => 27, '38180' => 24, '12780' => 24, '12380' => 9, '15780' => 99, '12530' => 18,
                '12640' => 18, '12021' => 12, '16153' => 78, '12093' => 9, '12398' => 18, '12003' => 3,
                '12008' => 3, '12000' => 3,
            ]],
            'Cruz Roja 046/26' => ['15_S', self::draw([
                'primer_premio' => ['decimo' => '97984'],
                'segundo_premio' => ['decimo' => '86985'],
                'terceros_premios' => self::n('90170'),
                'extracciones_cinco_cifras' => self::n('07419', '25093'),
                'extracciones_cuatro_cifras' => self::n('5695'),
                'extracciones_tres_cifras' => self::n('034', '078', '249', '289', '332', '332', '410', '475', '532', '723', '879'),
                'extracciones_dos_cifras' => self::n('21', '93'),
                'reintegros' => self::n('4', '2', '9'),
            ]), [
                '97984' => 150000, '86985' => 30000, '90170' => 15000, '97983' => 2175, '97985' => 2175,
                '86984' => 1395, '86986' => 1275, '90169' => 712.5, '90171' => 697.5, '97900' => 75,
                '97902' => 90, '97904' => 90, '97909' => 90, '86900' => 75, '86902' => 90, '90100' => 75,
                '90109' => 90, '12984' => 120, '12084' => 45, '07419' => 7515, '25093' => 7530,
                '15695' => 375, '12332' => 165, '12532' => 90, '12021' => 30, '12002' => 15,
            ]],
            'San Ildefonso 6/26' => ['12_S', self::draw([
                'primer_premio' => ['decimo' => '58451'],
                'segundo_premio' => ['decimo' => '64780'],
                'terceros_premios' => self::n('09224'),
                'extracciones_cuatro_cifras' => self::n('1204', '8840', '5649'),
                'extracciones_tres_cifras' => self::n('411', '010', '514', '753'),
                'extracciones_dos_cifras' => self::n('00', '07', '40', '65', '65', '67', '72', '74', '90'),
                'reintegros' => self::n('1', '4', '3'),
            ]), [
                '58451' => 100000, '64780' => 25000, '09224' => 5012, '58450' => 1790, '58452' => 1790,
                '64779' => 968, '64781' => 980, '58410' => 60, '58411' => 132, '58414' => 72, '58413' => 72,
                '64710' => 60, '64714' => 72, '09210' => 60, '09214' => 72, '12451' => 96, '12051' => 36,
                '11204' => 312, '18840' => 324, '15649' => 300, '12010' => 60, '12514' => 72, '12753' => 72,
                '12065' => 48, '12000' => 24, '12004' => 12, '12348' => 0,
            ]],
            'DÃ­a del Padre 024/26' => ['15_S_ESPECIAL', self::draw([
                'primer_premio' => ['decimo' => '34302', 'serie' => '6', 'fraccion' => '10'],
                'segundo_premio' => ['decimo' => '73943'],
                'extracciones_cuatro_cifras' => self::n('0517', '2146', '4396'),
                'extracciones_tres_cifras' => self::n('390', '354', '375', '162'),
                'extracciones_dos_cifras' => self::n('14', '98'),
                'reintegros' => self::n('2', '3', '6'),
            ]), [
                '34302' => 130000, '73943' => 25015, '34301' => 2475, '34303' => 2490, '73942' => 1622.5,
                '73944' => 1607.5, '34300' => 75, '34390' => 150, '34354' => 150, '34375' => 150,
                '34396' => 465, '73900' => 75, '73902' => 165, '73903' => 90, '73906' => 90, '12302' => 165,
                '12002' => 90, '10517' => 375, '12146' => 390, '14396' => 390, '12162' => 90, '12014' => 30,
                '12098' => 30, '12003' => 15, '12006' => 15, '12943' => 15,
            ]],
            'NiÃ±o 2/26' => ['20_B', self::draw([
                'primer_premio' => ['decimo' => '06703'],
                'segundo_premio' => ['decimo' => '45875'],
                'terceros_premios' => self::n('32615'),
                'extracciones_cuatro_cifras' => self::n('1829', '3682'),
                'extracciones_tres_cifras' => self::n('058', '156', '861'),
                'extracciones_dos_cifras' => self::n('27', '44'),
                'reintegros' => self::n('3', '1', '0'),
            ]), [
                '06703' => 200000, '45875' => 75000, '32615' => 25000, '06702' => 1300, '06704' => 1300,
                '45874' => 710, '45876' => 710, '06715' => 100, '06710' => 120, '06711' => 120, '06713' => 120,
                '45815' => 100, '32610' => 120, '12703' => 220, '12003' => 120, '12875' => 100, '11829' => 350,
                '13682' => 350, '12058' => 100, '12156' => 100, '12861' => 120, '12027' => 40, '12044' => 40,
                '12001' => 20, '12000' => 20, '12348' => 0,
            ]],
            'Vacaciones 54/25' => ['20_V', self::draw([
                'primer_premio' => ['decimo' => '51376', 'serie' => '7', 'fraccion' => '2'],
                'segundo_premio' => ['decimo' => '54384'],
                'terceros_premios' => self::n('55495'),
                'extracciones_tres_cifras' => self::n('058', '061', '667'),
                'extracciones_dos_cifras' => self::n('06', '22'),
                'reintegros' => self::n('6', '4', '9'),
            ]), [
                '51376' => 200000, '55495' => 20000, '51375' => 900, '51377' => 900, '54383' => 480,
                '54385' => 480, '51315' => 100, '51314' => 120, '51319' => 120, '51316' => 120, '54315' => 50,
                '54314' => 70, '55415' => 50, '55414' => 70, '12376' => 160, '12076' => 60, '12384' => 120,
                '12058' => 100, '12061' => 100, '12667' => 100, '12006' => 60, '12022' => 40, '12004' => 20,
                '12348' => 0,
            ]],
            'Navidad 102/25' => ['20_N', self::draw([
                'primer_premio' => ['decimo' => '79432'],
                'segundo_premio' => ['decimo' => '70048'],
                'terceros_premios' => self::n('90693'),
                'cuartos_premios' => self::n('25508', '78477'),
                'quintos_premios' => self::n('41716'),
                'reintegros' => self::n('2'),
                'pedreas' => self::n('12772', '00082', '01064', '11132'),
            ]), [
                '79432' => 400000, '70048' => 125000, '90693' => 50000, '25508' => 20000, '78477' => 20000,
                '41716' => 6000, '79431' => 2100, '79433' => 2100, '70047' => 1350, '70049' => 1350,
                '90692' => 1080, '90694' => 1060, '79415' => 100, '79412' => 120, '70015' => 100, '90615' => 100,
                '25515' => 100, '78415' => 100, '00032' => 120, '00048' => 100, '00093' => 100, '12772' => 120,
                '00082' => 120, '01064' => 100, '11132' => 220, '12345' => 0,
            ]],
        ];
    }

    /**
     * @dataProvider officialDraws
     */
    public function test_matches_official_selae_amounts(string $type, object $result, array $cases): void
    {
        $calculator = new LotteryPrizeCalculator();
        $candidates = array_flip($calculator->candidateNumbers($result));

        foreach ($cases as $number => $expectedDecimo) {
            $number = str_pad((string) $number, 5, '0', STR_PAD_LEFT);
            $calculation = $calculator->calculate($number, $result, $type);
            $detail = implode(', ', array_map(fn ($p) => $p['key'] . '=' . $p['amount'], $calculation['prizes']));

            $this->assertEqualsWithDelta($expectedDecimo, $calculation['total_prize'] / 10, 0.001, "{$number}: {$detail}");
            if ($expectedDecimo > 0) {
                $this->assertArrayHasKey($number, $candidates, "{$number} no estÃ¡ entre los nÃºmeros candidatos");
            }
        }
    }

    public function test_approximations_wrap_around_the_drum(): void
    {
        $calculator = new LotteryPrizeCalculator();

        $top = self::draw(['primer_premio' => ['decimo' => '99999']]);
        $posterior = $calculator->calculate('00000', $top, '6_X');
        $this->assertContains('posteriorPrimerPremio', array_column($posterior['prizes'], 'key'));

        $bottom = self::draw(['primer_premio' => ['decimo' => '00000']]);
        $anterior = $calculator->calculate('99999', $bottom, '6_X');
        $this->assertContains('anteriorPrimerPremio', array_column($anterior['prizes'], 'key'));
        $this->assertContains('99999', $calculator->candidateNumbers($bottom));
    }
}
