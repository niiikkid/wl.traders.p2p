<script setup>
import {useViewStore} from "@/store/view.js";
import {router} from "@inertiajs/vue3";

const viewStore = useViewStore();


const visitDefaultPage = () => {
    if (viewStore.viewMode === 'admin') {
        router.visit(route('admin.main.index'), {
            preserveScroll: true
        })
    }
    if (viewStore.viewMode === 'trader') {
        router.visit(route('trader.main.index'), {
            preserveScroll: true
        })
    }
    if (viewStore.viewMode === 'merchant') {
        router.visit(route('merchant.main.index'), {
            preserveScroll: true
        })
    }
    if (viewStore.viewMode === 'leader') {
        router.visit(route('leader.main.index'), {
            preserveScroll: true
        })
    }
    if (viewStore.viewMode === 'support') {
        router.visit(route('support.users.index'), {
            preserveScroll: true
        })
    }
}

const selectViewMode = (mode) => {
    viewStore.viewMode = mode;
    visitDefaultPage();
}

const getCurrentViewModeLabel = () => {
    const labels = {
        'admin': 'Админ',
        'trader': 'Трейдер',
        'merchant': 'Мерчант',
        'leader': 'Тимлидер',
        'support': 'Саппорт',
    };
    return labels[viewStore.viewMode] || 'Выберите режим';
}
</script>

<template>
    <div class="dropdown dropdown-end">
        <button
            tabindex="0"
            type="button"
            class="btn btn-ghost normal-case h-auto min-h-0 px-3 py-2 rounded-xl border border-base-300/70 hover:border-primary/60 hover:bg-primary/5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary/40 btn-sm"
        >
            {{ getCurrentViewModeLabel() }}
        </button>

        <ul tabindex="0" class="dropdown-content menu bg-base-100 border border-base-300 rounded-box z-[60] w-52 p-2 shadow-sm">
                <li class="active-item">
                    <a @click="selectViewMode('admin')">
                        Админ
                    </a>
                </li>
                <li>
                    <a @click="selectViewMode('trader')">
                        Трейдер
                    </a>
                </li>
                <li>
                    <a @click="selectViewMode('merchant')">
                        Мерчант
                    </a>
                </li>
                <li>
                    <a @click="selectViewMode('leader')">
                        Тимлидер
                    </a>
                </li>
                <li>
                    <a @click="selectViewMode('support')">
                        Саппорт
                    </a>
                </li>
        </ul>
    </div>
</template>

<style scoped>

</style>
