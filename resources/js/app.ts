import '../css/app.css';

import { createApp, h, type DefineComponent } from 'vue';
import { createInertiaApp, Head, Link } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { vCan } from '@/directives/v-can';
import i18n from '@/i18n';

// >>> MYRA v2.6 [C] START
// The brand is server-resolved and embedded by app.blade.php, so the document
// title and the Inertia progress bar are branded before the first render.
import { readBrandMeta } from '@/composables/useBrand';

const brand = readBrandMeta();
const appName = brand?.name || import.meta.env.VITE_APP_NAME || 'Laravel';
const progressColor = brand?.palette?.primary || '#4B5563';
// <<< MYRA v2.6 [C] END

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });
        app.use(plugin);
        app.use(i18n);
        app.use(ZiggyVue);
        app.component('Head', Head);
        app.component('Link', Link);
        app.directive('can', vCan);
        app.mount(el);
    },
    progress: {
        color: progressColor,
    },
});
