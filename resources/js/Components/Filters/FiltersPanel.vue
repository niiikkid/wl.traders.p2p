<script setup>
import {computed, provide, ref, watch} from "vue";
import {router, usePage} from "@inertiajs/vue3";
import {useTableFiltersStore} from "@/store/tableFilters.js";

const tableFiltersStore = useTableFiltersStore();

const props = defineProps({
    name: {
        type: String,
    },
    query: {
        type: Object,
        default: {}
    }
});
const page = usePage();
const routeKey = computed(() => route().current() || page.url?.split('?')[0] || window.location.pathname || 'default');
const filtersStorageKey = computed(() => {
    const baseName = props.name ?? 'default';
    return `display-filters-${baseName}-${routeKey.value}`;
});
const displayFilters = ref(false);

const normalizeValue = (value) => {
    if (value === null || value === undefined) {
        return '';
    }
    if (Array.isArray(value)) {
        return value
            .map((item) => normalizeValue(item))
            .flat()
            .filter((item) => item !== '');
    }
    if (typeof value === 'object') {
        return Object.values(value)
            .map((item) => normalizeValue(item))
            .flat()
            .filter((item) => item !== '');
    }
    if (typeof value === 'boolean' || typeof value === 'number') {
        return value ? ['1'] : [];
    }
    if (typeof value === 'string') {
        return value.trim().length ? [value.trim()] : [];
    }
    return [];
};

const hasActiveFilters = computed(() => {
    const filters = tableFiltersStore.getFilters;
    if (!filters || typeof filters !== 'object') {
        return false;
    }

    return Object.values(filters).some((value) => normalizeValue(value).length > 0);
});

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

    router.visit(route(route().current()), {
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

    router.visit(route(route().current()), {
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
</script>

<template>
    <section>
        <div class="w-full flex justify-end mb-1 mr-1">
            <div v-if="!displayFilters" class="relative inline-flex">
                <button
                    @click.prevent="toggleFiltersDisplay"
                    type="button"
                    class="btn btn-sm btn-square btn-primary"
                    aria-pressed="false"
                    title="Показать фильтры"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z"/>
                    </svg>
                </button>
                <span
                    v-if="hasActiveFilters"
                    class="absolute -top-1 -right-1 h-3 w-3 rounded-full bg-error border border-base-100"
                    aria-label="Активные фильтры применены"
                    title="Есть применённые фильтры"
                />
            </div>
        </div>
        <Transition name="filters-collapse">
        <div
            v-show="displayFilters"
            class="mb-5"
        >
            <div class="mx-auto w-full">
                <div class="card bg-base-100 shadow">
                    <div
                        class="p-3 lg:p-4"
                        @keydown.enter.stop="onKeydownEnter"
                    >
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-5 items-center">
                            <slot/>
                            <div class="col-span-full flex flex-wrap items-center justify-end gap-2 pt-1">
                                <button
                                    @click.prevent="applyFilters"
                                    type="button"
                                    class="btn btn-primary btn-sm"
                                >
                                    <svg class="w-4 h-4 mr-2" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                        <path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M18.796 4H5.204a1 1 0 0 0-.753 1.659l5.302 6.058a1 1 0 0 1 .247.659v4.874a.5.5 0 0 0 .2.4l3 2.25a.5.5 0 0 0 .8-.4v-7.124a1 1 0 0 1 .247-.659l5.302-6.059c.566-.646.106-1.658-.753-1.658Z"/>
                                    </svg>
                                    <span>Фильтровать</span>
                                </button>
                                <button
                                    @click.prevent="clearFilters"
                                    type="button"
                                    class="btn btn-error btn-sm btn-outline"
                                >
                                    <svg class="w-4 h-4 mr-2" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 7h14m-9 3v8m4-8v8M10 3h4a1 1 0 0 1 1 1v3H9V4a1 1 0 0 1 1-1ZM6 7h12v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V7Z"/>
                                    </svg>
                                    <span>Сбросить</span>
                                </button>
                                <button
                                    v-if="displayFilters"
                                    @click.prevent="toggleFiltersDisplay"
                                    type="button"
                                    class="btn btn-sm btn-square btn-ghost text-base-content border-base-content/30"
                                    aria-pressed="true"
                                    title="Скрыть фильтры"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </Transition>
    </section>
</template>

<style scoped>

/* Плавное раскрытие/сворачивание панели фильтров */
.filters-collapse-enter-active,
.filters-collapse-leave-active {
    will-change: max-height, opacity, transform;
    overflow: hidden;
}
.filters-collapse-enter-active {
    transition: max-height 680ms cubic-bezier(0.22, 1, 0.36, 1),
                opacity 520ms ease-out 60ms,
                transform 680ms cubic-bezier(0.22, 1, 0.36, 1);
}
.filters-collapse-leave-active {
    transition: max-height 460ms cubic-bezier(0.4, 0, 0.2, 1),
                opacity 360ms ease-in,
                transform 460ms cubic-bezier(0.4, 0, 0.2, 1);
}
.filters-collapse-enter-from,
.filters-collapse-leave-to {
    max-height: 0;
    opacity: 0;
    transform: translateY(-6px);
}
.filters-collapse-enter-to,
.filters-collapse-leave-from {
    max-height: 2000px;
    opacity: 1;
    transform: translateY(0);
}
</style>
