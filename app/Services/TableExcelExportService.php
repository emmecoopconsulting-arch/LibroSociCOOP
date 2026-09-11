<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Contracts\Support\Htmlable;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TableExcelExportService
{
    public function download(HasTable $livewire, string $title, string $filename): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()->setTitle($title)->setCreator('Libro Soci COOP');
        $sheet = $spreadsheet->getActiveSheet()->setTitle($title);
        $columns = array_values(array_map(fn (Column $column): Column => clone $column, $livewire->getTable()->getVisibleColumns()));

        foreach ($columns as $index => $column) {
            $sheet->setCellValueExplicit([$index + 1, 1], $this->text($column->getLabel()), DataType::TYPE_STRING);
            if ($column instanceof TextColumn) {
                // Export the full value, even when the table shortens long notes.
                $column->limit(null)->words(null);
            }
        }

        $row = 2;
        foreach ($livewire->getFilteredSortedTableQuery()->lazy(500) as $record) {
            foreach ($columns as $index => $column) {
                $column->record($record);
                $column->clearCachedState();
                $state = $column->getState();
                $cell = $sheet->getCell([$index + 1, $row]);

                if ($state === null || $state === '') {
                    continue;
                }

                if ($column instanceof TextColumn && ($column->isDate() || $column->isDateTime())) {
                    $cell->setValueExplicit(Date::PHPToExcel(CarbonImmutable::parse($state)), DataType::TYPE_NUMERIC);
                    $cell->getStyle()->getNumberFormat()->setFormatCode($column->isDateTime() ? 'dd/mm/yyyy hh:mm' : 'dd/mm/yyyy');
                } elseif ($column instanceof IconColumn && $column->isBoolean()) {
                    $cell->setValueExplicit($state ? 'Sì' : 'No', DataType::TYPE_STRING);
                } else {
                    $value = $column instanceof TextColumn ? $column->formatState($state) : $state;
                    // Explicit text preserves identifiers and prevents notes becoming Excel formulas.
                    $numeric = is_int($value) || is_float($value);
                    $cell->setValueExplicit($numeric ? $value : $this->text($value), $numeric ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
                }
            }
            $row++;
        }

        $lastColumn = Coordinate::stringFromColumnIndex(max(1, count($columns)));
        $lastRow = max(1, $row - 1);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:{$lastColumn}{$lastRow}");
        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E78']],
        ]);
        $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->getAlignment()->setWrapText(true)->setVertical('top');
        $sheet->getRowDimension(1)->setRowHeight(32);
        foreach ($columns as $index => $column) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index + 1))
                ->setWidth($column->getName() === 'note' ? 50 : 24);
        }
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setFitToWidth(1)->setFitToHeight(0);
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 1);

        return response()->streamDownload(function () use ($spreadsheet): void {
            try {
                (new Xlsx($spreadsheet))->save('php://output');
            } finally {
                $spreadsheet->disconnectWorksheets();
            }
        }, $filename.'-'.now()->format('Y-m-d_His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function text(mixed $value): string
    {
        return $value instanceof Htmlable ? html_entity_decode(strip_tags($value->toHtml()), ENT_QUOTES | ENT_HTML5, 'UTF-8') : (string) $value;
    }
}
