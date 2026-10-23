import { createInertiaApp } from '@inertiajs/vue3';
import { layout, title, withApp } from '@/lib/inertia';

// `vite build --ssr` turns this call into the Inertia SSR server
// (bootstrap/ssr/ssr.js, port 13714): the Inertia Vite plugin wraps it.
// Only the options both sides share: the browser-only setup stays in app.ts.
void createInertiaApp({ title, layout, withApp });
