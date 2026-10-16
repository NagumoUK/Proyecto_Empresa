<?php

use App\Http\Controllers\CompanyActivityController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Management\ClientController;
use App\Http\Controllers\Management\FinancialDocumentController;
use App\Http\Controllers\Management\ProjectController;
use App\Http\Controllers\Management\ProjectFileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request) {
    if ($request->user()) {
        return to_route('dashboard');
    }

    return redirect()->route('login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::post('activities', [CompanyActivityController::class, 'store'])
        ->name('activities.store');

    Route::prefix('management')->name('management.')->group(function () {
        Route::resource('clientes', ClientController::class)
            ->except(['create', 'edit']);
        Route::resource('proyectos', ProjectController::class)
            ->except(['create', 'edit']);
        Route::post('documentos/{documento}/convertir-a-factura', [FinancialDocumentController::class, 'convertToInvoice'])
            ->name('documentos.convertir-a-factura');
        Route::patch('documentos/{documento}/estado', [FinancialDocumentController::class, 'updateStatus'])
            ->name('documentos.estado');
        Route::resource('documentos', FinancialDocumentController::class)
            ->except(['create', 'edit'])
            ->parameters(['documentos' => 'documento']);
        Route::post('proyectos/{proyecto}/archivos', [ProjectFileController::class, 'store'])
            ->name('proyectos.archivos.store');
        Route::get('proyectos/{proyecto}/archivos/{archivo}/descargar', [ProjectFileController::class, 'download'])
            ->name('proyectos.archivos.download');
        Route::delete('proyectos/{proyecto}/archivos/{archivo}', [ProjectFileController::class, 'destroy'])
            ->name('proyectos.archivos.destroy');
    });
});

require __DIR__.'/settings.php';
