<?php

namespace App\Http\Controllers;

use App\Ai\Agents\BusinessDataAssistant;
use App\Http\Requests\AskBusinessAssistantRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Ai\Models\Conversation;

class BusinessAssistantController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('assistant');
    }

    public function ask(AskBusinessAssistantRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $validated = $request->validated();
        $conversationId = $validated['conversation_id'] ?? null;

        if ($conversationId !== null) {
            $ownsConversation = Conversation::query()
                ->whereKey($conversationId)
                ->where('participant_type', $user->getMorphClass())
                ->where('participant_id', $user->getKey())
                ->whereHas('messages', fn ($query) => $query->where('agent', BusinessDataAssistant::class))
                ->exists();

            if (! $ownsConversation) {
                return response()->json([
                    'message' => 'No se encontró la conversación solicitada.',
                ], 404);
            }
        }

        $response = (new BusinessDataAssistant)
            ->continueOrStart($conversationId, $user)
            ->prompt($validated['question']);

        return response()->json([
            'answer' => $response->text,
            'conversationId' => $response->conversationId,
        ]);
    }
}
