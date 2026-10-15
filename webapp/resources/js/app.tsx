import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';

if (window.isSecureContext && 'serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js', { scope: '/', updateViaCache: 'none' }).catch(() => {});
}

createInertiaApp({
    title: title => `${title} · Buksza`,
    resolve: async name => (await import(`./pages/${name}.tsx`)).default,
    setup({ el, App, props }) { createRoot(el).render(<App {...props} />); },
    progress: { color: '#367b5d' },
});
