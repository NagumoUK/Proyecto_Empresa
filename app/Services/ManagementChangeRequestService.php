<?php

namespace App\Services;

use App\Mail\ManagementChangeRequestMail;
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

class ManagementChangeRequestService
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return array{request: DeletionRequest, email_sent: bool, created: bool}
     */
    public function requestCreate(string $entityType, array $attributes, User $requester): array
    {
        abort_unless(in_array($entityType, ['client', 'project'], true), 422);

        $entityName = $attributes['name'];
        $details = $this->detailsForChanges($entityType, $attributes);

        return $this->createRequest(
            entityType: $entityType,
            action: 'create',
            entityId: null,
            entityName: $entityName,
            details: $details,
            changes: ['attributes' => $attributes],
            requester: $requester,
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{request: DeletionRequest, email_sent: bool, created: bool}
     */
    public function requestUpdate(Client|Project $target, array $attributes, User $requester): array
    {
        $entityType = $target instanceof Client ? 'client' : 'project';
        $deletionRequest = DB::transaction(function () use ($entityType, $target, $attributes, $requester): DeletionRequest {
            $lockedTarget = $target->newQuery()
                ->whereKey($target->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $hasPending = DeletionRequest::query()
                ->where('entity_type', $entityType)
                ->where('entity_id', $lockedTarget->getKey())
                ->where('status', 'pending')
                ->exists();

            if ($hasPending) {
                throw new LogicException('Ya existe una solicitud pendiente para este registro.');
            }

            $details = $this->detailsForChanges($entityType, $attributes);
            if ($lockedTarget instanceof Client && array_key_exists('logo_path', $attributes)) {
                $details['old_logo_path'] = $lockedTarget->logo_path;
            }

            $changes = [
                'attributes' => $attributes,
                'before' => array_intersect_key(
                    $lockedTarget->getAttributes(),
                    $attributes,
                ),
            ];

            return DeletionRequest::query()->create([
                'entity_type' => $entityType,
                'action' => 'update',
                'entity_id' => $lockedTarget->getKey(),
                'entity_name' => $attributes['name'],
                'details' => $details,
                'changes' => $changes,
                'requested_by' => $requester->getKey(),
                'requester_name' => $requester->name,
                'status' => 'pending',
            ]);
        });

        $created = $deletionRequest->wasRecentlyCreated;
        $emailSent = $created && $this->sendReviewEmail($deletionRequest);

        return [
            'request' => $deletionRequest,
            'email_sent' => $emailSent,
            'created' => $created,
        ];
    }

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

            return DeletionRequest::query()->create([
                'entity_type' => $entityType,
                'action' => 'delete',
                'entity_id' => $lockedTarget->getKey(),
                'entity_name' => $lockedTarget->name,
                'details' => $this->details($lockedTarget),
                'requested_by' => $requester->getKey(),
                'requester_name' => $requester->name,
                'status' => 'pending',
            ]);
        });

        $created = $deletionRequest->wasRecentlyCreated;
        $emailSent = $created && $this->sendReviewEmail($deletionRequest);

        return [
            'request' => $deletionRequest,
            'email_sent' => $emailSent,
            'created' => $created,
        ];
    }

    /**
     * @return array{deleted: bool, applied: bool, reason: string|null}
     */
    public function approve(DeletionRequest $deletionRequest, User $reviewer): array
    {
        return DB::transaction(function () use ($deletionRequest, $reviewer): array {
            $lockedRequest = DeletionRequest::query()
                ->whereKey($deletionRequest->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($lockedRequest->status === 'pending', 409, 'Esta solicitud ya fue revisada.');

            if ($lockedRequest->action === 'create') {
                $attributes = data_get($lockedRequest->changes, 'attributes', []);
                $target = $this->createTarget($lockedRequest->entity_type, $attributes);
                $lockedRequest->entity_id = $target->getKey();
                $this->markReviewed($lockedRequest, $reviewer, 'approved');

                return ['deleted' => false, 'applied' => true, 'reason' => null];
            }

            $target = $this->findTarget($lockedRequest);

            if ($target === null) {
                $this->markReviewed($lockedRequest, $reviewer, 'rejected');

                return ['deleted' => false, 'applied' => false, 'reason' => 'El registro ya no existe.'];
            }

            if ($lockedRequest->action === 'update') {
                $changes = $lockedRequest->changes ?? [];
                $attributes = $changes['attributes'] ?? [];
                if (! $this->matchesSnapshot($target, $changes['before'] ?? [])) {
                    $this->markReviewed($lockedRequest, $reviewer, 'rejected');

                    return [
                        'deleted' => false,
                        'applied' => false,
                        'reason' => 'El registro cambió antes de que esta solicitud fuera aprobada.',
                    ];
                }

                $target->fill($attributes)->save();
                $this->markReviewed($lockedRequest, $reviewer, 'approved');

                return ['deleted' => false, 'applied' => true, 'reason' => null];
            }

            try {
                $this->ensureDeletable($target);
            } catch (LogicException $exception) {
                $this->markReviewed($lockedRequest, $reviewer, 'rejected');

                return ['deleted' => false, 'applied' => false, 'reason' => $exception->getMessage()];
            }

            abort_unless($lockedRequest->action === 'delete', 422);

            if ($target instanceof Client) {
                $target->delete();
            } else {
                $target->files()->delete();
                $target->delete();
            }

            $this->markReviewed($lockedRequest, $reviewer, 'approved');

            return ['deleted' => true, 'applied' => true, 'reason' => null];
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

    public function cleanAfterApproval(DeletionRequest $deletionRequest): void
    {
        if ($deletionRequest->action === 'update' && $deletionRequest->entity_type === 'client') {
            $oldLogoPath = data_get($deletionRequest->details, 'old_logo_path');
            if (is_string($oldLogoPath) && $oldLogoPath !== '') {
                app(ImageStorage::class)->delete($oldLogoPath);
            }

            return;
        }

        if ($deletionRequest->action !== 'delete') {
            return;
        }

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

    public function cleanRejectedChange(DeletionRequest $deletionRequest): void
    {
        if (
            $deletionRequest->entity_type !== 'client'
            || ! in_array($deletionRequest->action, ['create', 'update'], true)
        ) {
            return;
        }

        $newLogoPath = data_get($deletionRequest->changes, 'attributes.logo_path');
        if (is_string($newLogoPath) && $newLogoPath !== '') {
            app(ImageStorage::class)->delete($newLogoPath);
        }
    }

    private function sendReviewEmail(DeletionRequest $deletionRequest): bool
    {
        $recipient = config('app.admin_email');

        if (! is_string($recipient) || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            report(new LogicException('The admin email for deletion approvals is not configured.'));

            return false;
        }

        try {
            Mail::to($recipient)->send(new ManagementChangeRequestMail(
                action: match ($deletionRequest->action) {
                    'create' => 'crear',
                    'update' => 'actualizar',
                    default => 'eliminar',
                },
                entityType: $this->entityLabel($deletionRequest->entity_type),
                entityName: $deletionRequest->entity_name,
                requesterName: $deletionRequest->requester_name,
                details: $this->emailDetails($deletionRequest),
                reviewUrl: route('management.solicitudes-eliminacion.index', [
                    'solicitud' => $deletionRequest->getKey(),
                ]),
            ));
        } catch (TransportExceptionInterface $exception) {
            report($exception);

            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $details
     * @param  array<string, mixed>|null  $changes
     * @return array{request: DeletionRequest, email_sent: bool, created: bool}
     */
    private function createRequest(
        string $entityType,
        string $action,
        ?int $entityId,
        string $entityName,
        array $details,
        ?array $changes,
        User $requester,
    ): array {
        $deletionRequest = DeletionRequest::query()->create([
            'entity_type' => $entityType,
            'action' => $action,
            'entity_id' => $entityId,
            'entity_name' => $entityName,
            'details' => $details,
            'changes' => $changes,
            'requested_by' => $requester->getKey(),
            'requester_name' => $requester->name,
            'status' => 'pending',
        ]);

        return [
            'request' => $deletionRequest,
            'email_sent' => $this->sendReviewEmail($deletionRequest),
            'created' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createTarget(string $entityType, array $attributes): Client|Project
    {
        return match ($entityType) {
            'client' => Client::query()->create($attributes),
            'project' => Project::query()->create($attributes),
            default => abort(422),
        };
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function matchesSnapshot(Model $target, array $snapshot): bool
    {
        foreach ($snapshot as $key => $expectedValue) {
            $currentValue = $target->getAttributes()[$key] ?? null;
            if ($currentValue !== null && $expectedValue !== null) {
                $currentValue = (string) $currentValue;
                $expectedValue = (string) $expectedValue;
            }

            if ($currentValue !== $expectedValue) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function detailsForChanges(string $entityType, array $attributes): array
    {
        if ($entityType === 'client') {
            return [
                'company' => $attributes['company'] ?? 'No especificada',
                'email' => $attributes['email'] ?? 'No especificado',
                'phone' => $attributes['phone'] ?? 'No especificado',
                'address' => $attributes['address'] ?? 'No especificada',
                'status' => $attributes['status'],
                'logo_path' => $attributes['logo_path'] ?? '',
            ];
        }

        return [
            'client' => Client::query()->find($attributes['client_id'])?->company
                ?? Client::query()->find($attributes['client_id'])?->name
                ?? 'Cliente',
            'status' => $attributes['status'],
            'budget' => $attributes['budget'] ?? 'No definido',
            'start_date' => $attributes['start_date'] ?? 'No definida',
            'end_date' => $attributes['end_date'] ?? 'No definida',
        ];
    }

    private function entityLabel(string $entityType): string
    {
        return match ($entityType) {
            'client' => 'cliente',
            'project' => 'proyecto',
            default => $entityType,
        };
    }

    private function findTarget(DeletionRequest $deletionRequest): Client|Project|null
    {
        if ($deletionRequest->entity_id === null) {
            return null;
        }

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
        unset($details['logo_path'], $details['old_logo_path'], $details['files']);

        $labels = [
            'company' => 'Empresa',
            'email' => 'Correo',
            'phone' => 'Teléfono',
            'address' => 'Dirección',
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
