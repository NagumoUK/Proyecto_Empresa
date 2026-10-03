<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Http\Requests\Management\StoreProjectRequest;
use App\Http\Requests\Management\UpdateProjectRequest;
use App\Models\Client;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(Request $request): Response
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
        $proyecto->load('client');

        return Inertia::render('management/projects/show', [
            'project' => $proyecto,
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
        $proyecto->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Proyecto eliminado correctamente.',
        ]);

        return to_route('management.proyectos.index');
    }
}
