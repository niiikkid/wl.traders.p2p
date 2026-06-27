<script setup>
import {computed, provide, ref, watch} from "vue";
import {router, usePage} from "@inertiajs/vue3";
import {useTableFiltersStore} from "@/store/tableFilters.js";
import {useActiveTableFiltersCount, useHasActiveTableFilters} from "@/composables/useHasActiveTableFilters.js";
import FunnelIcon from "@/Components/Filters/Icons/FunnelIcon.vue";
import ChevronDownIcon from "@/Components/Filters/Icons/ChevronDownIcon.vue";

const tableFiltersStore = useTableFiltersStore();

const props = defineProps({
    name: {
        type: String,
    },
    query: {
        type: Object,
        default: () => ({}),
    },
    /** Подпись на строке-триггере. */
    label: {
        type: String,
        default: 'Фильтры',
    },
});

const page = usePage();
const routeKey = computed(() => route().current() || page.url?.split('?')[0] || window.location.pathname || 'default');
const currentPageUrl = computed(() => page.url?.split('?')[0] || window.location.pathname);
const filtersStorageKey = computed(() => {
    const baseName = props.name ?? 'default';
    return `display-filters-${baseName}-${routeKey.value}`;
});
const displayFilters = ref(false);
// Во время анимации раскрытия держим overflow скрытым, после — открываем,
// чтобы выпадающие списки фильтров не обрезались краем карточки.
const isAnimating = ref(false);

const hasActiveFilters = useHasActiveTableFilters();
const activeFiltersCount = useActiveTableFiltersCount();

const contentOverflowClass = computed(() => (displayFilters.value && !isAnimating.value ? 'overflow-visible' : 'overflow-hidden'));

watch(displayFilters, () => {
    isAnimating.value = true;
});

const onCollapseTransitionEnd = (event) => {
    if (event.propertyName === 'grid-template-rows') {
        isAnimating.value = false;
    }
};

const syncDisplayFromStorage = (key) => {
    const saved = localStorage.getItem(key);
    if (saved === null) {
        localStorage.setItem(key, 'hide');
        displayFilters.value = false;
        return;
    }

    displayFilters.value = saved === 'display';
};

// Инициализация состояния для конкретной страницы
syncDisplayFromStorage(filtersStorageKey.value);

// При смене страницы/роута — работаем с новым ключом, не переиспользуя кэш
watch(filtersStorageKey, (newKey) => {
    syncDisplayFromStorage(newKey);
});

const toggleFiltersDisplay = () => {
    displayFilters.value = !displayFilters.value;
    localStorage.setItem(filtersStorageKey.value, displayFilters.value ? 'display' : 'hide');
}

const applyFilters = () => {
    tableFiltersStore.setCurrentPage(1);

    router.visit(currentPageUrl.value, {
        data: {
            ...tableFiltersStore.getQueryData,
            ...props.query
        },
        preserveScroll: true
    })
}

const clearFilters = () => {
    tableFiltersStore.setCurrentPage(1);
    tableFiltersStore.setFilters({});

    router.visit(currentPageUrl.value, {
        data: {
            ...tableFiltersStore.getQueryData,
            ...props.query
        },
        preserveScroll: true
    })
}

// Применение фильтров по Enter только из текстовых/числовых инпутов
const onKeydownEnter = (event) => {
    const target = event?.target;
    if (!target) {
        return;
    }
    const tagName = (target.tagName || '').toUpperCase();
    const type = (target.type || '').toLowerCase();
    const isTextLike =
        tagName === 'INPUT' && (type === 'text' || type === 'search' || type === 'number' || type === 'email');
    const isTextarea = tagName === 'TEXTAREA';

    if (isTextLike || isTextarea) {
        event.preventDefault();
        applyFilters();
    }
}

// Делаем доступным в дочерних инпутах, чтобы по Enter применять всегда
provide('applyFilters', applyFilters);

defineExpose({
    toggleFiltersDisplay,
    displayFilters,
    hasActiveFilters,
});
</script>

<template>
    <section class="space-y-2">
        <button
            type="button"
            class="group flex w-full items-center gap-3 rounded-box border border-base-300 bg-base-100 px-4 py-3 shadow-sm transition-colors hover:border-primary/50 focus:outline-none"
            :class="{ 'border-primary/40': displayFilters }"
            :aria-expanded="displayFilters ? 'true' : 'false'"
            @click.prevent="toggleFiltersDisplay"
        >
            <span
                class="flex size-8 flex-none items-center justify-center rounded-lg transition-colors"
                :class="hasActiveFilters ? 'bg-primary/15 text-primary' : 'bg-base-200 text-base-content/70'"
            >
                <FunnelIcon class="size-4"/>
            </span>
            <span class="font-medium text-base-content">{{ label }}</span>
            <span v-if="activeFiltersCount" class="badge badge-primary badge-sm">{{ activeFiltersCount }}</span>
            <span class="ml-auto flex items-center gap-2 text-sm text-base-content/60">
                <span class="hidden sm:inline">{{ displayFilters ? 'Скрыть' : 'Показать' }}</span>
                <ChevronDownIcon class="size-4 transition-transform duration-200" :class="{ 'rotate-180': displayFilters }"/>
            </span>
        </button>

        <div
            class="grid transition-[grid-template-rows] duration-300 ease-out"
            :class="displayFilters ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'"
            @transitionend="onCollapseTransitionEnd"
        >
            <div :class="contentOverflowClass">
                <div class="card border border-base-300 bg-base-100 shadow-sm">
                    <div class="p-3 lg:p-4" @keydown.enter.stop="onKeydownEnter">
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-5 items-end">
                            <slot/>
                        </div>
                        <div class="mt-4 flex flex-wrap items-center justify-end gap-2 border-t border-base-200 pt-3">
                            <button
                                @click.prevent="clearFilters"
                                type="button"
                                class="btn btn-ghost btn-sm gap-2"
                            >
                                <svg class="w-4 h-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 7h14m-9 3v8m4-8v8M10 3h4a1 1 0 0 1 1 1v3H9V4a1 1 0 0 1 1-1ZM6 7h12v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V7Z"/>
                                </svg>
                                <span>Сбросить</span>
                            </button>
                            <button
                                @click.prevent="applyFilters"
                                type="button"
                                class="btn btn-primary btn-sm gap-2"
                            >
                                <FunnelIcon class="w-4 h-4"/>
                                <span>Фильтровать</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
