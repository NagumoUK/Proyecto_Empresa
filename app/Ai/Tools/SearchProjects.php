<?php

namespace App\Ai\Tools;

use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SearchProjects implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Busca proyectos por nombre, cliente o estado y devuelve conteos por estado. No devuelve presupuesto ni descripción.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(Project::STATUSES)],
        ]);

        $projectsQuery = Project::query()->whereHas('client');
        $statusCounts = (clone $projectsQuery)
            ->selectRaw('status, COUNT(*) AS aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->all();
        $countsByStatus = array_fill_keys(Project::STATUSES, 0);

        foreach ($statusCounts as $status => $count) {
            $countsByStatus[$status] = (int) $count;
        }

        $projectsQuery->with('client:id,name,company')
            ->select(['id', 'client_id', 'name', 'status', 'start_date', 'end_date']);

        $searchTerm = $request->string('search')->trim()->lower()->toString();

        if ($searchTerm !== '') {
            $search = '%'.$searchTerm.'%';
            $projectsQuery->where(function (Builder $query) use ($search): void {
                $query->whereRaw('LOWER(name) LIKE ?', [$search])
                    ->orWhereHas('client', function (Builder $clientQuery) use ($search): void {
                        $clientQuery->whereRaw('LOWER(name) LIKE ?', [$search])
                            ->orWhereRaw('LOWER(company) LIKE ?', [$search]);
                    });
            });
        }

        $status = $request->string('status')->toString();

        if ($status !== '') {
            $projectsQuery->where('status', $status);
        }

        $totalMatches = (clone $projectsQuery)->count();
        $projects = $projectsQuery
            ->orderBy('name')
            ->orderBy('id')
            ->limit(20)
            ->get()
            ->map(static fn (Project $project): array => [
                'id' => $project->getKey(),
                'name' => $project->name,
                'client' => $project->client->name,
                'client_company' => $project->client->company,
                'status' => $project->status,
                'start_date' => $project->start_date?->toDateString(),
                'end_date' => $project->end_date?->toDateString(),
            ])
            ->all();

        return json_encode([
            'counts_by_status' => $countsByStatus,
            'total_matches' => $totalMatches,
            'returned' => count($projects),
            'truncated' => $totalMatches > count($projects),
            'projects' => $projects,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()
                ->description('Texto opcional para buscar por proyecto, nombre de cliente o empresa.')
                ->nullable(),
            'status' => $schema->string()
                ->enum(Project::STATUSES)
                ->description('Filtra por estado; omítelo para incluir todos.')
                ->nullable(),
        ];
    }
}
