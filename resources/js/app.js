import './bootstrap';
import { createApp, h } from 'vue';
import { createInertiaApp, Head, Link } from '@inertiajs/vue3';
import '../css/app.css';
import ActionProcessingOverlay from './Components/ActionProcessingOverlay.vue';
import ConfirmActionModal from './Components/ConfirmActionModal.vue';
import { initMutationGuard } from './Composables/useMutationGuard';

const pages = import.meta.glob('./Pages/**/*.vue');

initMutationGuard();

function dismissBootScreen() {
    const boot = document.getElementById('lugia-boot-screen');
    if (!boot) return;
    boot.style.opacity = '0';
    boot.style.pointerEvents = 'none';
    setTimeout(() => boot.remove(), 180);
}

createInertiaApp({
    title: (title) => (title ? `${title} — LugiaCloud` : 'LugiaCloud — Cloud VPS SSD & Proxy IPv4'),
    resolve: async (name) => {
        const loader = pages[`./Pages/${name}.vue`];
        if (!loader) {
            throw new Error(`Page not found: ${name}`);
        }
        const mod = await loader();
        return mod.default || mod;
    },
    setup({ el, App, props, plugin }) {
        createApp({
            render: () => [h(App, props), h(ActionProcessingOverlay), h(ConfirmActionModal)],
        })
            .use(plugin)
            .component('Head', Head)
            .component('Link', Link)
            .mount(el);

        requestAnimationFrame(dismissBootScreen);
    },
    progress: {
        color: '#1D6AE5',
        showSpinner: false,
        delay: 100,
    },
});
