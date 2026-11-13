import { useHttp } from '@inertiajs/react';
import { Bell, BellOff } from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    destroy as deletePushSubscription,
    store as storePushSubscription,
} from '@/actions/App/Http/Controllers/PushSubscriptionController';
import { Button } from '@/components/ui/button';

type PushSubscriptionData = {
    endpoint: string;
    public_key: string;
    auth_token: string;
    content_encoding: 'aes128gcm';
};

type PushResponse = {
    message: string;
};

type Props = {
    publicKey: string | null;
};

function decodeBase64Url(value: string): ArrayBuffer {
    const base64 = value
        .replace(/-/g, '+')
        .replace(/_/g, '/')
        .padEnd(Math.ceil(value.length / 4) * 4, '=');
    const binary = window.atob(base64);
    const buffer = new ArrayBuffer(binary.length);
    const bytes = new Uint8Array(buffer);

    for (let index = 0; index < binary.length; index += 1) {
        bytes[index] = binary.charCodeAt(index);
    }

    return buffer;
}

function messageFromResponse(data: unknown, fallback: string): string {
    if (
        typeof data === 'object' &&
        data !== null &&
        'message' in data &&
        typeof data.message === 'string'
    ) {
        return data.message;
    }

    return fallback;
}

export function PushNotificationToggle({ publicKey }: Props) {
    const saveRequest = useHttp<PushSubscriptionData, PushResponse>({
        endpoint: '',
        public_key: '',
        auth_token: '',
        content_encoding: 'aes128gcm',
    });
    const deleteRequest = useHttp<{ endpoint: string }, PushResponse>({
        endpoint: '',
    });
    const [supported, setSupported] = useState(false);
    const [subscribed, setSubscribed] = useState(false);
    const [message, setMessage] = useState<string | null>(null);
    const processing = saveRequest.processing || deleteRequest.processing;

    useEffect(() => {
        if (
            typeof window === 'undefined' ||
            !('serviceWorker' in navigator) ||
            !('PushManager' in window) ||
            !('Notification' in window)
        ) {
            return;
        }

        setSupported(true);
        void navigator.serviceWorker
            .getRegistration('/')
            .then((registration) => registration?.pushManager.getSubscription())
            .then((subscription) => {
                setSubscribed(
                    subscription !== null && subscription !== undefined,
                );
            })
            .catch(() => {
                setMessage(
                    'No se pudo comprobar el estado de las notificaciones.',
                );
            });
    }, []);

    async function enablePush(): Promise<void> {
        if (publicKey === null) {
            setMessage(
                'El servidor todavía no ha configurado las notificaciones push.',
            );

            return;
        }

        if (!supported) {
            setMessage(
                'Este navegador no admite notificaciones push o requiere HTTPS.',
            );

            return;
        }

        try {
            const permission = await Notification.requestPermission();

            if (permission !== 'granted') {
                setMessage('Permite las notificaciones para poder activarlas.');

                return;
            }

            const registration =
                await navigator.serviceWorker.register('/sw.js');
            let subscription = await registration.pushManager.getSubscription();

            if (subscription === null) {
                subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: decodeBase64Url(publicKey),
                });
            }

            const subscriptionData = subscription.toJSON();

            if (
                subscriptionData.endpoint === undefined ||
                subscriptionData.keys?.p256dh === undefined ||
                subscriptionData.keys.auth === undefined
            ) {
                throw new Error(
                    'El navegador no proporcionó los datos de suscripción necesarios.',
                );
            }

            const payload: PushSubscriptionData = {
                endpoint: subscriptionData.endpoint,
                public_key: subscriptionData.keys.p256dh,
                auth_token: subscriptionData.keys.auth,
                content_encoding: 'aes128gcm',
            };

            saveRequest.transform(() => payload);
            await saveRequest.post(storePushSubscription.url(), {
                onSuccess: (response) => {
                    setSubscribed(true);
                    setMessage(response.message);
                },
                onError: () => {
                    setMessage('No se pudo guardar la suscripción push.');
                },
                onHttpException: (response) => {
                    setMessage(
                        messageFromResponse(
                            response.data,
                            'El servidor no pudo guardar la suscripción push.',
                        ),
                    );
                },
                onNetworkError: () => {
                    setMessage('No fue posible conectar con el servidor.');
                },
            });
        } catch (error) {
            setMessage(
                error instanceof Error
                    ? error.message
                    : 'No se pudieron activar las notificaciones push.',
            );
        }
    }

    async function disablePush(): Promise<void> {
        try {
            const registration =
                await navigator.serviceWorker.getRegistration('/');
            const subscription =
                await registration?.pushManager.getSubscription();

            if (subscription === null || subscription === undefined) {
                setSubscribed(false);
                setMessage('No hay una suscripción activa en este navegador.');

                return;
            }

            deleteRequest.transform(() => ({
                endpoint: subscription.endpoint,
            }));
            await deleteRequest.delete(deletePushSubscription.url(), {
                onSuccess: async (response) => {
                    await subscription.unsubscribe();
                    setSubscribed(false);
                    setMessage(response.message);
                },
                onError: () => {
                    setMessage('No se pudo eliminar la suscripción push.');
                },
                onHttpException: (response) => {
                    setMessage(
                        messageFromResponse(
                            response.data,
                            'El servidor no pudo eliminar la suscripción push.',
                        ),
                    );
                },
                onNetworkError: () => {
                    setMessage('No fue posible conectar con el servidor.');
                },
            });
        } catch (error) {
            setMessage(
                error instanceof Error
                    ? error.message
                    : 'No se pudieron desactivar las notificaciones push.',
            );
        }
    }

    return (
        <div className="border-t px-3 py-3">
            <div className="flex items-center justify-between gap-3">
                <div className="min-w-0">
                    <p className="text-sm font-medium">
                        Notificaciones del dispositivo
                    </p>
                    <p className="text-xs text-muted-foreground">
                        {publicKey === null
                            ? 'No configuradas en el servidor'
                            : subscribed
                              ? 'Activas en este navegador'
                              : 'Recibe avisos de nuevas actividades'}
                    </p>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={!supported || publicKey === null || processing}
                    onClick={() =>
                        subscribed ? void disablePush() : void enablePush()
                    }
                    aria-label={
                        subscribed
                            ? 'Desactivar notificaciones push'
                            : 'Activar notificaciones push'
                    }
                >
                    {subscribed ? (
                        <BellOff aria-hidden="true" />
                    ) : (
                        <Bell aria-hidden="true" />
                    )}
                    {subscribed ? 'Desactivar' : 'Activar'}
                </Button>
            </div>
            {message !== null && (
                <p className="mt-2 text-xs text-muted-foreground" role="status">
                    {message}
                </p>
            )}
        </div>
    );
}
