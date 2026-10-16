<?php

use App\Contracts\CompanyActivityRecorder;
use App\Events\ActivityCreated;
use App\Models\CompanyActivity;
use App\Models\User;
use App\Notifications\ActivityCreatedNotification;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

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

test('activity descriptions must be strings between 1 and 280 characters', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('activities.store'), ['description' => ''])
        ->assertSessionHasErrors('description');

    $this->actingAs(User::factory()->create())
        ->post(route('activities.store'), ['description' => ['invalid']])
        ->assertSessionHasErrors('description');

    $this->assertDatabaseCount('company_activities', 0);

    $this->actingAs(User::factory()->create())
        ->post(route('activities.store'), ['description' => str_repeat('x', 281)])
        ->assertSessionHasErrors('description');

    $this->assertDatabaseCount('company_activities', 0);
});

test('activity broadcasts are discarded when their transaction rolls back', function () {
    $user = User::factory()->create();
    Event::fake([ActivityCreated::class]);

    expect(fn () => DB::transaction(function () use ($user): void {
        app(CompanyActivityRecorder::class)->record($user, 'No debe persistir');

        throw new RuntimeException('Rollback activity transaction.');
    }))->toThrow(RuntimeException::class, 'Rollback activity transaction.');

    $this->assertDatabaseCount('company_activities', 0);
    Event::assertNotDispatched(ActivityCreated::class);
});

test('activity events are handled by the discovered logging listener', function () {
    $user = User::factory()->create();
    Log::spy();

    $this->actingAs($user)
        ->post(route('activities.store'), ['description' => 'Registro para el log'])
        ->assertRedirect(route('dashboard'));

    $activity = CompanyActivity::query()->firstOrFail();

    Log::shouldHaveReceived('info')
        ->once()
        ->with('Company activity created.', [
            'activity_id' => $activity->getKey(),
        ]);
});

test('activity emails are queued for every verified user', function () {
    $author = User::factory()->create();
    User::factory()->create();
    User::factory()->unverified()->create();
    Queue::fake();

    $this->actingAs($author)
        ->post(route('activities.store'), ['description' => 'Actividad para el equipo'])
        ->assertRedirect(route('dashboard'));

    Queue::assertPushed(SendQueuedNotifications::class, 2);
});

test('activity emails are discarded when their transaction rolls back', function () {
    $user = User::factory()->create();
    Queue::fake();

    expect(fn () => DB::transaction(function () use ($user): void {
        app(CompanyActivityRecorder::class)->record($user, 'No debe enviarse');

        throw new RuntimeException('Rollback activity notification.');
    }))->toThrow(RuntimeException::class, 'Rollback activity notification.');

    Queue::assertNothingPushed();
});

test('activity emails are sent only to verified users', function () {
    $author = User::factory()->create();
    $recipient = User::factory()->create();
    $unverifiedUser = User::factory()->unverified()->create();
    Notification::fake();

    $this->actingAs($author)
        ->post(route('activities.store'), ['description' => 'Actividad verificada'])
        ->assertRedirect(route('dashboard'));

    Notification::assertSentTo(
        [$author, $recipient],
        ActivityCreatedNotification::class,
    );
    Notification::assertNotSentTo(
        $unverifiedUser,
        ActivityCreatedNotification::class,
    );
});

test('activity emails escape user-provided content', function () {
    $author = User::factory()->create([
        'name' => '<script>alert("name")</script>',
    ]);
    $recipient = User::factory()->create([
        'name' => '<script>alert("recipient")</script>',
    ]);
    $activity = CompanyActivity::query()->create([
        'user_id' => $author->id,
        'description' => '<script>alert("description")</script>',
    ]);

    $renderedEmail = (string) (new ActivityCreatedNotification($activity))
        ->toMail($recipient)
        ->render();

    expect($renderedEmail)
        ->toContain('&lt;script&gt;')
        ->not->toContain('<script>');
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
