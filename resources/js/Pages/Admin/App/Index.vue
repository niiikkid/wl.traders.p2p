<script setup>
import {Head, useForm} from '@inertiajs/vue3';
import {computed, ref} from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DateTime from '@/Components/DateTime.vue';

defineOptions({ layout: AuthenticatedLayout })

const props = defineProps({
    isUploaded: {
        type: Boolean,
        default: false,
    },
    lastUploadedAt: {
        type: String,
        default: null,
    },
    downloadUrl: {
        type: String,
        default: null,
    },
});

const fileInput = ref(null);
const form = useForm({
    apk: null,
});

const selectedFileName = computed(() => form.apk?.name ?? 'Файл не выбран');

const selectFile = () => {
    fileInput.value?.click();
};

const onFileChange = (event) => {
    const [file] = event.target.files ?? [];
    form.apk = file || null;
    form.clearErrors('apk');
};

const submit = () => {
    if (!form.apk) {
        form.setError('apk', 'Выберите APK файл');
        return;
    }

    form.post(route('admin.app.store'), {
        forceFormData: true,
        onFinish: () => {
            form.reset('apk');
            if (fileInput.value) {
                fileInput.value.value = '';
            }
        },
    });
};
</script>

<template>
    <Head title="Приложение" />

    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl sm:text-3xl font-bold text-base-content">Приложение</h1>
                <p class="text-sm text-base-content/70">Управление APK для трейдера</p>
            </div>
            <div class="badge px-4 py-3 text-sm" :class="props.isUploaded ? 'badge-success' : 'badge-neutral'">
                {{ props.isUploaded ? 'Загружено' : 'Не загружено' }}
            </div>
        </div>

        <div class="card bg-base-100 shadow">
            <div class="card-body space-y-5">
                <div class="flex flex-col gap-2">
                    <div class="flex flex-wrap items-center gap-2 text-sm text-base-content/80">
                        <span class="font-medium text-base-content">Последнее обновление:</span>
                        <DateTime v-if="props.lastUploadedAt" :data="props.lastUploadedAt" simple />
                        <span v-else class="text-warning">Пока не загружено</span>
                    </div>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <input
                        ref="fileInput"
                        type="file"
                        class="hidden"
                        accept=".apk,application/vnd.android.package-archive"
                        @change="onFileChange"
                    />
                    <button type="button" class="btn btn-outline" @click="selectFile" :disabled="form.processing">
                        Выбрать файл
                    </button>
                    <div class="flex-1 text-sm text-base-content/70 truncate">{{ selectedFileName }}</div>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <button
                        type="button"
                        class="btn btn-primary"
                        @click="submit"
                        :disabled="form.processing"
                        :class="{ loading: form.processing }"
                    >
                        Загрузить
                    </button>
                    <a
                        v-if="props.isUploaded"
                        :href="props.downloadUrl"
                        class="btn btn-outline"
                        download="p2p-bridge.apk"
                    >
                        Скачать
                    </a>
                    <span v-else class="text-sm text-base-content/60">APK еще не загружен.</span>
                </div>

                <div class="text-xs text-base-content/60 space-y-1">
                    <p>Допускается только APK.</p>
                    <p>Файл заменит текущий для скачивания трейдерами.</p>
                </div>

                <div v-if="form.errors.apk" class="alert alert-error shadow-sm">
                    <span class="text-sm">{{ form.errors.apk }}</span>
                </div>
            </div>
        </div>
    </div>
</template>
