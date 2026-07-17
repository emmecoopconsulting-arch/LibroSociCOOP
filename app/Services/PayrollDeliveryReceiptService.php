<?php

namespace App\Services;

use App\Models\PayrollDistribution;
use Barryvdh\DomPDF\Facade\Pdf;

class PayrollDeliveryReceiptService
{
    public function output(PayrollDistribution $distribution): string
    {
        $distribution->loadMissing(['deliveries.socio', 'user']);

        return Pdf::loadView('pdf.payroll-delivery-receipt', [
            'distribution' => $distribution,
            'deliveries' => $distribution->deliveries->sortBy([
                fn ($delivery) => $delivery->socio?->cognome ?? '',
                fn ($delivery) => $delivery->socio?->nome ?? '',
            ])->values(),
        ])->setPaper('a4')->output();
    }
}
