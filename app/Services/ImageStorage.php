<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Image;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ImageStorage
{
    public function store(
        UploadedFile $uploadedImage,
        string $directory,
        int $width,
        int $height,
        bool $cover = false,
    ): string {
        $image = Image::fromUpload($uploadedImage)->orient();
        $image = $cover
            ? $image->cover($width, $height)
            : $image->scale($width, $height);

        $path = $image
            ->toWebp()
            ->quality(82)
            ->store($directory, 'local');

        if (! is_string($path)) {
            throw new RuntimeException('The processed image could not be stored.');
        }

        return $path;
    }

    public function delete(?string $path): void
    {
        if (blank($path)) {
            return;
        }

        $disk = Storage::disk('local');

        if (! $disk->delete($path) && $disk->exists($path)) {
            throw new RuntimeException('The stored image could not be deleted.');
        }
    }
}
