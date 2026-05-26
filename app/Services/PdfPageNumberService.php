<?php

namespace App\Services;

use Barryvdh\DomPDF\PDF;

class PdfPageNumberService
{
    public function apply(PDF $pdf): PDF
    {
        $pdf->render();

        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $fontSize = 9;
        $text = 'Pagina {PAGE_NUM} di {PAGE_COUNT}';
        $textWidth = $dompdf->getFontMetrics()->getTextWidth('Pagina 999 di 999', $font, $fontSize);

        $canvas->page_text(
            $canvas->get_width() - $textWidth - 46,
            $canvas->get_height() - 30,
            $text,
            $font,
            $fontSize,
            [0.42, 0.45, 0.50],
        );

        return $pdf;
    }
}
