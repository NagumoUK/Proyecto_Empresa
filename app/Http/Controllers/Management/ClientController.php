<?php

namespace App\Http\Controllers\Management;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Management\IndexClientRequest;
use App\Http\Requests\Management\StoreClientRequest;
use App\Http\Requests\Management\UpdateClientRequest;
use App\Models\Client;
use App\Models\DeletionRequest;
use App\Services\ImageStorage;
use App\Services\ManagementActivityMailer;
use App\Services\ManagementChangeRequestService;
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
        ManagementChangeRequestService $changeRequests,
    ): RedirectResponse {
        $attributes = $request->safe()->except(['logo']);
        $logo = $request->file('logo');
        $logoPath = $logo instanceof UploadedFile
            ? $imageStorage->store($logo, 'clients/logos', 512, 512)
            : null;

        if ($logoPath !== null) {
            $attributes['logo_path'] = $logoPath;
        }

        if ($request->user()->role !== UserRole::Admin) {
            try {
                $result = $changeRequests->requestCreate('client', $attributes, $request->user());
            } catch (Throwable $exception) {
                $this->deleteStagedLogo($imageStorage, $logoPath);

                throw $exception;
            }

            $this->flashChangeRequest('creación de cliente', $result);

            return to_route('management.clientes.index');
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
        ManagementChangeRequestService $changeRequests,
    ): RedirectResponse {
        $attributes = $request->safe()->except(['logo']);

        $newLogoPath = null;
        $logo = $request->file('logo');

        if ($logo instanceof UploadedFile) {
            $newLogoPath = $imageStorage->store($logo, 'clients/logos', 512, 512);
            $attributes['logo_path'] = $newLogoPath;
        }

        if ($request->user()->role !== UserRole::Admin) {
            try {
                $result = $changeRequests->requestUpdate($cliente, $attributes, $request->user());
            } catch (Throwable $exception) {
                $this->deleteStagedLogo($imageStorage, $newLogoPath);

                if ($exception instanceof \LogicException) {
                    throw ValidationException::withMessages(['change' => $exception->getMessage()]);
                }

                throw $exception;
            }

            $this->flashChangeRequest('actualización de cliente', $result);

            return to_route('management.clientes.index');
        }

        $oldLogoPath = $cliente->logo_path;
        $cliente->fill($attributes);

        try {
            $cliente->save();
        } catch (Throwable $exception) {
            $this->deleteStagedLogo($imageStorage, $newLogoPath);

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
        ManagementChangeRequestService $changeRequests,
    ): RedirectResponse {
        try {
            $result = $changeRequests->request($cliente, $request->user());
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

    /**
     * @param  array{request: DeletionRequest, email_sent: bool, created: bool}  $result
     */
    private function flashChangeRequest(string $action, array $result): void
    {
        Inertia::flash('toast', [
            'type' => $result['email_sent'] ? 'success' : 'warning',
            'message' => $result['email_sent']
                ? "Solicitud de {$action} enviada al administrador."
                : "La solicitud de {$action} quedó guardada, pero no se pudo notificar al administrador.",
        ]);
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

    private function deleteStagedLogo(ImageStorage $imageStorage, ?string $logoPath): void
    {
        if ($logoPath === null) {
            return;
        }

        try {
            $imageStorage->delete($logoPath);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
