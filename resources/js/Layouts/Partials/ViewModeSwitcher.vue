<script setup>
import Select from "@/Components/Select.vue";

import {useViewStore} from "@/store/view.js";
import {watch} from "vue";
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
    if (viewStore.viewMode === 'merchant-support') {
        router.visit(route('merchant-support.payments.index'), {
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
        'merchant-support': 'Разработчик'
    };
    return labels[viewStore.viewMode] || 'Выберите режим';
}
</script>

<template>
    <div>
        <div class="dropdown dropdown-end w-full">
            <div tabindex="0" role="button" class="btn btn-outline btn-primary btn-sm w-full">
                {{ getCurrentViewModeLabel() }}
            </div>

            <ul tabindex="0" class="dropdown-content menu bg-base-100 border border-base-300 rounded-box z-1 w-52 p-2 shadow-sm">
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
                <li>
                    <a @click="selectViewMode('merchant-support')">
                        Разработчик
                    </a>
                </li>
            </ul>
        </div>
    </div>
</template>

<style scoped>

</style>
