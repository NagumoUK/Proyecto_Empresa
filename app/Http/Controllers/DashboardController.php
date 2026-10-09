<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\CompanyActivity;
use App\Models\Project;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Concurrency;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $dashboard = Concurrency::run([
            'activities' => static fn (): array => CompanyActivity::query()
                ->with('user')
                ->latest()
                ->limit(8)
                ->get()
                ->map(static function (CompanyActivity $activity): array {
                    $createdAt = new Carbon($activity->created_at);
                    $createdAt->locale('es');

                    return [
                        'id' => $activity->id,
                        'userName' => $activity->user->name,
                        'description' => $activity->description,
                        'time' => $createdAt->diffForHumans(),
                    ];
                })
                ->all(),
            'projects' => static fn (): array => Project::query()
                ->with('client:id,name,company')
                ->latest()
                ->limit(5)
                ->get()
                ->map(static fn (Project $project): array => [
                    'id' => $project->id,
                    'name' => $project->name,
                    'client' => $project->client->company ?: $project->client->name,
                    'status' => $project->status,
                    'endDate' => $project->end_date?->toDateString(),
                ])
                ->all(),
            'summary' => static fn (): array => [
                'clients' => Client::query()->count(),
                'projectStatuses' => Project::query()
                    ->selectRaw('status, COUNT(*) as aggregate')
                    ->groupBy('status')
                    ->pluck('aggregate', 'status')
                    ->map(static fn (int|string $count): int => (int) $count)
                    ->all(),
            ],
        ], timeout: 30);

        $projectStatuses = array_replace(
            array_fill_keys(Project::STATUSES, 0),
            $dashboard['summary']['projectStatuses'],
        );

        return Inertia::render('dashboard', [
            'activities' => $dashboard['activities'],
            'metrics' => [
                'clients' => $dashboard['summary']['clients'],
                'activeProjects' => $projectStatuses['active'],
                'planningProjects' => $projectStatuses['planning'],
                'completedProjects' => $projectStatuses['completed'],
            ],
            'projectStatuses' => $projectStatuses,
            'projects' => $dashboard['projects'],
        ]);
    }
}
