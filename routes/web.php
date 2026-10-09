<?php

use App\Http\Controllers\CompanyActivityController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Management\ClientController;
use App\Http\Controllers\Management\ProjectController;
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
    });
});

require __DIR__.'/settings.php';
