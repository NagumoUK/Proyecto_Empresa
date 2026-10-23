<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Http\Requests\Management\IndexProjectRequest;
use App\Http\Requests\Management\StoreProjectRequest;
use App\Http\Requests\Management\UpdateProjectRequest;
use App\Models\Client;
use App\Models\Project;
use App\Services\DeletionRequestService;
use App\Services\ManagementActivityMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(IndexProjectRequest $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->toString();

        $projects = Project::query()
            ->with('client:id,name,company')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('projects.name', 'like', "%{$search}%")
                        ->orWhereHas('client', function ($query) use ($search): void {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhere('company', 'like', "%{$search}%");
                        });
                });
            })
            ->when(in_array($status, Project::STATUSES, true), fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('management/projects/index', [
            'projects' => $projects,
            'clients' => Client::query()->orderBy('name')->get(['id', 'name', 'company']),
            'stats' => [
                'total' => Project::query()->count(),
                'active' => Project::query()->where('status', 'active')->count(),
                'withBudget' => Project::query()->whereNotNull('budget')->count(),
            ],
            'filters' => [
                'search' => $search,
                'status' => in_array($status, Project::STATUSES, true) ? $status : '',
            ],
        ]);
    }

    public function store(
        StoreProjectRequest $request,
        ManagementActivityMailer $activityMailer,
    ): RedirectResponse {
        $project = Project::query()->create($request->validated())->load('client');
        $emailSent = $activityMailer->send(
            action: 'Creación de',
            entityType: 'proyecto',
            entityName: $project->name,
            actorName: $request->user()->name,
            details: $this->projectDetails($project),
        );

        $this->flashActivityToast('Proyecto creado correctamente.', $emailSent);

        return to_route('management.proyectos.index');
    }

    public function show(Project $proyecto): Response
    {
        $proyecto->load([
            'client',
            'files' => fn ($query) => $query->latest(),
        ]);

        return Inertia::render('management/projects/show', [
            'project' => $proyecto,
            'files' => $proyecto->files->map(fn ($file): array => [
                'id' => $file->id,
                'name' => $file->original_name,
                'mimeType' => $file->mime_type,
                'size' => $file->size,
                'uploadedAt' => $file->created_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    public function update(
        UpdateProjectRequest $request,
        Project $proyecto,
        ManagementActivityMailer $activityMailer,
    ): RedirectResponse {
        $proyecto->update($request->validated());
        $proyecto->load('client');

        $emailSent = $activityMailer->send(
            action: 'Actualización de',
            entityType: 'proyecto',
            entityName: $proyecto->name,
            actorName: $request->user()->name,
            details: $this->projectDetails($proyecto),
        );

        $this->flashActivityToast('Proyecto actualizado correctamente.', $emailSent);

        return to_route('management.proyectos.index');
    }

    public function destroy(
        Request $request,
        Project $proyecto,
        DeletionRequestService $deletionRequests,
    ): RedirectResponse {
        try {
            $result = $deletionRequests->request($proyecto, $request->user());
        } catch (\LogicException $exception) {
            throw ValidationException::withMessages(['delete' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => $result['email_sent'] || ! $result['created'] ? 'success' : 'warning',
            'message' => ! $result['created']
                ? 'Ya existe una solicitud pendiente para este proyecto.'
                : ($result['email_sent']
                    ? 'Solicitud enviada al administrador para aprobar la eliminación del proyecto.'
                    : 'La solicitud quedó registrada, pero no se pudo enviar el correo al administrador.'),
        ]);

        return to_route('management.proyectos.index');
    }

    /**
     * @return array<string, string>
     */
    private function projectDetails(Project $project): array
    {
        return [
            'Cliente' => $project->client->company ?? $project->client->name,
            'Estado' => $project->status,
            'Presupuesto' => $project->budget === null ? 'No definido' : "S/ {$project->budget}",
            'Fecha de inicio' => $project->start_date?->format('d/m/Y') ?? 'No definida',
            'Fecha de fin' => $project->end_date?->format('d/m/Y') ?? 'No definida',
        ];
    }

    private function flashActivityToast(string $message, bool $emailSent): void
    {
        Inertia::flash('toast', [
            'type' => $emailSent ? 'success' : 'warning',
            'message' => $emailSent
                ? "{$message} Se envió un aviso por correo."
                : "{$message} No se pudo enviar el aviso por correo; revisa los registros.",
        ]);
    }
}
