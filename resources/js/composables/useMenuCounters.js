import { ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

/**
 * Реактивные счётчики/индикаторы бокового меню из shared-пропсов Inertia
 * (см. HandleInertiaRequests::menu). Обновляются после каждой успешной навигации.
 *
 * @returns {{ menu: import('vue').Ref<Record<string, any>> }}
 */
export function useMenuCounters() {
    const menu = ref(usePage().props.menu ?? {});

    router.on('success', () => {
        menu.value = usePage().props.menu ?? {};
    });

    return { menu };
}
