<?php

namespace App\Http\Middleware;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Middleware;
use Laravel\Pennant\Feature;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user()?->append('avatar'),
            ],
            'features' => fn (): array => [
                'documentManagement' => Feature::globally()->active('document-management'),
                'projectAttachments' => Feature::globally()->active('project-attachments'),
            ],
            'notifications' => fn (): array => $this->notificationsFor($request),
            'push' => fn (): array => [
                'publicKey' => filled(config('webpush.vapid.public_key'))
                    ? config('webpush.vapid.public_key')
                    : null,
            ],
            'locale' => app()->getLocale(),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * @return array{unreadCount: int, items: list<array{id: string, title: string, message: string, url: string, createdAt: string|null, readAt: string|null}>}
     */
    private function notificationsFor(Request $request): array
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return ['unreadCount' => 0, 'items' => []];
        }

        $items = array_values(
            $user->notifications()
                ->limit(8)
                ->get(['id', 'data', 'read_at', 'created_at'])
                ->map(static function (DatabaseNotification $notification): array {
                    /** @var array{title: string, message: string, url: string} $data */
                    $data = $notification->data;

                    return [
                        'id' => (string) $notification->getKey(),
                        'title' => $data['title'],
                        'message' => $data['message'],
                        'url' => $data['url'],
                        'createdAt' => $notification->created_at?->toIso8601String(),
                        'readAt' => $notification->read_at?->toIso8601String(),
                    ];
                })
                ->all(),
        );

        return [
            'unreadCount' => $user->unreadNotifications()->count(),
            'items' => $items,
        ];
    }
}
