<?php

use App\Models\Client;
use App\Models\CompanyActivity;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    fakeDashboardWeather();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();

    Http::assertSentCount(1);
});

test('dashboard summaries and project list use saved business records', function () {
    $user = User::factory()->create();
    $client = Client::query()->create([
        'name' => 'Cliente del dashboard',
        'status' => 'active',
    ]);
    Project::query()->create([
        'client_id' => $client->id,
        'name' => 'Proyecto del dashboard',
        'status' => 'active',
    ]);
    fakeDashboardWeather();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('metrics.clients', 1)
            ->where('metrics.activeProjects', 1)
            ->where('projectStatuses.active', 1)
            ->where('projects.0.name', 'Proyecto del dashboard')
        );

    Http::assertSentCount(1);
});

test('dashboard includes daily activity counts for the last 180 days', function () {
    $this->travelTo(Carbon::parse('2026-11-13 12:00:00'));

    $user = User::factory()->create();

    $yesterdayActivity = new CompanyActivity([
        'user_id' => $user->id,
        'description' => 'Actividad de ayer',
    ]);
    $yesterdayActivity->created_at = now()->subDay();
    $yesterdayActivity->updated_at = now()->subDay();
    $yesterdayActivity->save();

    CompanyActivity::query()->create([
        'user_id' => $user->id,
        'description' => 'Primera actividad de hoy',
    ]);
    CompanyActivity::query()->create([
        'user_id' => $user->id,
        'description' => 'Segunda actividad de hoy',
    ]);

    $oldActivity = new CompanyActivity([
        'user_id' => $user->id,
        'description' => 'Actividad fuera del periodo',
    ]);
    $oldActivity->created_at = now()->subDays(180);
    $oldActivity->updated_at = now()->subDays(180);
    $oldActivity->save();

    fakeDashboardWeather();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('activitySeries.0.date', '2026-05-18')
            ->where('activitySeries.0.count', 0)
            ->where('activitySeries.178.date', '2026-11-12')
            ->where('activitySeries.178.count', 1)
            ->where('activitySeries.179.date', '2026-11-13')
            ->where('activitySeries.179.count', 2)
            ->has('activitySeries', 180)
        );

    Http::assertSentCount(1);
});
