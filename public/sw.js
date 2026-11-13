self.addEventListener('push', (event) => {
    let payload = {};

    try {
        payload = event.data?.json() ?? {};
    } catch {
        payload = { body: event.data?.text() ?? '' };
    }

    event.waitUntil(
        self.registration.showNotification(
            payload.title ?? 'Nueva actividad del equipo',
            {
                body: payload.body ?? '',
                data: payload.data ?? {},
                tag: payload.tag ?? 'company-activity',
            },
        ),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const destination = new URL(
        event.notification.data?.url ?? '/',
        self.location.origin,
    );

    if (destination.origin !== self.location.origin) {
        return;
    }

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(
            (clients) => {
                const existingClient = clients.find(
                    (client) => client.url === destination.href,
                );

                if (existingClient) {
                    return existingClient.focus();
                }

                return self.clients.openWindow(destination.href);
            },
        ),
    );
});
