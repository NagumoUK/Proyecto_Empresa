<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StoredImageController extends Controller
{
    public function avatar(Request $request): StreamedResponse
    {
        return $this->response($request->user()->avatar_path);
    }

    public function clientLogo(Client $cliente): StreamedResponse
    {
        return $this->response($cliente->logo_path);
    }

    private function response(?string $path): StreamedResponse
    {
        abort_if(blank($path), 404);

        $disk = Storage::disk('local');

        abort_unless($disk->exists($path), 404);

        return $disk->response($path, basename($path), [
            'Cache-Control' => 'private, max-age=3600',
            'Content-Type' => 'image/webp',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
