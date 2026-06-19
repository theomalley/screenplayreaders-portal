<?php

// v1.0 — 2026-06-19 | FPDI subclass for rendering proofreading marks on imported PDF pages.

namespace App\Support\Pdf;

class ProofreadPdf extends WatermarkPdf
{
    public function drawArrowhead(float $x1, float $y1, float $x2, float $y2, float $length = 3): void
    {
        $angle = atan2($y2 - $y1, $x2 - $x1);
        $this->Line(
            $x2, $y2,
            $x2 - $length * cos($angle - M_PI / 6),
            $y2 - $length * sin($angle - M_PI / 6)
        );
        $this->Line(
            $x2, $y2,
            $x2 - $length * cos($angle + M_PI / 6),
            $y2 - $length * sin($angle + M_PI / 6)
        );
    }
}
