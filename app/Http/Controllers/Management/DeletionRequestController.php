<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Models\DeletionRequest;
use App\Services\DeletionRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class DeletionRequestController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('management/deletion-requests/index', [
            'requests' => DeletionRequest::query()
                ->with('reviewer:id,name')
                ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
                ->latest()
                ->paginate(20)
                ->through(fn (DeletionRequest $deletionRequest): array => [
                    'id' => $deletionRequest->id,
                    'entity_type' => $deletionRequest->entity_type,
                    'entity_name' => $deletionRequest->entity_name,
                    'details' => collect($deletionRequest->details ?? [])
                        ->except(['logo_path', 'files'])
                        ->all(),
                    'requester_name' => $deletionRequest->requester_name,
                    'status' => $deletionRequest->status,
                    'created_at' => $deletionRequest->created_at?->toIso8601String(),
                    'reviewed_at' => $deletionRequest->reviewed_at?->toIso8601String(),
                    'reviewer' => $deletionRequest->reviewer?->only(['name']),
                ]),
        ]);
    }

    public function approve(
        Request $request,
        DeletionRequest $deletionRequest,
        DeletionRequestService $deletionRequests,
    ): RedirectResponse {
        $result = $deletionRequests->approve($deletionRequest, $request->user());

        if ($result['deleted']) {
            try {
                $deletionRequests->cleanDeletedFiles($deletionRequest);
            } catch (RuntimeException $exception) {
                report($exception);
                Inertia::flash('toast', [
                    'type' => 'warning',
                    'message' => 'El registro fue eliminado, pero no se pudieron borrar todos sus archivos. Revisa los registros del sistema.',
                ]);

                return back();
            }

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => 'Solicitud aprobada y registro eliminado.',
            ]);

            return back();
        }

        return back()->withErrors([
            'approval' => "La solicitud fue rechazada porque {$result['reason']}",
        ]);
    }

    public function reject(
        Request $request,
        DeletionRequest $deletionRequest,
        DeletionRequestService $deletionRequests,
    ): RedirectResponse {
        $deletionRequests->reject($deletionRequest, $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Solicitud rechazada; el registro se conserva.',
        ]);

        return back();
    }
}
