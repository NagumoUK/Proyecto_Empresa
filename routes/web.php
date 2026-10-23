<?php

use App\Http\Controllers\CompanyActivityController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Management\ClientController;
use App\Http\Controllers\Management\DeletionRequestController;
use App\Http\Controllers\Management\FinancialDocumentController;
use App\Http\Controllers\Management\ProjectController;
use App\Http\Controllers\Management\ProjectFileController;
use App\Http\Controllers\Management\UserManagementController;
use App\Http\Controllers\StoredImageController;
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
        ->middleware('role:admin,manager')
        ->name('activities.store');

    Route::prefix('management')->name('management.')->group(function () {
        Route::get('clientes/{cliente}/logo', [StoredImageController::class, 'clientLogo'])
            ->name('clientes.logo');
        Route::resource('clientes', ClientController::class)
            ->only(['index', 'show']);
        Route::resource('proyectos', ProjectController::class)
            ->only(['index', 'show']);
        Route::resource('documentos', FinancialDocumentController::class)
            ->only(['index', 'show'])
            ->parameters(['documentos' => 'documento']);
        Route::get('proyectos/{proyecto}/archivos/{archivo}/descargar', [ProjectFileController::class, 'download'])
            ->name('proyectos.archivos.download');

        Route::middleware('role:admin,manager')->group(function (): void {
            Route::resource('clientes', ClientController::class)
                ->only(['store', 'update', 'destroy']);
            Route::resource('proyectos', ProjectController::class)
                ->only(['store', 'update', 'destroy']);
            Route::resource('documentos', FinancialDocumentController::class)
                ->only(['store', 'update', 'destroy'])
                ->parameters(['documentos' => 'documento']);
            Route::post('documentos/{documento}/convertir-a-factura', [FinancialDocumentController::class, 'convertToInvoice'])
                ->name('documentos.convertir-a-factura');
            Route::patch('documentos/{documento}/estado', [FinancialDocumentController::class, 'updateStatus'])
                ->name('documentos.estado');
            Route::post('proyectos/{proyecto}/archivos', [ProjectFileController::class, 'store'])
                ->name('proyectos.archivos.store');
            Route::delete('proyectos/{proyecto}/archivos/{archivo}', [ProjectFileController::class, 'destroy'])
                ->name('proyectos.archivos.destroy');
        });

        Route::middleware('role:admin')->group(function (): void {
            Route::get('usuarios', [UserManagementController::class, 'index'])
                ->name('usuarios.index');
            Route::patch('usuarios/{user}/rol', [UserManagementController::class, 'updateRole'])
                ->name('usuarios.rol');
            Route::get('solicitudes-eliminacion', [DeletionRequestController::class, 'index'])
                ->name('solicitudes-eliminacion.index');
            Route::post('solicitudes-eliminacion/{deletionRequest}/aprobar', [DeletionRequestController::class, 'approve'])
                ->name('solicitudes-eliminacion.aprobar');
            Route::post('solicitudes-eliminacion/{deletionRequest}/rechazar', [DeletionRequestController::class, 'reject'])
                ->name('solicitudes-eliminacion.rechazar');
        });

    });
});

require __DIR__.'/settings.php';
