<script setup>
import AppTooltip from '@/Components/AppTooltip.vue';
import { ref } from 'vue';
import {router, useForm, usePage} from "@inertiajs/vue3";

const is_online = ref(!!usePage().props.auth.user.is_online);

router.on('success', (event) => {
    is_online.value = !!usePage().props.auth.user.is_online;
})

const form = useForm({});
const submit = () => {
    form.patch(route('user.online.toggle'), {
        preserveScroll: true,
        onSuccess: (result) => {
            is_online.value = !!result.props.auth.user.is_online;
        },
    });
};
</script>

<template>
    <fieldset
        class="fieldset border-base-300 bg-base-200/60 w-full min-w-0 rounded-lg border px-3 py-2"
        :class="{ 'pointer-events-none opacity-60': form.processing }"
    >
        <AppTooltip
            :tip="is_online ? 'Трафик включён — новые сделки доступны' : 'Трафик выключен — новые сделки не назначаются'"
            placement="right"
            wrapper-class="block w-full"
        >
            <label class="label min-h-0 cursor-pointer justify-between gap-2.5 px-0 py-0">
                <span class="flex min-w-0 flex-1 items-center gap-2 text-left">
                    <span class="truncate text-xs font-semibold leading-tight text-base-content">Трафик</span>
                    <span
                        class="badge badge-sm badge-outline shrink-0 whitespace-nowrap font-medium normal-case"
                        :class="is_online ? 'badge-success' : 'badge-ghost'"
                    >
                        {{ is_online ? 'Онлайн' : 'Офлайн' }}
                    </span>
                </span>
                <input
                    type="checkbox"
                    v-model="is_online"
                    class="toggle toggle-success toggle-sm shrink-0"
                    :disabled="form.processing"
                    @change="submit"
                />
            </label>
        </AppTooltip>
    </fieldset>
</template>
