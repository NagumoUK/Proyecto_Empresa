<?php

use App\Events\ActivityCreated;
use App\Models\CompanyActivity;
use App\Models\User;
use Illuminate\Support\Facades\Event;

test('authenticated users can create an activity and broadcast it', function () {
    $user = User::factory()->create();
    Event::fake([ActivityCreated::class]);

    $this->actingAs($user)
        ->post(route('activities.store'), [
            'description' => 'Se revisó el avance del proyecto',
        ])
        ->assertRedirect(route('dashboard'));

    $activity = CompanyActivity::query()->firstOrFail();

    expect($activity->user_id)->toBe($user->id)
        ->and($activity->description)->toBe('Se revisó el avance del proyecto');

    Event::assertDispatched(ActivityCreated::class, function (ActivityCreated $event) use ($activity) {
        return $event->activity->is($activity);
    });
});

test('activity descriptions are required and limited to 280 characters', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('activities.store'), ['description' => ''])
        ->assertSessionHasErrors('description');

    $this->actingAs(User::factory()->create())
        ->post(route('activities.store'), ['description' => str_repeat('x', 281)])
        ->assertSessionHasErrors('description');

    $this->assertDatabaseCount('company_activities', 0);
});

test('dashboard returns the latest activity with its author', function () {
    $user = User::factory()->create(['name' => 'David Solano']);
    CompanyActivity::query()->create([
        'user_id' => $user->id,
        'description' => 'Se registró una actividad',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('activities.0.userName', 'David Solano')
            ->where('activities.0.description', 'Se registró una actividad')
        );
});

test('activity pruning deletes only records older than the selected period', function () {
    $user = User::factory()->create();
    $oldActivity = CompanyActivity::query()->create([
        'user_id' => $user->id,
        'description' => 'Registro antiguo',
    ]);
    $oldActivity->forceFill(['created_at' => now()->subDays(31)])->save();

    $recentActivity = CompanyActivity::query()->create([
        'user_id' => $user->id,
        'description' => 'Registro reciente',
    ]);

    $this->artisan('activities:prune', ['--days' => 30, '--pretend' => true])
        ->assertSuccessful();
    $this->assertDatabaseCount('company_activities', 2);

    $this->artisan('activities:prune', ['--days' => 30])
        ->assertSuccessful();

    $this->assertDatabaseMissing('company_activities', ['id' => $oldActivity->id]);
    $this->assertDatabaseHas('company_activities', ['id' => $recentActivity->id]);
});

test('activity pruning rejects a non-positive retention period', function () {
    $this->artisan('activities:prune', ['--days' => 0])
        ->assertFailed();
});
