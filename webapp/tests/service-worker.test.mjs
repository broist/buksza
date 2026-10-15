import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import assert from 'node:assert/strict';
import { test } from 'node:test';

const source = readFileSync(new URL('../public/sw.js', import.meta.url), 'utf8');

function worker() {
    const handlers = new Map();
    const notifications = [];
    let activated = false;
    let claimed = false;
    runInNewContext(source, {
        self: {
            addEventListener: (name, callback) => handlers.set(name, callback),
            skipWaiting: async () => { activated = true; },
            clients: { claim: async () => { claimed = true; } },
            registration: { showNotification: async (title, options) => notifications.push({ title, ...options }) },
        },
    });
    async function dispatch(name, data) {
        let completion;
        handlers.get(name)({ data, waitUntil: promise => { completion = promise; } });
        await completion;
    }
    return { notifications, dispatch, activated: () => activated, claimed: () => claimed };
}

test('push displays the supplied reminder title and destination', async () => {
    const instance = worker();
    for (const title of ['Esti pénzügyi emlékeztető', 'Anna hónap végi egyenlege', 'Esti és hónap végi emlékeztető', 'Próbaértesítés']) {
        await instance.dispatch('push', { json: () => ({ title, body: 'Mai teendő', url: '/esti-rogzites?date=2026-10-31' }) });
        const notification = instance.notifications.at(-1);
        assert.equal(notification.title, title);
        assert.equal(notification.body, 'Mai teendő');
        assert.equal(notification.data.url, '/esti-rogzites?date=2026-10-31');
    }
});

test('missing or malformed push titles have a meaningful fallback', async () => {
    const instance = worker();
    for (const title of [undefined, null, 42, '', '   ']) {
        await instance.dispatch('push', { json: () => ({ title, url: 'https://example.com/' }) });
        assert.equal(instance.notifications.at(-1).title, 'Pénzügyi emlékeztető');
        assert.equal(instance.notifications.at(-1).data.url, '/');
    }
    await instance.dispatch('push', { json: () => { throw new Error('Invalid JSON'); } });
    assert.equal(instance.notifications.at(-1).title, 'Pénzügyi emlékeztető');
});

test('notification-only worker updates activate without reopening every app window', async () => {
    const instance = worker();
    await instance.dispatch('install');
    await instance.dispatch('activate');
    assert.equal(instance.activated(), true);
    assert.equal(instance.claimed(), true);
});
