<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Http\Requests\Management\IndexClientRequest;
use App\Http\Requests\Management\StoreClientRequest;
use App\Http\Requests\Management\UpdateClientRequest;
use App\Models\Client;
use App\Services\DeletionRequestService;
use App\Services\ImageStorage;
use App\Services\ManagementActivityMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ClientController extends Controller
{
    public function index(IndexClientRequest $request): Response
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
            ->withQueryString()
            ->through(fn (Client $client): Client => $client->append('logo_url'));

        return Inertia::render('management/clients/index', [
            'clients' => $clients,
            'stats' => [
                'total' => Client::query()->count(),
                'withProjects' => Client::query()->has('projects')->count(),
            ],
            'filters' => ['search' => $search],
        ]);
    }

    public function store(
        StoreClientRequest $request,
        ImageStorage $imageStorage,
        ManagementActivityMailer $activityMailer,
    ): RedirectResponse {
        $attributes = $request->safe()->except(['logo']);
        $logo = $request->file('logo');
        $logoPath = $logo instanceof UploadedFile
            ? $imageStorage->store($logo, 'clients/logos', 512, 512)
            : null;

        if ($logoPath !== null) {
            $attributes['logo_path'] = $logoPath;
        }

        try {
            $client = Client::query()->create($attributes);
        } catch (Throwable $exception) {
            if ($logoPath !== null) {
                try {
                    $imageStorage->delete($logoPath);
                } catch (Throwable $cleanupException) {
                    report($cleanupException);
                }
            }

            throw $exception;
        }

        $emailSent = $activityMailer->send(
            action: 'Creación de',
            entityType: 'cliente',
            entityName: $client->name,
            actorName: $request->user()->name,
            details: [
                'Empresa' => $client->company ?? 'No especificada',
                'Correo del cliente' => $client->email ?? 'No especificado',
                'Estado' => $client->status,
            ],
        );

        $this->flashActivityToast('Cliente creado correctamente.', $emailSent);

        return to_route('management.clientes.index');
    }

    public function show(Client $cliente): Response
    {
        $cliente->loadCount('projects')->append('logo_url');

        return Inertia::render('management/clients/show', [
            'client' => $cliente,
            'projects' => $cliente->projects()->latest()->get(),
        ]);
    }

    public function update(
        UpdateClientRequest $request,
        Client $cliente,
        ImageStorage $imageStorage,
        ManagementActivityMailer $activityMailer,
    ): RedirectResponse {
        $cliente->fill($request->safe()->except(['logo']));

        $oldLogoPath = null;
        $newLogoPath = null;
        $logo = $request->file('logo');

        if ($logo instanceof UploadedFile) {
            $oldLogoPath = $cliente->logo_path;
            $newLogoPath = $imageStorage->store($logo, 'clients/logos', 512, 512);
            $cliente->logo_path = $newLogoPath;
        }

        try {
            $cliente->save();
        } catch (Throwable $exception) {
            if ($newLogoPath !== null) {
                try {
                    $imageStorage->delete($newLogoPath);
                } catch (Throwable $cleanupException) {
                    report($cleanupException);
                }
            }

            throw $exception;
        }

        $emailSent = $activityMailer->send(
            action: 'Actualización de',
            entityType: 'cliente',
            entityName: $cliente->name,
            actorName: $request->user()->name,
            details: [
                'Empresa' => $cliente->company ?? 'No especificada',
                'Correo del cliente' => $cliente->email ?? 'No especificado',
                'Estado' => $cliente->status,
            ],
        );

        if ($newLogoPath !== null) {
            $imageStorage->delete($oldLogoPath);
        }

        $this->flashActivityToast('Cliente actualizado correctamente.', $emailSent);

        return to_route('management.clientes.index');
    }

    public function destroy(
        Request $request,
        Client $cliente,
        DeletionRequestService $deletionRequests,
    ): RedirectResponse {
        try {
            $result = $deletionRequests->request($cliente, $request->user());
        } catch (\LogicException $exception) {
            throw ValidationException::withMessages(['delete' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => $result['email_sent'] || ! $result['created'] ? 'success' : 'warning',
            'message' => ! $result['created']
                ? 'Ya existe una solicitud pendiente para este cliente.'
                : ($result['email_sent']
                    ? 'Solicitud enviada al administrador para aprobar la eliminación del cliente.'
                    : 'La solicitud quedó registrada, pero no se pudo enviar el correo al administrador.'),
        ]);

        return to_route('management.clientes.index');
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
