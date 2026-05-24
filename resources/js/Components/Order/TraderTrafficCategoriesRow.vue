<script setup>
import AppTooltip from '@/Components/AppTooltip.vue';
import AlertError from '@/Components/Alerts/AlertError.vue';
import { useTraderTrafficCategories } from '@/composables/useTraderTrafficCategories.js';
import { computed, onMounted, ref } from 'vue';

const TOOLTIP_DELAY_MS = 400;

const {
    categories,
    merchantTrafficCategoriesEnabled,
    loading,
    fetchCategories,
    setCategoryEnabled,
} = useTraderTrafficCategories();

const visible = ref(false);
const toggleError = ref('');
const togglingCategoryIds = ref(new Set());

const showRow = computed(() => visible.value && categories.value.length > 0);

const isToggling = (categoryId) => togglingCategoryIds.value.has(Number(categoryId));

const load = async () => {
    toggleError.value = '';

    const result = await fetchCategories();

    visible.value = Boolean(result.merchant_traffic_categories_enabled);
};

const toggleCategory = async (category) => {
    if (isToggling(category.id)) {
        return;
    }

    const nextEnabled = !category.enabled;
    const previousEnabled = category.enabled;
    const categoryId = Number(category.id);

    toggleError.value = '';
    category.enabled = nextEnabled;
    togglingCategoryIds.value = new Set([...togglingCategoryIds.value, categoryId]);

    try {
        await setCategoryEnabled(categoryId, nextEnabled);
    } catch (error) {
        category.enabled = previousEnabled;

        const message = error?.response?.data?.message
            || error?.response?.data?.errors?.enabled?.[0]
            || 'Не удалось сохранить настройку категории. Попробуйте ещё раз.';

        toggleError.value = message;
    } finally {
        const nextIds = new Set(togglingCategoryIds.value);
        nextIds.delete(categoryId);
        togglingCategoryIds.value = nextIds;
    }
};

onMounted(() => {
    load();
});
</script>

<template>
    <div v-if="loading && !showRow" class="rounded-xl border border-base-300 bg-base-200/40 px-4 py-3">
        <div class="flex items-center gap-2 text-sm text-base-content/70">
            <span class="loading loading-spinner loading-sm text-primary" role="status" aria-label="Загрузка категорий" />
            <span>Загрузка категорий трафика…</span>
        </div>
    </div>

    <div
        v-else-if="showRow"
        class="rounded-xl border border-base-300 bg-base-200/40 px-4 py-3"
    >
        <div class="flex flex-col gap-3">
            <div class="min-w-0">
                <h3 class="text-sm font-semibold text-base-content">
                    Категории трафика
                </h3>
                <p class="mt-1 text-xs leading-relaxed text-base-content/70">
                    Включите категории, с которыми хотите работать. Если выключить категорию, заявки от таких мерчантов не будут приходить.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <AppTooltip
                    v-for="category in categories"
                    :key="category.id"
                    :tip="category.description"
                    placement="top"
                    :show-delay-ms="TOOLTIP_DELAY_MS"
                    wrapper-class="inline-flex max-w-full"
                >
                    <button
                        type="button"
                        class="btn btn-sm h-auto min-h-8 max-w-full gap-1.5 rounded-lg px-3 py-1.5 font-medium normal-case"
                        :class="category.enabled
                            ? 'btn-primary border-primary'
                            : 'btn-ghost border border-base-300 bg-base-100/60 text-base-content/55'"
                        :disabled="isToggling(category.id)"
                        :aria-pressed="category.enabled"
                        @click="toggleCategory(category)"
                    >
                        <span
                            v-if="isToggling(category.id)"
                            class="loading loading-spinner loading-xs shrink-0"
                            role="status"
                            aria-hidden="true"
                        />
                        <span class="truncate border-b border-dotted border-current/40">
                            {{ category.name }}
                        </span>
                        <svg
                            class="size-3.5 shrink-0 opacity-70"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.5"
                            stroke="currentColor"
                            aria-hidden="true"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z"
                            />
                        </svg>
                    </button>
                </AppTooltip>
            </div>

            <AlertError v-if="toggleError" :message="toggleError" />
        </div>
    </div>
</template>
