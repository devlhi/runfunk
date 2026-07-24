import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

// Aktifkan font Google yang dimuat sebagai media="print" (non-blocking) di
// app.blade.php. Pengalihan dilakukan di sini, bukan lewat atribut onload
// sebaris, karena CSP situs (script-src 'self') memblokir handler sebaris —
// tanpa ini font tetap "print" dan seluruh situs tampil dengan font cadangan.
// Dijalankan lebih dulu supaya font berganti secepat mungkin.
document.querySelectorAll('link[data-font][media="print"]').forEach((l) => {
    l.media = 'all';
});

const appName = import.meta.env.VITE_APP_NAME || 'Gong Funrun 2026';

createInertiaApp({
    title: (title) => (title ? `${title} — ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#FF4A1C',
        showSpinner: false,
    },
});
