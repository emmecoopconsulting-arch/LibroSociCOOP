<?php

use App\Http\Controllers\Auth\InitialAdminSetupController;
use App\Services\InitialAdminSetupService;
use App\Services\LibroSociExportService;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (app(InitialAdminSetupService::class)->isRequired()) {
        return redirect()->route('setup.show');
    }

    return view('welcome');
});

Route::get('/setup', [InitialAdminSetupController::class, 'show'])->name('setup.show');
Route::post('/setup', [InitialAdminSetupController::class, 'store'])->name('setup.store');

Route::middleware('auth')->group(function (): void {
    Route::get('/exports/libro-soci.pdf', fn (LibroSociExportService $service) => $service->pdfResponse())
        ->name('exports.libro-soci.pdf');

    Route::get('/exports/libro-soci.xlsx', fn (LibroSociExportService $service) => $service->excelResponse())
        ->name('exports.libro-soci.excel');
});
