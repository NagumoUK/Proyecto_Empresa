<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\DeletionRequest;
use App\Services\ManagementChangeRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class DeletionRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $selectedRequestId = $request->integer('solicitud');

        return Inertia::render('management/deletion-requests/index', [
            'selectedRequestId' => $selectedRequestId > 0 ? $selectedRequestId : null,
            'requests' => DeletionRequest::query()
                ->with('reviewer:id,name')
                ->when(
                    $selectedRequestId > 0,
                    fn ($query) => $query->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$selectedRequestId]),
                )
                ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
                ->latest()
                ->paginate(20)
                ->through(fn (DeletionRequest $deletionRequest): array => [
                    'id' => $deletionRequest->id,
                    'action' => $deletionRequest->action,
                    'entity_type' => $deletionRequest->entity_type,
                    'entity_name' => $deletionRequest->entity_name,
                    'details' => collect($deletionRequest->details ?? [])
                        ->except(['logo_path', 'old_logo_path', 'files'])
                        ->all(),
                    'changes' => [
                        'before' => $this->displayFields(
                            $deletionRequest->entity_type,
                            data_get($deletionRequest->changes, 'before', []),
                            isBefore: true,
                        ),
                        'proposed' => $this->displayFields(
                            $deletionRequest->entity_type,
                            data_get($deletionRequest->changes, 'attributes', []),
                        ),
                    ],
                    'requester_name' => $deletionRequest->requester_name,
                    'status' => $deletionRequest->status,
                    'created_at' => $deletionRequest->created_at?->toIso8601String(),
                    'reviewed_at' => $deletionRequest->reviewed_at?->toIso8601String(),
                    'reviewer' => $deletionRequest->reviewer?->only(['name']),
                ]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, scalar|null>
     */
    private function displayFields(string $entityType, array $attributes, bool $isBefore = false): array
    {
        $allowedFields = $entityType === 'client'
            ? ['name', 'company', 'email', 'phone', 'address', 'status']
            : ['client_id', 'name', 'description', 'status', 'budget', 'budget_currency', 'start_date', 'end_date'];

        $fields = collect($attributes)
            ->only($allowedFields)
            ->map(fn (mixed $value): int|float|string|bool|null => is_scalar($value) ? $value : null);

        if ($entityType === 'client' && array_key_exists('logo_path', $attributes)) {
            $fields->put(
                'logo',
                blank($attributes['logo_path'])
                    ? 'Sin logotipo'
                    : ($isBefore ? 'Logotipo actual' : 'Nuevo logotipo adjunto'),
            );
        }

        if ($entityType === 'project' && array_key_exists('client_id', $attributes)) {
            $client = Client::query()->find($attributes['client_id']);
            $fields->forget('client_id');
            $fields->put('client', $client?->company ?? $client?->name ?? 'Cliente no disponible');
        }

        return $fields->all();
    }

    public function approve(
        Request $request,
        DeletionRequest $deletionRequest,
        ManagementChangeRequestService $changeRequests,
    ): RedirectResponse {
        $result = $changeRequests->approve($deletionRequest, $request->user());

        if ($result['applied']) {
            try {
                $changeRequests->cleanAfterApproval($deletionRequest);
            } catch (RuntimeException $exception) {
                report($exception);
                Inertia::flash('toast', [
                    'type' => 'warning',
                    'message' => 'El cambio fue aprobado, pero no se pudieron limpiar todos los archivos asociados. Revisa los registros del sistema.',
                ]);

                return back();
            }

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => $result['deleted']
                    ? 'Solicitud aprobada y registro eliminado.'
                    : 'Solicitud aprobada y cambio aplicado.',
            ]);

            return back();
        }

        try {
            $changeRequests->cleanRejectedChange($deletionRequest);
        } catch (RuntimeException $exception) {
            report($exception);
        }

        return back()->withErrors([
            'approval' => "No se aplicó el cambio: {$result['reason']}",
        ]);
    }

    public function reject(
        Request $request,
        DeletionRequest $deletionRequest,
        ManagementChangeRequestService $changeRequests,
    ): RedirectResponse {
        $changeRequests->reject($deletionRequest, $request->user());

        try {
            $changeRequests->cleanRejectedChange($deletionRequest);
        } catch (RuntimeException $exception) {
            report($exception);
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => 'La solicitud fue rechazada, pero no se pudieron limpiar todos los archivos pendientes. Revisa los registros del sistema.',
            ]);

            return back();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Solicitud rechazada; el cambio no se aplicó.',
        ]);

        return back();
    }
}
