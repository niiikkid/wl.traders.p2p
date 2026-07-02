<script setup>
import { computed } from 'vue';
import { useDropdown } from '@/composables/useDropdown.js';

const props = defineProps({
    /** Object returned by `useDashboardStats`. */
    controller: {
        type: Object,
        required: true,
    },
    showBulkActions: {
        type: Boolean,
        default: false,
    },
});

const { isOpen, el, toggle, close } = useDropdown();

const filterTypes = computed(() => props.controller.gearFilterTypes.value);
const activeType = computed(() => props.controller.activeFilterType.value);
const selectedFilters = computed(() => props.controller.selectedFilters.value);
const loadingOptions = computed(() => props.controller.loadingOptions.value);
const processing = computed(() => props.controller.processing.value);
const hasActiveFilters = computed(() => props.controller.hasActiveAdvancedFilters.value);

const activePlaceholder = computed(() => (
    filterTypes.value.find((item) => item.key === activeType.value)?.placeholder || ''
));

const searchValue = computed({
    get: () => props.controller.searchQueries.value[activeType.value] || '',
    set: (value) => {
        props.controller.searchQueries.value[activeType.value] = value;
    },
});

const onToggle = () => {
    toggle();
    if (isOpen.value && activeType.value) {
        props.controller.loadFilterOptions(activeType.value, searchValue.value);
    }
};

const applyAdvanced = () => {
    close();
    props.controller.applyFilter();
};

const resetAdvanced = () => {
    close();
    props.controller.resetAdvancedFilters();
};
</script>

<template>
    <div ref="el" class="dropdown" :class="{ 'dropdown-open': isOpen }">
        <button
            type="button"
            class="btn btn-sm btn-square relative"
            :class="hasActiveFilters ? 'btn-primary border-transparent' : 'bg-base-100 border-transparent text-base-content hover:bg-primary hover:border-primary hover:text-primary-content'"
            title="Фильтры"
            @click.stop="onToggle"
        >
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
            </svg>
            <span
                v-if="hasActiveFilters"
                class="absolute -top-1 -right-1 h-2.5 w-2.5 rounded-full border border-base-100 bg-success"
            ></span>
        </button>

        <div class="dropdown-content z-30 mt-2 w-[20rem] md:w-[24rem] max-w-[calc(100vw-1rem)] rounded-box border border-base-300 bg-base-100 p-3 shadow">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-[8.5rem_1fr]">
                <div class="space-y-1 rounded-md border border-base-300 p-2">
                    <button
                        v-for="filterType in filterTypes"
                        :key="filterType.key"
                        type="button"
                        class="btn btn-xs w-full justify-between"
                        :class="{ 'btn-active btn-primary': activeType === filterType.key }"
                        @click="controller.selectFilterType(filterType.key)"
                    >
                        {{ filterType.label }}
                        <span
                            v-if="(selectedFilters[filterType.key] || []).length"
                            class="badge badge-secondary badge-xs ml-1 shrink-0"
                        >
                            {{ (selectedFilters[filterType.key] || []).length }}
                        </span>
                    </button>
                </div>

                <div class="space-y-3">
                    <input
                        v-model="searchValue"
                        type="text"
                        class="input input-bordered input-sm w-full"
                        :placeholder="activePlaceholder"
                    >

                    <div class="max-h-64 space-y-1 overflow-y-auto rounded-md border border-base-300 p-2">
                        <div v-if="loadingOptions[activeType]" class="py-2 text-sm text-base-content/60">
                            Загрузка...
                        </div>
                        <div
                            v-for="option in controller.getDisplayedOptions(activeType)"
                            :key="`${activeType}-${option.value}`"
                            class="w-full"
                        >
                            <label
                                class="flex w-full cursor-pointer items-start gap-3 rounded-md px-2 py-1.5"
                                :class="activeType === 'payment_detail' && option.is_archived ? 'bg-warning/10 border border-warning/25' : ''"
                            >
                                <input
                                    type="checkbox"
                                    class="checkbox checkbox-sm mt-0.5 shrink-0"
                                    :checked="controller.isOptionSelected(activeType, option.value)"
                                    @change="controller.toggleFilterOption(activeType, option, $event.target.checked)"
                                >
                                <span class="flex min-w-0 flex-col gap-0.5">
                                    <span class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                        <span
                                            class="text-sm leading-4 break-words"
                                            :class="activeType === 'payment_detail' && option.is_archived ? 'text-warning' : 'text-base-content'"
                                        >
                                            {{ option.label }}
                                        </span>
                                        <span
                                            v-if="activeType === 'payment_detail' && option.is_archived"
                                            class="badge badge-warning badge-xs font-medium"
                                        >
                                            Арх
                                        </span>
                                    </span>
                                    <span
                                        v-if="option.subtitle"
                                        class="text-xs leading-4 break-words"
                                        :class="activeType === 'payment_detail' && option.is_archived ? 'text-warning/80' : 'text-base-content/50'"
                                    >
                                        {{ option.subtitle }}
                                    </span>
                                </span>
                            </label>
                        </div>
                        <div
                            v-if="!loadingOptions[activeType] && controller.getDisplayedOptions(activeType).length === 0"
                            class="py-2 text-sm text-base-content/60"
                        >
                            Ничего не найдено
                        </div>
                    </div>
                </div>
            </div>

            <div
                v-if="showBulkActions && !loadingOptions[activeType]"
                class="mt-3 flex flex-wrap justify-end gap-1"
            >
                <button type="button" class="btn btn-xs btn-ghost h-7 min-h-0 px-2" @click="controller.bulkSelectFilterOptions('all')">
                    Все
                </button>
                <button type="button" class="btn btn-xs btn-ghost h-7 min-h-0 px-2" @click="controller.bulkSelectFilterOptions('none')">
                    Снять все
                </button>
                <button
                    v-if="activeType === 'payment_detail'"
                    type="button"
                    class="btn btn-xs btn-ghost h-7 min-h-0 px-2"
                    @click="controller.bulkSelectFilterOptions('active_only')"
                >
                    Без архива
                </button>
            </div>

            <div class="mt-3 flex justify-end gap-2 border-t border-base-300 pt-3">
                <button type="button" class="btn btn-outline btn-sm" :disabled="processing" @click="resetAdvanced">
                    Сбросить
                </button>
                <button type="button" class="btn btn-ghost btn-sm" @click.prevent.stop="close">
                    Закрыть
                </button>
                <button type="button" class="btn btn-primary btn-sm" :disabled="processing" @click="applyAdvanced">
                    Применить
                </button>
            </div>
        </div>
    </div>
</template>
