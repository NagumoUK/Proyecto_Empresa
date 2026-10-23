<?php

namespace App\Services;

use App\Mail\DeletionApprovalRequestMail;
use App\Models\Client;
use App\Models\DeletionRequest;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use LogicException;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class DeletionRequestService
{
    /**
     * @return array{request: DeletionRequest, email_sent: bool, created: bool}
     */
    public function request(Client|Project $target, User $requester): array
    {
        $entityType = $target instanceof Client ? 'client' : 'project';

        $deletionRequest = DB::transaction(function () use ($entityType, $target, $requester): DeletionRequest {
            $lockedTarget = $target->newQuery()
                ->whereKey($target->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureDeletable($lockedTarget);

            $pendingRequest = DeletionRequest::query()
                ->where('entity_type', $entityType)
                ->where('entity_id', $lockedTarget->getKey())
                ->where('status', 'pending')
                ->first();

            if ($pendingRequest !== null) {
                return $pendingRequest;
            }

            $details = $this->details($lockedTarget);

            return DeletionRequest::query()->create([
                'entity_type' => $entityType,
                'entity_id' => $lockedTarget->getKey(),
                'entity_name' => $lockedTarget->name,
                'details' => $details,
                'requested_by' => $requester->getKey(),
                'requester_name' => $requester->name,
                'status' => 'pending',
            ]);
        });

        $created = $deletionRequest->wasRecentlyCreated;
        $emailSent = $created && $this->sendApprovalEmail($deletionRequest, $requester);

        return [
            'request' => $deletionRequest,
            'email_sent' => $emailSent,
            'created' => $created,
        ];
    }

    public function approve(DeletionRequest $deletionRequest, User $reviewer): array
    {
        return DB::transaction(function () use ($deletionRequest, $reviewer): array {
            $lockedRequest = DeletionRequest::query()
                ->whereKey($deletionRequest->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($lockedRequest->status === 'pending', 409, 'Esta solicitud ya fue revisada.');

            $target = $this->findTarget($lockedRequest);

            if ($target === null) {
                $this->markReviewed($lockedRequest, $reviewer, 'rejected');

                return ['deleted' => false, 'reason' => 'El registro ya no existe.'];
            }

            try {
                $this->ensureDeletable($target);
            } catch (LogicException $exception) {
                $this->markReviewed($lockedRequest, $reviewer, 'rejected');

                return ['deleted' => false, 'reason' => $exception->getMessage()];
            }

            if ($target instanceof Client) {
                $target->delete();
            } else {
                $target->files()->delete();
                $target->delete();
            }

            $this->markReviewed($lockedRequest, $reviewer, 'approved');

            return ['deleted' => true, 'reason' => null];
        });
    }

    public function reject(DeletionRequest $deletionRequest, User $reviewer): void
    {
        DB::transaction(function () use ($deletionRequest, $reviewer): void {
            $lockedRequest = DeletionRequest::query()
                ->whereKey($deletionRequest->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($lockedRequest->status === 'pending', 409, 'Esta solicitud ya fue revisada.');

            $this->markReviewed($lockedRequest, $reviewer, 'rejected');
        });
    }

    public function cleanDeletedFiles(DeletionRequest $deletionRequest): void
    {
        if ($deletionRequest->entity_type === 'client') {
            $logoPath = data_get($deletionRequest->details, 'logo_path');

            if (is_string($logoPath) && $logoPath !== '') {
                $disk = Storage::disk('local');

                if (! $disk->delete($logoPath) && $disk->exists($logoPath)) {
                    throw new RuntimeException('The client logo could not be deleted from storage.');
                }
            }

            return;
        }

        $projectFiles = data_get($deletionRequest->details, 'files', []);

        foreach ($projectFiles as $file) {
            if (is_array($file) && is_string($file['disk'] ?? null) && is_string($file['path'] ?? null)) {
                $disk = Storage::disk($file['disk']);

                if (! $disk->delete($file['path']) && $disk->exists($file['path'])) {
                    throw new RuntimeException('A project attachment could not be deleted from storage.');
                }
            }
        }
    }

    private function sendApprovalEmail(DeletionRequest $deletionRequest, User $requester): bool
    {
        $recipient = config('app.admin_email');

        if (! is_string($recipient) || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            report(new LogicException('The admin email for deletion approvals is not configured.'));

            return false;
        }

        try {
            Mail::to($recipient)->send(new DeletionApprovalRequestMail(
                entityType: $deletionRequest->entity_type === 'client' ? 'cliente' : 'proyecto',
                entityName: $deletionRequest->entity_name,
                requesterName: $requester->name,
                details: $this->emailDetails($deletionRequest),
                approvalUrl: route('management.solicitudes-eliminacion.index'),
            ));
        } catch (TransportExceptionInterface $exception) {
            report($exception);

            return false;
        }

        return true;
    }

    private function findTarget(DeletionRequest $deletionRequest): Client|Project|null
    {
        return match ($deletionRequest->entity_type) {
            'client' => Client::query()->whereKey($deletionRequest->entity_id)->first(),
            'project' => Project::query()->whereKey($deletionRequest->entity_id)->first(),
            default => null,
        };
    }

    private function ensureDeletable(Model $target): void
    {
        if ($target instanceof Client && ($target->projects()->exists() || $target->financialDocuments()->exists())) {
            throw new LogicException('No se puede eliminar el cliente porque tiene proyectos, cotizaciones o facturas asociadas.');
        }

        if ($target instanceof Project && $target->financialDocuments()->exists()) {
            throw new LogicException('No se puede eliminar el proyecto porque tiene cotizaciones o facturas asociadas.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function details(Client|Project $target): array
    {
        if ($target instanceof Client) {
            return [
                'company' => $target->company ?? 'No especificada',
                'email' => $target->email ?? 'No especificado',
                'status' => $target->status,
                'logo_path' => $target->logo_path ?? '',
            ];
        }

        $target->load('client');

        $details = [
            'client' => $target->client->company ?? $target->client->name,
            'status' => $target->status,
            'budget' => $target->budget === null ? 'No definido' : "S/ {$target->budget}",
            'start_date' => $target->start_date?->format('d/m/Y') ?? 'No definida',
            'end_date' => $target->end_date?->format('d/m/Y') ?? 'No definida',
        ];
        $files = $target->files()->get(['disk', 'path']);

        $details['files'] = $files
            ->map(fn (ProjectFile $file): array => [
                'disk' => $file->disk,
                'path' => $file->path,
            ])
            ->all();

        return $details;
    }

    /**
     * @return array<string, string>
     */
    private function emailDetails(DeletionRequest $deletionRequest): array
    {
        $details = $deletionRequest->details ?? [];
        unset($details['logo_path'], $details['files']);

        $labels = [
            'company' => 'Empresa',
            'email' => 'Correo',
            'status' => 'Estado',
            'client' => 'Cliente',
            'budget' => 'Presupuesto',
            'start_date' => 'Fecha de inicio',
            'end_date' => 'Fecha de fin',
        ];

        $formatted = [];

        foreach ($details as $key => $value) {
            $formatted[$labels[$key] ?? $key] = is_scalar($value) ? (string) $value : '';
        }

        return $formatted;
    }

    private function markReviewed(DeletionRequest $deletionRequest, User $reviewer, string $status): void
    {
        $deletionRequest->forceFill([
            'status' => $status,
            'reviewed_by' => $reviewer->getKey(),
            'reviewed_at' => now(),
        ])->save();
    }
}
