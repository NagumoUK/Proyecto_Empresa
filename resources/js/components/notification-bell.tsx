import { router, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { useEffect } from 'react';
import {
    markAllAsRead as markAllAsReadAction,
    markAsRead as markAsReadAction,
} from '@/actions/App/Http/Controllers/NotificationController';
import { Button } from '@/components/ui/button';
import { PushNotificationToggle } from '@/components/push-notification-toggle';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

function formatCreatedAt(createdAt: string | null): string {
    if (createdAt === null) {
        return 'Reciente';
    }

    return new Intl.DateTimeFormat('es', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(createdAt));
}

export function NotificationBell() {
    const { auth, notifications, push } = usePage().props;
    const userId = auth.user?.id;

    useEffect(() => {
        if (!userId) {
            return;
        }

        const echo = window.Echo;
        const channelName = `App.Models.User.${userId}`;
        const channel = echo.private(channelName);
        const reloadNotifications = () => {
            router.reload({ only: ['notifications'] });
        };

        channel.notification(reloadNotifications);

        return () => {
            channel.stopListening(
                '.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated',
                reloadNotifications,
            );
            echo.leaveChannel(`private:${channelName}`);
        };
    }, [userId]);

    function openNotification(id: string, url: string, readAt: string | null) {
        if (readAt !== null) {
            router.visit(url);

            return;
        }

        router.patch(
            markAsReadAction.url(id),
            {},
            {
                preserveScroll: true,
                onSuccess: () => router.visit(url),
            },
        );
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="relative"
                    aria-label="Notificaciones"
                >
                    <Bell className="size-5" />
                    {notifications.unreadCount > 0 && (
                        <span className="absolute -top-1 -right-1 flex min-h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-semibold text-white">
                            {notifications.unreadCount > 99
                                ? '99+'
                                : notifications.unreadCount}
                        </span>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                className="w-80 max-w-[calc(100vw-2rem)] p-0"
            >
                <div className="flex items-center justify-between px-3 py-2">
                    <DropdownMenuLabel className="p-0">
                        Notificaciones
                    </DropdownMenuLabel>
                    <button
                        type="button"
                        disabled={notifications.unreadCount === 0}
                        onClick={() =>
                            router.patch(
                                markAllAsReadAction.url(),
                                {},
                                {
                                    preserveScroll: true,
                                },
                            )
                        }
                        className="text-xs font-medium text-sky-700 hover:underline disabled:cursor-not-allowed disabled:opacity-50 dark:text-sky-300"
                    >
                        Marcar todas como leídas
                    </button>
                </div>
                <DropdownMenuSeparator />
                {notifications.items.length === 0 ? (
                    <p className="px-3 py-6 text-center text-sm text-muted-foreground">
                        No tienes notificaciones.
                    </p>
                ) : (
                    <div className="max-h-96 overflow-y-auto p-1">
                        {notifications.items.map((notification) => (
                            <DropdownMenuItem
                                key={notification.id}
                                onSelect={() =>
                                    openNotification(
                                        notification.id,
                                        notification.url,
                                        notification.readAt,
                                    )
                                }
                                className="items-start gap-3 px-3 py-3 whitespace-normal"
                            >
                                <span
                                    className={`mt-1.5 size-2 shrink-0 rounded-full ${
                                        notification.readAt === null
                                            ? 'bg-sky-600'
                                            : 'bg-transparent'
                                    }`}
                                />
                                <span className="min-w-0 flex-1">
                                    <span className="block font-medium">
                                        {notification.title}
                                    </span>
                                    <span className="mt-1 block text-xs text-muted-foreground">
                                        {notification.message}
                                    </span>
                                    <span className="mt-1 block text-[11px] text-muted-foreground">
                                        {formatCreatedAt(
                                            notification.createdAt,
                                        )}
                                    </span>
                                </span>
                            </DropdownMenuItem>
                        ))}
                    </div>
                )}
                <PushNotificationToggle publicKey={push.publicKey} />
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
