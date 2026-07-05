<script setup>
import InputError from '@/Components/InputError.vue';
import InputHelper from '@/Components/InputHelper.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AlertError from '@/Components/Alerts/AlertError.vue';
import AlertInfo from '@/Components/Alerts/AlertInfo.vue';
import Select from '@/Components/Select.vue';
import {computed} from 'vue';
import {useForm, usePage} from '@inertiajs/vue3';

const page = usePage();
const openAiSetting = computed(() => page.props.openAiSetting);

const form = useForm({
    api_key: '',
    selected_model: openAiSetting.value.selected_model ?? '',
});

const modelOptions = computed(() => {
    const models = [...(page.props.openAiSetting.available_models ?? [])];
    const selected = form.selected_model || page.props.openAiSetting.selected_model;

    if (selected && !models.includes(selected)) {
        models.unshift(selected);
    }

    return models.map((model) => ({
        value: model,
        name: model,
    }));
});

const syncFormFromPage = () => {
    form.defaults({
        api_key: '',
        selected_model: page.props.openAiSetting.selected_model ?? '',
    });
    form.reset();
};

const save = () => {
    form.patch(route('admin.open-ai.update'), {
        preserveScroll: true,
        onSuccess: syncFormFromPage,
    });
};

const refreshModels = () => {
    form.post(route('admin.open-ai.models.refresh'), {
        preserveScroll: true,
        onSuccess: syncFormFromPage,
    });
};
</script>

<template>
    <section>
        <header>
            <h3 class="text-sm font-semibold leading-snug text-base-content">OpenAI</h3>
            <p class="mt-0.5 text-xs text-base-content/60">
                Интеграция для проверки SMS через prompt-запросы.
            </p>
        </header>

        <div class="mt-2 space-y-2">
            <AlertError :message="$page.props.flash.error" />
            <AlertInfo :message="$page.props.flash.message" />
        </div>

        <form class="mt-3 w-full min-w-0 space-y-3" @submit.prevent="save">
            <div class="w-full min-w-0">
                <InputLabel
                    for="openai_api_key"
                    value="API ключ"
                    :error="!!form.errors.api_key"
                />

                <input
                    id="openai_api_key"
                    v-model="form.api_key"
                    type="password"
                    class="input input-bordered input-sm mt-1 block w-full"
                    :class="{ 'input-error': !!form.errors.api_key }"
                    autocomplete="off"
                    placeholder="sk-..."
                    @input="form.clearErrors('api_key')"
                >

                <InputError class="mt-1 text-xs" :message="form.errors.api_key" />
                <InputHelper
                    v-if="!form.errors.api_key"
                    class="!mt-1 !text-xs"
                    :model-value="openAiSetting.has_api_key
                        ? 'Ключ сохранён. Заполните поле только для замены.'
                        : 'Сохраните ключ, чтобы загрузить доступные модели.'"
                />
            </div>

            <div class="w-full min-w-0">
                <InputLabel
                    for="openai_selected_model"
                    value="Модель"
                    :error="!!form.errors.selected_model"
                />

                <div class="mt-1 min-w-0">
                    <Select
                        id="openai_selected_model"
                        v-model="form.selected_model"
                        :items="modelOptions"
                        value="value"
                        name="name"
                        default_title="Не выбрана"
                        default_value=""
                        :required="false"
                        size="sm"
                        :error="!!form.errors.selected_model"
                        :disabled="form.processing"
                        @change="form.clearErrors('selected_model')"
                    />
                </div>

                <InputError class="mt-1 text-xs" :message="form.errors.selected_model" />
                <InputHelper
                    v-if="!form.errors.selected_model"
                    class="!mt-1 !text-xs"
                    :model-value="openAiSetting.models_loaded_at
                        ? `Список обновлён: ${openAiSetting.models_loaded_at}`
                        : 'Список моделей ещё не загружался.'"
                />
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <PrimaryButton type="submit" class="btn-sm" :disabled="form.processing">Сохранить</PrimaryButton>
                <button
                    type="button"
                    class="btn btn-outline btn-sm"
                    :disabled="form.processing"
                    @click="refreshModels"
                >
                    Обновить модели
                </button>

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
