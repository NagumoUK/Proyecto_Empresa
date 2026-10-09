<?php

use App\Models\User;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

test('web requests expose a validated request id and keep user context hidden', function () {
    $user = User::factory()->create();
    $requestId = (string) Str::uuid();

    $response = $this->actingAs($user)
        ->withHeader('X-Request-ID', $requestId)
        ->get(route('dashboard'));

    $response->assertHeader('X-Request-ID', $requestId);

    expect(Context::get('request_id'))->toBe($requestId)
        ->and(Context::get('request_method'))->toBe('GET')
        ->and(Context::getHidden('user_id'))->toBe($user->getKey())
        ->and(Context::has('user_id'))->toBeFalse();
});

test('malformed request ids are replaced with a generated uuid', function () {
    $response = $this->withHeader('X-Request-ID', 'not-a-uuid')
        ->get(route('login'));

    $requestId = $response->headers->get('X-Request-ID');

    expect($requestId)->not->toBe('not-a-uuid')
        ->and(Str::isUuid($requestId))->toBeTrue();
});
