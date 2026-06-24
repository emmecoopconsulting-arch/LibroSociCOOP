<?php

namespace App\Services;

use App\Models\DocumentHeaderSetting;
use App\Models\WorkOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class WorkOrderPdfService
{
    public function __construct(private readonly PdfPageNumberService $pageNumberService) {}

    public function generate(WorkOrder $order, bool $archive = false): WorkOrder
    {
        $order->loadMissing([
            'sites.assignments.socio',
            'sites.vehicle',
            'absences.socio',
        ]);

        $pdf = $this->pageNumberService->apply(Pdf::loadView('pdf.work-order', [
            'documentHeader' => DocumentHeaderSetting::current(),
            'order' => $order,
        ]));

        $path = sprintf(
            'ordini-servizio/%s/ordine-servizio-%s-%s.pdf',
            $order->data_servizio?->format('Y') ?? now()->format('Y'),
            $order->data_servizio?->format('Y-m-d') ?? now()->format('Y-m-d'),
            $order->id,
        );

        Storage::disk('local')->put($path, $pdf->output());

        $order->update([
            'stato' => $archive ? 'archiviato' : $order->stato,
            'pdf_path' => $path,
            'archiviato_il' => $archive ? now() : $order->archiviato_il,
        ]);

        return $order->refresh();
    }

    public function downloadResponse(WorkOrder $order, bool $regenerate = false, bool $archive = false): BinaryFileResponse
    {
        if ($regenerate || $archive || blank($order->pdf_path) || ! Storage::disk('local')->exists($order->pdf_path)) {
            $order = $this->generate($order, archive: $archive);
        }

        return response()->download(
            Storage::disk('local')->path($order->pdf_path),
            $this->downloadFilename($order),
        );
    }

    private function downloadFilename(WorkOrder $order): string
    {
        $date = $order->data_servizio?->format('Y-m-d') ?? now()->format('Y-m-d');

        return "ordine-servizio-{$date}-{$order->id}.pdf";
    }
}
