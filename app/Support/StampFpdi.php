<?php

namespace App\Support;

use setasign\Fpdi\Fpdi;

/**
 * FPDI + rotación de texto (script clásico de FPDF).
 */
class StampFpdi extends Fpdi
{
    protected float $stampAngle = 0.0;

    /**
     * Rota el sistema de coordenadas. Ángulo en grados, antihorario.
     * Pasar 0 restaura.
     */
    public function Rotate(float $angle, ?float $x = null, ?float $y = null): void
    {
        if ($x === null) {
            $x = $this->x;
        }
        if ($y === null) {
            $y = $this->y;
        }
        if ($this->stampAngle != 0) {
            $this->_out('Q');
        }
        $this->stampAngle = $angle;
        if ($angle != 0) {
            $angle *= M_PI / 180;
            $c = cos($angle);
            $s = sin($angle);
            $cx = $x * $this->k;
            $cy = ($this->h - $y) * $this->k;
            $this->_out(sprintf(
                'q %.5F %.5F %.5F %.5F %.5F %.5F cm 1 0 0 1 %.5F %.5F cm',
                $c,
                $s,
                -$s,
                $c,
                $cx,
                $cy,
                -$cx,
                -$cy
            ));
        }
    }

    protected function _endpage()
    {
        if ($this->stampAngle != 0) {
            $this->Rotate(0);
        }
        parent::_endpage();
    }
}
