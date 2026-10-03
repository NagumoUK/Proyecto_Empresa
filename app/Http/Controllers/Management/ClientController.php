<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Http\Requests\Management\StoreClientRequest;
use App\Http\Requests\Management\UpdateClientRequest;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        $clients = Client::query()
            ->withCount('projects')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('company', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('management/clients/index', [
            'clients' => $clients,
            'stats' => [
                'total' => Client::query()->count(),
                'withProjects' => Client::query()->has('projects')->count(),
            ],
            'filters' => ['search' => $search],
        ]);
    }

    public function store(StoreClientRequest $request): RedirectResponse
    {
        Client::query()->create($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Cliente creado correctamente.',
        ]);

        return to_route('management.clientes.index');
    }

    public function show(Client $cliente): Response
    {
        $cliente->loadCount('projects');

        return Inertia::render('management/clients/show', [
            'client' => $cliente,
            'projects' => $cliente->projects()->latest()->get(),
        ]);
    }

    public function update(UpdateClientRequest $request, Client $cliente): RedirectResponse
    {
        $cliente->update($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Cliente actualizado correctamente.',
        ]);

        return to_route('management.clientes.index');
    }

    public function destroy(Client $cliente): RedirectResponse
    {
        if ($cliente->projects()->exists()) {
            return back()->withErrors([
                'delete' => 'No puedes eliminar este cliente porque tiene proyectos asociados.',
            ]);
        }

        $cliente->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Cliente eliminado correctamente.',
        ]);

        return to_route('management.clientes.index');
    }
}
