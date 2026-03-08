<script setup>
import InputError from '@/Components/InputError.vue';
import InputHelper from '@/Components/InputHelper.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import {useForm, usePage} from '@inertiajs/vue3';

const appSlogan = usePage().props.appSlogan;

const form = useForm({
    app_slogan: appSlogan,
});

const submit = () => {
    form.patch(route('admin.settings.update.app-slogan'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium">Слоган платформы</h2>
        </header>

        <form @submit.prevent="submit" class="mt-6 space-y-6">
            <div class="max-w-[24rem]">
                <InputLabel
                    for="app_slogan"
                    value="Текст слогана"
                    :error="!!form.errors.app_slogan"
                />

                <TextInput
                    id="app_slogan"
                    v-model="form.app_slogan"
                    class="mt-1 block w-full"
                    placeholder="Например: с 8 марта"
                    :error="!!form.errors.app_slogan"
                    @input="form.clearErrors('app_slogan')"
                />

                <InputError class="mt-2" :message="form.errors.app_slogan" />
                <InputHelper
                    v-if="!form.errors.app_slogan"
                    model-value="Показывается в шапке и на гостевых страницах."
                />
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
