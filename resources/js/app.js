import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { Ziggy } from './ziggy-routes.js';
import { createPinia } from 'pinia'

const pinia = createPinia()

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

const resolveZiggyConfig = () => {
    const config = { ...Ziggy };

    if (typeof window !== 'undefined') {
        config.url = window.location.origin;
        config.port = window.location.port ? Number(window.location.port) : null;

        if (window.Ziggy) {
            Object.assign(config.routes, window.Ziggy.routes);
        }
    }

    return config;
};

router.on('invalid', (event) => {
    if (event.detail.response?.status === 419) {
        event.preventDefault();
        window.location.reload();
    }
});

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        const myApp =  createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(pinia)
            .use(ZiggyVue, resolveZiggyConfig());

        myApp.config.globalProperties.appName = appName;

        myApp.mount(el);

        return myApp;
    },
    progress: {
        color: '#4B5563',
    },
});
