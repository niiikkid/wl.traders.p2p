import { onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';

export function useDevicesAutoRefresh(only = ['devices'], intervalMs = 30_000) {
    let timer = null;

    onMounted(() => {
        timer = setInterval(() => {
            router.reload({
                only,
                preserveScroll: true,
                preserveState: true,
            });
        }, intervalMs);
    });

    onUnmounted(() => {
        if (timer) {
            clearInterval(timer);
        }
    });
}
