<?php

use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function createProjectForFileTest(): Project
{
    $client = Client::query()->create([
        'name' => 'Cliente de prueba',
        'status' => 'active',
    ]);

    return Project::query()->create([
        'client_id' => $client->id,
        'name' => 'Proyecto de prueba',
        'status' => 'planning',
    ]);
}

test('authenticated users can upload a private file to a project', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $project = createProjectForFileTest();
    $uploadedFile = UploadedFile::fake()->create('propuesta.pdf', 5, 'application/pdf');

    $this->actingAs($user)
        ->post(route('management.proyectos.archivos.store', $project), [
            'file' => $uploadedFile,
        ])
        ->assertRedirect(route('management.proyectos.show', $project));

    $projectFile = ProjectFile::query()->firstOrFail();

    expect($projectFile->project_id)->toBe($project->id)
        ->and($projectFile->uploaded_by)->toBe($user->id)
        ->and($projectFile->original_name)->toBe('propuesta.pdf')
        ->and($projectFile->path)->toStartWith("projects/{$project->id}/");

    Storage::disk('local')->assertExists($projectFile->path);

    $this->get(route('management.proyectos.show', $project))
        ->assertInertia(fn ($page) => $page
            ->component('management/projects/show')
            ->where('files.0.name', 'propuesta.pdf')
            ->where('files.0.mimeType', 'application/pdf')
        );
});

test('guests cannot upload or download project files', function () {
    $project = createProjectForFileTest();
    $projectFile = $project->files()->create([
        'disk' => 'local',
        'path' => 'projects/'.$project->id.'/private.pdf',
        'original_name' => 'private.pdf',
        'mime_type' => 'application/pdf',
        'size' => 5,
    ]);

    $this->post(route('management.proyectos.archivos.store', $project), [
        'file' => UploadedFile::fake()->create('propuesta.pdf', 5, 'application/pdf'),
    ])->assertRedirect(route('login'));

    $this->get(route('management.proyectos.archivos.download', [
        'proyecto' => $project,
        'archivo' => $projectFile,
    ]))->assertRedirect(route('login'));
});

test('project file uploads reject unsupported types and files larger than 20 MB', function () {
    Storage::fake('local');
    $project = createProjectForFileTest();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('management.proyectos.archivos.store', $project), [
            'file' => UploadedFile::fake()->create('malware.exe', 1, 'application/octet-stream'),
        ])
        ->assertSessionHasErrors('file');

    $this->actingAs($user)
        ->post(route('management.proyectos.archivos.store', $project), [
            'file' => UploadedFile::fake()->create('large.pdf', 20481, 'application/pdf'),
        ])
        ->assertSessionHasErrors('file');

    $this->assertDatabaseCount('project_files', 0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

test('project files can be downloaded only through their owning project', function () {
    Storage::fake('local');
    $this->actingAs(User::factory()->create());
    $project = createProjectForFileTest();
    $otherProject = createProjectForFileTest();
    $path = "projects/{$project->id}/propuesta.pdf";
    Storage::disk('local')->put($path, 'document');
    $projectFile = $project->files()->create([
        'disk' => 'local',
        'path' => $path,
        'original_name' => 'propuesta.pdf',
        'mime_type' => 'application/pdf',
        'size' => 8,
    ]);

    $this->get(route('management.proyectos.archivos.download', [
        'proyecto' => $project,
        'archivo' => $projectFile,
    ]))->assertDownload('propuesta.pdf');

    $this->get(route('management.proyectos.archivos.download', [
        'proyecto' => $otherProject,
        'archivo' => $projectFile,
    ]))->assertNotFound();
});

test('deleting a project file removes its stored object and record', function () {
    Storage::fake('local');
    $this->actingAs(User::factory()->create());
    $project = createProjectForFileTest();
    $path = "projects/{$project->id}/propuesta.pdf";
    Storage::disk('local')->put($path, 'document');
    $projectFile = $project->files()->create([
        'disk' => 'local',
        'path' => $path,
        'original_name' => 'propuesta.pdf',
        'mime_type' => 'application/pdf',
        'size' => 8,
    ]);

    $this->delete(route('management.proyectos.archivos.destroy', [
        'proyecto' => $project,
        'archivo' => $projectFile,
    ]))->assertRedirect(route('management.proyectos.show', $project));

    $this->assertDatabaseMissing('project_files', ['id' => $projectFile->id]);
    Storage::disk('local')->assertMissing($path);
});

test('project files cannot be deleted through a different project', function () {
    Storage::fake('local');
    $this->actingAs(User::factory()->create());
    $project = createProjectForFileTest();
    $otherProject = createProjectForFileTest();
    $path = "projects/{$project->id}/propuesta.pdf";
    Storage::disk('local')->put($path, 'document');
    $projectFile = $project->files()->create([
        'disk' => 'local',
        'path' => $path,
        'original_name' => 'propuesta.pdf',
        'mime_type' => 'application/pdf',
        'size' => 8,
    ]);

    $this->delete(route('management.proyectos.archivos.destroy', [
        'proyecto' => $otherProject,
        'archivo' => $projectFile,
    ]))->assertNotFound();

    $this->assertDatabaseHas('project_files', ['id' => $projectFile->id]);
    Storage::disk('local')->assertExists($path);
});
