<script setup>
import {ref} from "vue";
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
        class="fieldset border-base-300 bg-base-200/60 w-full min-w-0 rounded-lg border px-2 py-1.5"
        :class="{ 'pointer-events-none opacity-60': form.processing }"
    >
        <label
            class="label tooltip tooltip-right min-h-0 cursor-pointer justify-between gap-2.5 px-0 py-0"
            :data-tip="is_online ? 'Трафик включён — новые сделки доступны' : 'Трафик выключен — новые сделки не назначаются'"
        >
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
    </fieldset>
</template>
