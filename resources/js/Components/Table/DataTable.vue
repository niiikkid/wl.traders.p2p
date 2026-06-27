<script setup>
defineProps({
    /** Показать оверлей загрузки поверх таблицы. */
    loading: {
        type: Boolean,
        default: false,
    },
    /** Текст под спиннером оверлея загрузки. */
    loadingText: {
        type: String,
        default: 'Загрузка данных...',
    },
    /** Дополнительные классы для <table> (например, table-zebra). */
    tableClass: {
        type: [String, Array, Object],
        default: '',
    },
});
</script>

<template>
    <div class="hidden xl:block rounded-table relative">
        <slot name="before" />

        <div
            class="card sticky top-0 left-0 bg-base-100/50 z-10 flex items-center justify-center backdrop-blur-sm transition-all duration-300 ease-in-out opacity-0 pointer-events-none"
            :class="{ 'opacity-0 pointer-events-none': !loading, 'opacity-100': loading }"
            style="position: absolute; inset: 0; width: 100%; height: 100%;"
        >
            <div
                class="flex flex-col items-center transition-transform duration-300"
                :class="{ 'scale-90 opacity-0': !loading, 'scale-100 opacity-100': loading }"
            >
                <div
                    class="animate-spin inline-block w-8 h-8 border-[3px] border-current border-t-transparent text-primary rounded-full"
                    role="status"
                    aria-label="loading"
                >
                    <span class="sr-only">Загрузка...</span>
                </div>
                <div class="mt-2 text-sm font-medium text-base-content">{{ loadingText }}</div>
            </div>
        </div>

        <div class="overflow-x-auto card bg-base-100 shadow">
            <table class="table table-sm" :class="[tableClass, { 'pointer-events-none': loading }]">
                <thead class="text-xs uppercase bg-base-300">
                    <tr>
                        <slot name="head" />
                    </tr>
                </thead>
                <tbody>
                    <slot />
                </tbody>
            </table>
        </div>
    </div>
</template>
