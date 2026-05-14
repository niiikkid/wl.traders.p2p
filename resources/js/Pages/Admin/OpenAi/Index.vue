<script setup>
import {Head, useForm} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

defineOptions({ layout: AuthenticatedLayout });

const props = defineProps({
    setting: {
        type: Object,
        required: true,
    },
    test_form: {
        type: Object,
        default: () => ({
            model: '',
            system_prompt: '',
            user_prompt: '',
        }),
    },
    test_response: {
        type: String,
        default: '',
    },
    test_model_output: {
        type: String,
        default: '',
    },
});

const form = useForm({
    api_key: '',
    selected_model: props.setting.selected_model ?? '',
});

const save = () => {
    form.patch(route('admin.open-ai.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset('api_key'),
    });
};

const refreshModels = () => {
    form.post(route('admin.open-ai.models.refresh'), {
        preserveScroll: true,
        onSuccess: () => form.reset('api_key'),
    });
};

const promptForm = useForm({
    model: props.test_form.model ?? props.setting.selected_model ?? '',
    system_prompt: props.test_form.system_prompt ?? '',
    user_prompt: props.test_form.user_prompt ?? '',
});

const sendPrompt = () => {
    promptForm.post(route('admin.open-ai.prompt'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <div>
        <Head title="OpenAI" />

        <div class="space-y-6">
            <div>
                <h2 class="text-2xl font-bold text-base-content sm:text-3xl">OpenAI</h2>
                <p class="text-sm text-base-content/60">
                    Настройки интеграции для будущей проверки SMS через prompt-запросы.
                </p>
            </div>

            <div class="card bg-base-100 shadow">
                <div class="card-body space-y-6">
                    <div class="grid gap-4 lg:grid-cols-2">
                        <fieldset class="fieldset">
                            <legend class="fieldset-legend">API ключ</legend>
                            <input
                                v-model="form.api_key"
                                type="password"
                                class="input input-bordered w-full"
                                autocomplete="off"
                                placeholder="sk-..."
                            >
                            <p class="label">
                                <span v-if="setting.has_api_key">Ключ уже сохранен. Заполните поле только если нужно заменить его.</span>
                                <span v-else>Сохраните ключ, чтобы загрузить доступные модели.</span>
                            </p>
                            <p v-if="form.errors.api_key" class="text-sm text-error">{{ form.errors.api_key }}</p>
                        </fieldset>

                        <fieldset class="fieldset">
                            <legend class="fieldset-legend">Модель</legend>
                            <select v-model="form.selected_model" class="select select-bordered w-full">
                                <option value="">Не выбрана</option>
                                <option
                                    v-for="model in setting.available_models"
                                    :key="model"
                                    :value="model"
                                >
                                    {{ model }}
                                </option>
                            </select>
                            <p class="label">
                                <span v-if="setting.models_loaded_at">Список обновлен: {{ setting.models_loaded_at }}</span>
                                <span v-else>Список моделей еще не загружался.</span>
                            </p>
                            <p v-if="form.errors.selected_model" class="text-sm text-error">{{ form.errors.selected_model }}</p>
                        </fieldset>
                    </div>

                    <div class="rounded-box border border-base-300 bg-base-200/50 p-4 text-sm text-base-content/70">
                        Сервис использует выбранную модель для одиночных запросов: системная инструкция, пользовательский prompt и текстовый ответ.
                        Чат-интерфейс здесь не реализуется.
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="btn btn-primary"
                            :disabled="form.processing"
                            @click="save"
                        >
                            Сохранить настройки
                        </button>
                        <button
                            type="button"
                            class="btn btn-outline"
                            :disabled="form.processing"
                            @click="refreshModels"
                        >
                            Обновить список моделей
                        </button>
                    </div>
                </div>
            </div>

            <div class="card bg-base-100 shadow">
                <div class="card-body">
                    <h3 class="text-lg font-semibold">Тестовый запрос</h3>
                    <p class="text-sm text-base-content/60 mb-4">
                        Заполните оба промпта, отправьте запрос и посмотрите сырой ответ OpenAI.
                    </p>

                    <div class="grid gap-4 lg:grid-cols-2">
                        <div class="space-y-4">
                            <fieldset class="fieldset">
                                <legend class="fieldset-legend">Модель</legend>
                                <select v-model="promptForm.model" class="select select-bordered w-full">
                                    <option value="">Выберите модель</option>
                                    <option
                                        v-for="model in setting.available_models"
                                        :key="`prompt-${model}`"
                                        :value="model"
                                    >
                                        {{ model }}
                                    </option>
                                </select>
                                <p v-if="promptForm.errors.model" class="text-sm text-error">{{ promptForm.errors.model }}</p>
                            </fieldset>

                            <fieldset class="fieldset">
                                <legend class="fieldset-legend">System prompt</legend>
                                <textarea
                                    v-model="promptForm.system_prompt"
                                    class="textarea textarea-bordered min-h-28 w-full"
                                    placeholder="System instruction..."
                                />
                                <p v-if="promptForm.errors.system_prompt" class="text-sm text-error">{{ promptForm.errors.system_prompt }}</p>
                            </fieldset>

                            <fieldset class="fieldset">
                                <legend class="fieldset-legend">User prompt</legend>
                                <textarea
                                    v-model="promptForm.user_prompt"
                                    class="textarea textarea-bordered min-h-36 w-full"
                                    placeholder="Ваш текст запроса..."
                                />
                                <p v-if="promptForm.errors.user_prompt" class="text-sm text-error">{{ promptForm.errors.user_prompt }}</p>
                            </fieldset>

                            <button
                                type="button"
                                class="btn btn-primary"
                                :disabled="promptForm.processing"
                                @click="sendPrompt"
                            >
                                Отправить запрос
                            </button>
                        </div>

                        <div class="space-y-4">
                            <fieldset class="fieldset">
                                <legend class="fieldset-legend">Ответ модели (JSON)</legend>
                                <p v-if="!test_model_output && !test_response" class="label">
                                    После успешного запроса здесь появится распознанный.
                                </p>
                                <div
                                    v-else-if="test_model_output"
                                    class="mockup-code max-h-80 w-full overflow-auto"
                                >
                                    <pre class="px-4 py-3"><code class="whitespace-pre-wrap break-words text-xs">{{ test_model_output }}</code></pre>
                                </div>
                                <p v-else class="label text-warning">
                                    В сыром ответе не найден блок <code class="text-xs">output_text</code> с текстом модели.
                                </p>
                            </fieldset>

                            <fieldset class="fieldset h-full">
                                <legend class="fieldset-legend">Сырой ответ</legend>
                                <textarea
                                    :value="test_response || ''"
                                    class="textarea textarea-bordered h-full min-h-96 w-full font-mono text-xs"
                                    readonly
                                    placeholder="После отправки запроса здесь появится raw JSON-ответ."
                                />
                            </fieldset>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
