<?php

// v1.0 — 2026-06-10 | Fpdi subclass adding rotated-text support for tiling download watermarks.

namespace App\Support\Pdf;

use setasign\Fpdi\Fpdi;

class WatermarkPdf extends Fpdi
{
    private float $angle = 0;

    /**
     * Draw $txt at ($x, $y) rotated $angle degrees counter-clockwise, then reset rotation.
     */
    public function rotatedText(float $x, float $y, string $txt, float $angle): void
    {
        $this->rotate($angle, $x, $y);
        $this->Text($x, $y, $txt);
        $this->rotate(0);
    }

    private function rotate(float $angle, ?float $x = null, ?float $y = null): void
    {
        $x ??= $this->x;
        $y ??= $this->y;

        if ($this->angle !== 0.0) {
            $this->_out('Q');
        }

        $this->angle = $angle;

        if ($angle !== 0.0) {
            $radians = deg2rad($angle);
            $cos = cos($radians);
            $sin = sin($radians);
            $cx = $x * $this->k;
            $cy = ($this->h - $y) * $this->k;

            $this->_out(sprintf(
                'q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm',
                $cos,
                $sin,
                -$sin,
                $cos,
                $cx,
                $cy,
                -$cx,
                -$cy
            ));
        }
    }

    protected function _endpage(): void
    {
        if ($this->angle !== 0.0) {
            $this->angle = 0;
            $this->_out('Q');
        }

        parent::_endpage();
    }
}
