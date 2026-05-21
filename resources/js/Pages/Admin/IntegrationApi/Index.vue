<script setup>
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useAppClipboard } from '@/composables/useAppClipboard.js';

const props = defineProps({
    token: {
        type: String,
        required: true,
    },
});

const token = ref(props.token);
const regenerating = ref(false);
const { copy, copied } = useAppClipboard();

const regenerateToken = async () => {
    if (regenerating.value) {
        return;
    }

    regenerating.value = true;

    try {
        const response = await axios.post(route('admin.integration-api.regenerate-token'));
        const newToken = response?.data?.data?.token;

        if (typeof newToken === 'string' && newToken.length > 0) {
            token.value = newToken;
        }
    } finally {
        regenerating.value = false;
    }
};

defineOptions({ layout: AuthenticatedLayout });
</script>

<template>
    <Head title="Интеграционный API" />

    <div class="mx-auto max-w-5xl space-y-6">
        <h1 class="text-2xl font-bold">Интеграционный API</h1>

        <div class="card bg-base-100 shadow">
            <div class="card-body space-y-4">
                <p class="text-sm text-base-content/70">
                    Используйте токен в заголовке <code>Access-Token</code> для доступа к <code>/api/integration/v1/*</code>.
                </p>

                <label class="form-control w-full">
                    <span class="label-text mb-2">Токен доступа</span>
                    <input
                        type="text"
                        class="input input-bordered w-full"
                        :value="token"
                        readonly
                    >
                </label>

                <div class="flex items-center gap-3">
                    <button class="btn btn-outline btn-sm" type="button" @click="copy(token)">
                        {{ copied ? 'Скопировано' : 'Скопировать' }}
                    </button>
                    <button class="btn btn-primary btn-sm" type="button" :disabled="regenerating" @click="regenerateToken">
                        {{ regenerating ? 'Обновляем...' : 'Перегенерировать токен' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
