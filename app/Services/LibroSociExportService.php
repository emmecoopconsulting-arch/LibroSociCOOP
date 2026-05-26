<?php

namespace App\Services;

use App\Models\DocumentHeaderSetting;
use App\Models\Socio;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LibroSociExportService
{
    public function __construct(private readonly PdfPageNumberService $pageNumberService) {}

    public function pdfResponse(): Response
    {
        $soci = Socio::query()
            ->attivi()
            ->orderBy('cognome')
            ->orderBy('nome')
            ->get();

        $pdf = Pdf::loadView('pdf.libro-soci', [
            'documentHeader' => DocumentHeaderSetting::current(),
            'soci' => $soci,
        ])
            ->setPaper('a4', 'landscape');

        return $this->pageNumberService
            ->apply($pdf)
            ->download('libro-soci.pdf');
    }

    public function excelResponse(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Libro soci');
        $sheet->fromArray([
            'Codice socio',
            'Cognome',
            'Nome',
            'Codice fiscale',
            'Tipologia',
            'Data ammissione',
            'Capitale versato',
        ], null, 'A1');

        $row = 2;
        Socio::query()->attivi()->orderBy('cognome')->orderBy('nome')->each(function (Socio $socio) use ($sheet, &$row): void {
            $sheet->fromArray([
                $socio->codice_socio,
                $socio->cognome,
                $socio->nome,
                $socio->codice_fiscale,
                Socio::TIPOLOGIE[$socio->tipologia] ?? $socio->tipologia,
                $socio->data_ammissione?->format('d/m/Y'),
                (float) $socio->capitale_versato,
            ], null, "A{$row}");
            $row++;
        });

        foreach (range('A', 'G') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'libro-soci.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
