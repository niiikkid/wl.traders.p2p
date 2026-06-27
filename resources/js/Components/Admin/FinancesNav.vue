<script setup>
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const props = defineProps({
    current: {
        type: String,
        required: true,
        validator: (value) => ['deposits', 'withdrawals'].includes(value),
    },
});

const pendingWithdrawals = computed(() => usePage().props.menu?.pendingWithdrawals ?? 0);

const items = [
    {
        key: 'deposits',
        label: 'Депозиты',
    },
    {
        key: 'withdrawals',
        label: 'Заявки на вывод',
    },
];

const visit = (tabKey) => {
    if (props.current === tabKey) {
        return;
    }

    router.visit(route('admin.finances.index', { tab: tabKey }), {
        preserveScroll: true,
        preserveState: false,
    });
};
</script>

<template>
    <nav aria-label="Разделы финансов" class="max-w-full overflow-x-auto">
        <div
            role="tablist"
            class="tabs tabs-box bg-base-200/60 border border-base-300/70 rounded-xl p-1 gap-0.5 inline-flex w-auto max-w-full"
        >
            <button
                v-for="item in items"
                :key="item.key"
                type="button"
                role="tab"
                class="tab gap-2 whitespace-nowrap px-3 sm:px-4"
                :class="{ 'tab-active font-semibold': current === item.key }"
                :aria-selected="current === item.key"
                :aria-current="current === item.key ? 'page' : undefined"
                @click="visit(item.key)"
            >
                <svg
                    v-if="item.key === 'deposits'"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="1.5"
                    stroke="currentColor"
                    class="size-4 shrink-0 opacity-80"
                    aria-hidden="true"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                <svg
                    v-else
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="1.5"
                    stroke="currentColor"
                    class="size-4 shrink-0 opacity-80"
                    aria-hidden="true"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                </svg>
                <span>{{ item.label }}</span>
                <span
                    v-if="item.key === 'withdrawals' && pendingWithdrawals > 0"
                    class="badge badge-warning badge-xs"
                >
                    {{ pendingWithdrawals }}
                </span>
            </button>
        </div>
    </nav>
</template>
