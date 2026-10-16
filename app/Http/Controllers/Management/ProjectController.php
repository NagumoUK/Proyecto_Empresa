<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Http\Requests\Management\IndexProjectRequest;
use App\Http\Requests\Management\StoreProjectRequest;
use App\Http\Requests\Management\UpdateProjectRequest;
use App\Models\Client;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
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

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        Project::query()->create($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Proyecto creado correctamente.',
        ]);

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

    public function update(UpdateProjectRequest $request, Project $proyecto): RedirectResponse
    {
        $proyecto->update($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Proyecto actualizado correctamente.',
        ]);

        return to_route('management.proyectos.index');
    }

    public function destroy(Project $proyecto): RedirectResponse
    {
        if ($proyecto->financialDocuments()->exists()) {
            return back()->withErrors([
                'delete' => 'No puedes eliminar este proyecto porque tiene cotizaciones o facturas asociadas.',
            ]);
        }

        $proyecto->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Proyecto eliminado correctamente.',
        ]);

        return to_route('management.proyectos.index');
    }
}
