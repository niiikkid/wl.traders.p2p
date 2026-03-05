<script setup>
import { computed } from 'vue';

const props = defineProps({
    response: {
        type: Object,
        default: null
    },
    responseError: {
        type: [Object, String],
        default: null
    }
});

const emit = defineEmits(['clear']);

const formatJSON = (str) => {
    if (!str || typeof str !== 'string') {
        return str;
    }

    const trimmed = str.trim();
    if (!trimmed) {
        return str;
    }

    // Пытаемся распарсить как JSON
    try {
        const parsed = JSON.parse(trimmed);
        return JSON.stringify(parsed, null, 2);
    } catch (error) {
        // Если не JSON, возвращаем как есть
        return str;
    }
};

const safeStringify = (payload) => {
    if (payload === undefined || payload === null) {
        return '';
    }

    if (typeof payload === 'string') {
        return formatJSON(payload);
    }

    try {
        return JSON.stringify(payload, null, 2);
    } catch (error) {
        return '';
    }
};

const getDisplayBody = (payload) => {
    if (!payload) {
        return '';
    }

    if (typeof payload === 'string') {
        return formatJSON(payload);
    }

    if (typeof payload.rawBody === 'string' && payload.rawBody.length > 0) {
        return formatJSON(payload.rawBody);
    }

    if (typeof payload.data === 'string') {
        return formatJSON(payload.data);
    }

    if (typeof payload.errors === 'string') {
        return formatJSON(payload.errors);
    }

    if (payload.errors && Object.keys(payload.errors).length > 0) {
        return safeStringify(payload.errors);
    }

    if (payload.data !== undefined) {
        return safeStringify(payload.data);
    }

    return safeStringify(payload);
};

const responseBody = computed(() => getDisplayBody(props.response));
const responseErrorBody = computed(() => getDisplayBody(props.responseError));
const responseErrorMessage = computed(() => {
    if (!props.responseError) {
        return '';
    }

    if (typeof props.responseError === 'string') {
        return props.responseError;
    }

    return props.responseError.message;
});

const getResponseStatus = (response) => {
    if (!response) {
        return null;
    }

    if (typeof response === 'object') {
        // Проверяем различные возможные места, где может быть статус
        if (response.status !== undefined) {
            return response.status;
        }
        if (response.response && response.response.status !== undefined) {
            return response.response.status;
        }
    }

    return null;
};

const responseStatus = computed(() => getResponseStatus(props.response));
const responseErrorStatus = computed(() => getResponseStatus(props.responseError));
</script>

<template>
    <div v-if="response || responseError" class="space-y-3">
        <div class="flex justify-between items-center">
            <h4 class="font-semibold text-sm">Результат запроса</h4>
            <button @click="$emit('clear')" class="btn btn-xs btn-ghost">Закрыть</button>
        </div>

        <div v-if="response" class="space-y-2">
            <div>
                <span class="badge badge-sm" :class="responseStatus && responseStatus < 400 ? 'badge-success' : 'badge-error'">
                    HTTP {{ responseStatus || 'N/A' }}
                </span>
            </div>
            <div v-if="responseBody">
                <pre class="bg-base-200 p-3 rounded-lg overflow-x-auto text-xs max-h-96 overflow-y-auto"><code>{{ responseBody }}</code></pre>
            </div>
        </div>

        <div v-if="responseError" class="space-y-2">
            <div>
                <span v-if="responseErrorStatus" class="badge badge-sm badge-error">
                    HTTP {{ responseErrorStatus }}
                </span>
                <span v-else class="badge badge-sm badge-error">Ошибка</span>
            </div>
            <div v-if="responseErrorMessage">
                <p class="text-error text-sm">{{ responseErrorMessage }}</p>
            </div>
            <div v-if="responseErrorBody">
                <pre class="bg-base-200 p-3 rounded-lg overflow-x-auto text-xs max-h-96 overflow-y-auto"><code>{{ responseErrorBody }}</code></pre>
            </div>
        </div>
    </div>
</template>

