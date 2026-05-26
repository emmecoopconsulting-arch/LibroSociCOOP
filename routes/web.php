<?php

use App\Services\LibroSociExportService;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/exports/libro-soci.pdf', fn (LibroSociExportService $service) => $service->pdfResponse())
        ->name('exports.libro-soci.pdf');

    Route::get('/exports/libro-soci.xlsx', fn (LibroSociExportService $service) => $service->excelResponse())
        ->name('exports.libro-soci.excel');
});
