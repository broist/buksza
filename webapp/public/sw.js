// Financial pages and requests always use the network. No offline records or data cache.
// This worker only handles notifications, so updates can take effect immediately.
self.addEventListener('install', event => event.waitUntil(self.skipWaiting()));
self.addEventListener('activate', event => event.waitUntil(self.clients.claim()));
self.addEventListener('push', event => {
    let payload = {};
    try { payload = event.data?.json() || {}; } catch {}
    const url = typeof payload.url === 'string' && /^\/(esti-rogzites|szamlak)(\?|$)/.test(payload.url) ? payload.url : '/';
    const title = typeof payload.title === 'string' && payload.title.trim() ? payload.title.trim().slice(0, 100) : 'Pénzügyi emlékeztető';
    event.waitUntil(self.registration.showNotification(title, {
        body: typeof payload.body === 'string' ? payload.body.slice(0, 200) : 'Nyisd meg a Bukszát a napi rögzítéshez.',
        icon: '/icons/buksza-192.png', badge: '/icons/buksza-192.png',
        tag: typeof payload.tag === 'string' ? payload.tag.slice(0, 120) : 'buksza', data: { url },
    }));
});
self.addEventListener('notificationclick', event => {
    event.notification.close();
    const target = new URL(event.notification.data?.url || '/', self.location.origin);
    if (target.origin !== self.location.origin) return;
    event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(async clients => {
        // Reuse an idle app window, without overwriting a form the user is typing in.
        for (const client of clients) if (client.url === target.href) return client.focus();
        return self.clients.openWindow(target.href);
    }));
});
