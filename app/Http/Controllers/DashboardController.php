<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\CompanyActivity;
use App\Models\Project;
use App\Services\WeatherService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use UnexpectedValueException;

class DashboardController extends Controller
{
    public function __invoke(WeatherService $weatherService): Response
    {
        try {
            $weather = $weatherService->current();
        } catch (ConnectionException|RequestException|UnexpectedValueException $exception) {
            report($exception);
            $weather = null;
        }

        $activities = CompanyActivity::query()
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
            ->all();

        $today = Carbon::today();
        $activityRangeStart = $today->copy()->subDays(179);
        $activityCounts = DB::table('company_activities')
            ->whereBetween('created_at', [
                $activityRangeStart,
                $today->copy()->endOfDay(),
            ])
            ->selectRaw('DATE(created_at) as activity_date, COUNT(*) as aggregate')
            ->groupByRaw('DATE(created_at)')
            ->get()
            ->mapWithKeys(static function (object $activity): array {
                $activityDate = Carbon::parse((string) $activity->activity_date)
                    ->toDateString();

                return [$activityDate => (int) $activity->aggregate];
            });
        $activitySeries = [];

        for ($date = $activityRangeStart->copy(); $date->lte($today); $date->addDay()) {
            $activityDate = $date->toDateString();
            $activitySeries[] = [
                'date' => $activityDate,
                'count' => (int) ($activityCounts[$activityDate] ?? 0),
            ];
        }

        $projects = Project::query()
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
            ->all();

        $summary = [
            'clients' => Client::query()->count(),
            'projectStatuses' => Project::query()
                ->selectRaw('status, COUNT(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status')
                ->map(static fn (int|string $count): int => (int) $count)
                ->all(),
        ];

        $projectStatuses = array_replace(
            array_fill_keys(Project::STATUSES, 0),
            $summary['projectStatuses'],
        );

        return Inertia::render('dashboard', [
            'activitySeries' => $activitySeries,
            'activities' => $activities,
            'weather' => $weather,
            'metrics' => [
                'clients' => $summary['clients'],
                'activeProjects' => $projectStatuses['active'],
                'planningProjects' => $projectStatuses['planning'],
                'completedProjects' => $projectStatuses['completed'],
            ],
            'projectStatuses' => $projectStatuses,
            'projects' => $projects,
        ]);
    }
}
