self.addEventListener('push', (event) => {
    let data = {};
    try { data = event.data?.json() || {}; } catch (_) { return; }
    const url = typeof data.url === 'string' && /^\/eventos\/\d+$/.test(data.url) ? data.url : '/senal';
    event.waitUntil(self.registration.showNotification(String(data.title || 'UEBTV').slice(0, 120), {
        body: String(data.body || 'Hay novedades para ti.').slice(0, 220),
        icon: '/images/brand/uebtv-logo-final.png',
        badge: '/favicon.ico',
        tag: `uebtv-${url}`,
        data: { url },
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data?.url || '/senal';
    event.waitUntil(clients.matchAll({ type: 'window', includeUncontrolled: true }).then(async (windows) => {
        const target = new URL(url, self.location.origin).href;
        const opened = windows.find((windowClient) => windowClient.url === target);
        return opened ? opened.focus() : clients.openWindow(target);
    }));
});
