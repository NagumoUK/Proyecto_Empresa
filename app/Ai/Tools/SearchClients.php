<?php

namespace App\Ai\Tools;

use App\Models\Client;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SearchClients implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Busca clientes por nombre, empresa o estado. Devuelve solo el identificador, nombre, empresa y estado.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $clientsQuery = Client::query()->select(['id', 'name', 'company', 'status']);

        $searchTerm = $request->string('search')->trim()->lower()->toString();

        if ($searchTerm !== '') {
            $search = '%'.$searchTerm.'%';
            $clientsQuery->where(function (Builder $query) use ($search): void {
                $query->whereRaw('LOWER(name) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(company) LIKE ?', [$search]);
            });
        }

        $status = $request->string('status')->toString();

        if ($status !== '') {
            $clientsQuery->where('status', $status);
        }

        $totalMatches = (clone $clientsQuery)->count();
        $clients = $clientsQuery
            ->orderBy('name')
            ->orderBy('id')
            ->limit(20)
            ->get()
            ->map(static fn (Client $client): array => [
                'id' => $client->getKey(),
                'name' => $client->name,
                'company' => $client->company,
                'status' => $client->status,
            ])
            ->all();

        return json_encode([
            'total_matches' => $totalMatches,
            'returned' => count($clients),
            'truncated' => $totalMatches > count($clients),
            'clients' => $clients,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()
                ->description('Texto opcional para buscar por nombre o empresa.')
                ->nullable(),
            'status' => $schema->string()
                ->enum(['active', 'inactive'])
                ->description('Filtra por estado; omítelo para incluir ambos.')
                ->nullable(),
        ];
    }
}
