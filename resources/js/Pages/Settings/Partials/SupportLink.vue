<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import {router, useForm, usePage} from '@inertiajs/vue3';
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

//
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium">Настройка ссылки на техническую поддержку</h2>
        </header>

        <form @submit.prevent="submit" class="mt-6 space-y-6">
            <div class="max-w-[24rem]">
                <div>
                    <InputLabel
                        for="support_link"
                        value="Ссылка"
                        :error="!!form.errors.support_link"
                    />

                    <TextInput
                        id="support_link"
                        v-model="form.support_link"
                        class="mt-1 block w-full"
                        step="0.01"
                        placeholder="https://example.com"
                        :error="!!form.errors.support_link"
                        @input="form.clearErrors('support_link')"
                    />

                    <InputError class="mt-2" :message="form.errors.support_link" />
                    <InputHelper v-if="! form.errors.support_link" model-value="Эта ссылка будет доступна клиенту на странице оплаты."></InputHelper>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <PrimaryButton :disabled="form.processing">Сохранить</PrimaryButton>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p v-if="form.recentlySuccessful" class="text-sm opacity-70">Сохранено.</p>
                </Transition>
            </div>
        </form>
    </section>
</template>
