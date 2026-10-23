<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use LogicException;

class ExternalApiClient
{
    /**
     * Send a GET request to the configured external API.
     *
     * @param  array<string, mixed>  $query
     */
    public function get(string $uri, array $query = []): Response
    {
        return $this->request()->get($uri, $query)->throw();
    }

    /**
     * Send a POST request to the configured external API.
     *
     * @param  array<string, mixed>  $data
     */
    public function post(string $uri, array $data = []): Response
    {
        return $this->request()->post($uri, $data)->throw();
    }

    private function request(): PendingRequest
    {
        $baseUrl = config('services.external_api.base_url');

        if (! is_string($baseUrl) || blank($baseUrl)) {
            throw new LogicException('The external API base URL is not configured.');
        }

        $request = Http::acceptJson()
            ->baseUrl(rtrim($baseUrl, '/'))
            ->connectTimeout((int) config('services.external_api.connect_timeout', 3))
            ->timeout((int) config('services.external_api.timeout', 10));

        $token = config('services.external_api.token');

        if (is_string($token) && filled($token)) {
            $request->withToken($token);
        }

        return $request;
    }
}
