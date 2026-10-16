<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Http\Requests\Management\StoreProjectFileRequest;
use App\Models\Project;
use App\Models\ProjectFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ProjectFileController extends Controller
{
    public function store(StoreProjectFileRequest $request, Project $proyecto): RedirectResponse
    {
        $uploadedFile = $request->file('file');
        $disk = (string) config('filesystems.project_files_disk', 'local');

        if (! $uploadedFile instanceof UploadedFile) {
            throw new RuntimeException('The validated project file is missing.');
        }

        if (data_get(config("filesystems.disks.{$disk}"), 'visibility', 'private') !== 'private') {
            throw new RuntimeException('Project file storage must use private visibility.');
        }

        $path = $uploadedFile->store("projects/{$proyecto->id}", $disk);

        if (! is_string($path)) {
            throw new RuntimeException('The project file could not be stored.');
        }

        try {
            $proyecto->files()->create([
                'uploaded_by' => $request->user()->id,
                'disk' => $disk,
                'path' => $path,
                'original_name' => basename($uploadedFile->getClientOriginalName()),
                'mime_type' => $uploadedFile->getMimeType() ?? 'application/octet-stream',
                'size' => $uploadedFile->getSize(),
            ]);
        } catch (Throwable $exception) {
            try {
                if (! Storage::disk($disk)->delete($path)) {
                    throw new RuntimeException('The uploaded project file could not be cleaned up.');
                }
            } catch (Throwable $cleanupException) {
                report($cleanupException);
            }

            throw $exception;
        }

        return to_route('management.proyectos.show', $proyecto)
            ->with('success', 'Archivo agregado al proyecto.');
    }

    public function download(Project $proyecto, ProjectFile $archivo): StreamedResponse
    {
        $file = $proyecto->files()->findOrFail($archivo->getKey());
        $disk = Storage::disk($file->disk);

        abort_unless($disk->exists($file->path), 404);

        return $disk->download(
            $file->path,
            basename($file->original_name),
            [
                'Content-Type' => $file->mime_type,
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    public function destroy(Project $proyecto, ProjectFile $archivo): RedirectResponse
    {
        $file = $proyecto->files()->findOrFail($archivo->getKey());
        $disk = Storage::disk($file->disk);

        if (! $disk->delete($file->path) && $disk->exists($file->path)) {
            throw new RuntimeException('The project file could not be deleted from storage.');
        }

        $file->delete();

        return to_route('management.proyectos.show', $proyecto)
            ->with('success', 'Archivo eliminado del proyecto.');
    }
}
