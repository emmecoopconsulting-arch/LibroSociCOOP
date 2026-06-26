<?php

use App\Http\Controllers\Auth\InitialAdminSetupController;
use App\Models\Assemblea;
use App\Models\SocioMedicalVisit;
use App\Services\AssembleaService;
use App\Services\InitialAdminSetupService;
use App\Services\LibroSociExportService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
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

    Route::get('/assemblee/{assemblea}/download', fn (Assemblea $assemblea, AssembleaService $service) => $service->downloadResponse($assemblea))
        ->name('assemblee.download');

    Route::get('/visite-mediche/{visit}/download', function (SocioMedicalVisit $visit) {
        abort_if(blank($visit->pdf_path) || ! Storage::disk('local')->exists($visit->pdf_path), 404);

        return response()->download(
            Storage::disk('local')->path($visit->pdf_path),
            basename($visit->pdf_path),
            ['Content-Type' => 'application/pdf']
        );
    })->name('visite-mediche.download');

    Route::get('/visite-mediche/file', function (Request $request) {
        try {
            $path = Crypt::decryptString((string) $request->query('path'));
        } catch (DecryptException) {
            abort(404);
        }

        $path = trim($path);

        abort_if(
            $path === ''
            || str_contains($path, '..')
            || ! str_starts_with($path, 'visite-mediche/')
            || ! Storage::disk('local')->exists($path),
            404
        );

        if ($request->boolean('download')) {
            return response()->download(
                Storage::disk('local')->path($path),
                basename($path),
                ['Content-Type' => 'application/pdf']
            );
        }

        return response()->file(
            Storage::disk('local')->path($path),
            ['Content-Type' => 'application/pdf']
        );
    })->name('visite-mediche.file');
});
