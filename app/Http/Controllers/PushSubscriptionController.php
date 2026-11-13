<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeletePushSubscriptionRequest;
use App\Http\Requests\StorePushSubscriptionRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use NotificationChannels\WebPush\PushSubscription;

class PushSubscriptionController extends Controller
{
    public function store(StorePushSubscriptionRequest $request): JsonResponse
    {
        if (blank(config('webpush.vapid.public_key'))
            || blank(config('webpush.vapid.private_key'))
            || blank(config('webpush.vapid.subject'))) {
            return response()->json([
                'message' => 'Las notificaciones push no están configuradas en el servidor.',
            ], 503);
        }

        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $validated = $request->validated();
        $endpoint = $validated['endpoint'];
        $subscriptionData = [
            'public_key' => $validated['public_key'],
            'auth_token' => $validated['auth_token'],
            'content_encoding' => $validated['content_encoding'],
        ];

        $stored = DB::connection(config('webpush.database_connection'))->transaction(
            function () use ($user, $endpoint, $subscriptionData): bool {
                $subscription = PushSubscription::query()
                    ->where('endpoint', $endpoint)
                    ->lockForUpdate()
                    ->first();

                if ($subscription !== null && ! $user->ownsPushSubscription($subscription)) {
                    return false;
                }

                if ($subscription === null) {
                    $user->pushSubscriptions()->create([
                        'endpoint' => $endpoint,
                        ...$subscriptionData,
                    ]);
                } else {
                    $subscription->update($subscriptionData);
                }

                return true;
            },
        );

        if (! $stored) {
            return response()->json([
                'message' => 'Esta suscripción ya pertenece a otro usuario.',
            ], 409);
        }

        return response()->json([
            'message' => 'Notificaciones push activadas.',
        ]);
    }

    public function destroy(DeletePushSubscriptionRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $user->deletePushSubscription($request->string('endpoint')->toString());

        return response()->json([
            'message' => 'Notificaciones push desactivadas.',
        ]);
    }
}
