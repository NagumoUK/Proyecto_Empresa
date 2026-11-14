<?php

use App\Ai\Agents\BusinessDataAssistant;
use App\Ai\Tools\SearchClients;
use App\Ai\Tools\SearchProjects;
use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request as ToolRequest;

test('guests are redirected from the business assistant', function () {
    $this->get(route('assistant.index'))
        ->assertRedirect(route('login'));
});

test('verified users can open the business assistant', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('assistant.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('assistant'));
});

test('users can ask questions without an OpenAI key and continue their own stored conversation', function () {
    config([
        'ai.providers.openai.key' => null,
    ]);

    BusinessDataAssistant::fake(['Hay tres clientes activos.']);
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson(route('assistant.ask'), [
            'question' => '¿Cuántos clientes están activos?',
        ])
        ->assertOk()
        ->assertJsonPath('answer', 'Hay tres clientes activos.');

    $conversationId = $response->json('conversationId');

    expect($conversationId)->toBeString();
    $this->assertDatabaseHas('agent_conversations', [
        'id' => $conversationId,
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
    ]);

    BusinessDataAssistant::assertPrompted('¿Cuántos clientes están activos?');
});

test('the question endpoint returns 404 when a conversation belongs to another user', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $conversationId = createBusinessAssistantTestConversation($owner, BusinessDataAssistant::class);
    BusinessDataAssistant::fake();

    $this->actingAs($otherUser)
        ->postJson(route('assistant.ask'), [
            'question' => 'Resume esta conversación.',
            'conversation_id' => $conversationId,
        ])
        ->assertNotFound();

    BusinessDataAssistant::assertNeverPrompted();
});

test('the question endpoint returns 404 for another agents conversation', function () {
    $user = User::factory()->create();
    $conversationId = createBusinessAssistantTestConversation($user, 'App\\Ai\\Agents\\OtherAgent');
    BusinessDataAssistant::fake();

    $this->actingAs($user)
        ->postJson(route('assistant.ask'), [
            'question' => 'Resume esta conversación.',
            'conversation_id' => $conversationId,
        ])
        ->assertNotFound();

    BusinessDataAssistant::assertNeverPrompted();
});

test('the question endpoint rejects questions longer than 2000 characters', function () {
    BusinessDataAssistant::fake();

    $this->actingAs(User::factory()->create())
        ->postJson(route('assistant.ask'), [
            'question' => str_repeat('a', 2001),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('question');

    BusinessDataAssistant::assertNeverPrompted();
});

test('client lookup returns only allowlisted fields', function () {
    Client::query()->create([
        'name' => 'Northstar',
        'company' => 'Northstar Labs',
        'email' => 'private@example.test',
        'phone' => '555-0100',
        'address' => 'Private address',
        'status' => 'active',
    ]);

    $result = json_decode(
        (string) (new SearchClients)->handle(new ToolRequest([
            'search' => 'NORTHSTAR',
            'status' => 'active',
        ])),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($result['total_matches'])->toBe(1)
        ->and(array_keys($result['clients'][0]))->toBe(['id', 'name', 'company', 'status'])
        ->and($result['clients'][0]['name'])->toBe('Northstar')
        ->and(json_encode($result, JSON_THROW_ON_ERROR))->not->toContain('private@example.test');
});

test('project lookup returns only allowlisted fields and status counts', function () {
    $client = Client::query()->create([
        'name' => 'Northstar',
        'company' => 'Northstar Labs',
    ]);
    Project::query()->create([
        'client_id' => $client->getKey(),
        'name' => 'Solar Field',
        'description' => 'Private project description',
        'budget' => '987654.00',
        'status' => 'active',
        'start_date' => '2025-01-02',
        'end_date' => '2025-06-30',
    ]);

    $result = json_decode(
        (string) (new SearchProjects)->handle(new ToolRequest([
            'search' => 'NORTHSTAR',
            'status' => 'active',
        ])),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($result['counts_by_status']['active'])->toBe(1)
        ->and($result['total_matches'])->toBe(1)
        ->and(array_keys($result['projects'][0]))->toBe([
            'id',
            'name',
            'client',
            'client_company',
            'status',
            'start_date',
            'end_date',
        ])
        ->and($result['projects'][0]['client'])->toBe('Northstar')
        ->and(json_encode($result, JSON_THROW_ON_ERROR))
        ->not->toContain('Private project description')
        ->not->toContain('987654');
});

function createBusinessAssistantTestConversation(User $user, string $agent): string
{
    $conversationId = (string) Str::uuid();

    DB::table('agent_conversations')->insert([
        'id' => $conversationId,
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
        'title' => 'Existing conversation',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('agent_conversation_messages')->insert([
        'id' => (string) Str::uuid(),
        'conversation_id' => $conversationId,
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
        'agent' => $agent,
        'role' => 'assistant',
        'content' => 'Previous answer',
        'attachments' => '[]',
        'steps' => '[]',
        'usage' => '[]',
        'meta' => '[]',
        'status' => 'completed',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $conversationId;
}
