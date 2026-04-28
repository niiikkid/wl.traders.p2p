<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import {useForm, usePage} from '@inertiajs/vue3';
import InputHelper from "@/Components/InputHelper.vue";
import TextInput from "@/Components/TextInput.vue";

const support_link = usePage().props.supportLink;

const form = useForm({
    support_link: support_link,
});

const submit = () => {
    form.patch(route('admin.settings.update.support-link'), {
        preserveScroll: true,
        onError: (result) => form.reset(),
    });
};
</script>

<template>
    <section>
        <header>
            <h3 class="text-sm font-semibold leading-snug text-base-content">Ссылка на техподдержку</h3>
        </header>

        <form @submit.prevent="submit" class="mt-3 w-full min-w-0 space-y-3">
            <div class="w-full min-w-0">
                <InputLabel
                    for="support_link"
                    value="Ссылка"
                    :error="!!form.errors.support_link"
                />

                <TextInput
                    id="support_link"
                    v-model="form.support_link"
                    class="input-sm mt-1 block w-full"
                    step="0.01"
                    placeholder="https://example.com"
                    :error="!!form.errors.support_link"
                    @input="form.clearErrors('support_link')"
                />

                <InputError class="mt-1 text-xs" :message="form.errors.support_link" />
                <InputHelper
                    v-if="! form.errors.support_link"
                    class="!mt-1 !text-xs"
                    model-value="Доступна клиенту на странице оплаты."
                />
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <PrimaryButton class="btn-sm" :disabled="form.processing">Сохранить</PrimaryButton>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p v-if="form.recentlySuccessful" class="text-xs opacity-70">Сохранено.</p>
                </Transition>
            </div>
        </form>
    </section>
</template>
