<?php

use App\Http\Controllers\CompanyActivityController;
use App\Models\CompanyActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function (Request $request) {
    if ($request->user()) {
        return to_route('dashboard');
    }

    return redirect()->route('login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        $activities = CompanyActivity::query()
            ->with('user')
            ->latest()
            ->limit(8)
            ->get()
            ->map(function (CompanyActivity $activity): array {
                $createdAt = new Carbon($activity->created_at);
                $createdAt->locale('es');

                return [
                    'id' => $activity->id,
                    'userName' => $activity->user->name,
                    'description' => $activity->description,
                    'time' => $createdAt->diffForHumans(),
                ];
            })
            ->values();

        return Inertia::render('dashboard', [
            'activities' => $activities,
        ]);
    })->name('dashboard');

    Route::post('activities', [CompanyActivityController::class, 'store'])
        ->name('activities.store');
});

require __DIR__.'/settings.php';
