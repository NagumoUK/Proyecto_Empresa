<?php

use App\Http\Controllers\CompanyActivityController;
use App\Http\Controllers\Management\ClientController;
use App\Http\Controllers\Management\ProjectController;
use App\Models\Client;
use App\Models\CompanyActivity;
use App\Models\Project;
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

        $projects = Project::query()
            ->with('client:id,name,company')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Project $project): array => [
                'id' => $project->id,
                'name' => $project->name,
                'client' => $project->client->company ?: $project->client->name,
                'status' => $project->status,
                'endDate' => $project->end_date?->toDateString(),
            ])
            ->values();

        return Inertia::render('dashboard', [
            'activities' => $activities,
            'metrics' => [
                'clients' => Client::query()->count(),
                'activeProjects' => Project::query()->where('status', 'active')->count(),
                'planningProjects' => Project::query()->where('status', 'planning')->count(),
                'completedProjects' => Project::query()->where('status', 'completed')->count(),
            ],
            'projectStatuses' => collect(Project::STATUSES)
                ->mapWithKeys(fn (string $status): array => [
                    $status => Project::query()->where('status', $status)->count(),
                ]),
            'projects' => $projects,
        ]);
    })->name('dashboard');

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
