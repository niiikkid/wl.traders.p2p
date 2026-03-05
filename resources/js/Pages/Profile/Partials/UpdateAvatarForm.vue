<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { useForm, usePage } from '@inertiajs/vue3';

const user = usePage().props.auth.user;

const form = useForm({
    avatar_uuid: user.avatar_uuid,
    avatar_style: user.avatar_style,
});

const generateAvatarUUID = () => {
    form.avatar_uuid = generateUUID();
}

const generateUUID = () => {
    // Проверяем, доступен ли crypto и randomUUID
    if (typeof crypto !== 'undefined' && crypto.randomUUID) {
        // Используем встроенный метод randomUUID, если доступен
        return crypto.randomUUID();
    }

    // Если crypto.randomUUID недоступен, генерируем UUID вручную
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
        const r = (Math.random() * 16) | 0; // Генерация случайного числа от 0 до 15
        const v = c === 'x' ? r : (r & 0x3) | 0x8; // Для 'x' используем r, для 'y' используем (r & 0x3) | 0x8
        return v.toString(16); // Преобразуем число в шестнадцатеричную строку
    });
}

const avatarsStyles = [
    'adventurer',
    'avataaars',
    'big-ears',
    'big-smile',
    'bottts',
    'croodles',
    'dylan',
    'fun-emoji',
    'lorelei',
    'micah',
    'open-peeps',
    'notionists',
    'miniavs',
    'personas',
    'pixel-art',
];
</script>

<template>
    <div>
        <header>
            <h2 class="text-lg font-medium">Редактирование аватара</h2>

            <p class="mt-1 text-sm text-base-content/70">
                Вы можете выбрать стиль аватара и/или сгенерировать новый.
            </p>
        </header>

        <form @submit.prevent="form.patch(route('profile.update.avatar'))" class="mt-6 space-y-6">
            <div class="flex flex-wrap justify-center items-center gap-4 py-2">
                <img
                    v-for="avatar in avatarsStyles"
                    @click="form.avatar_style = avatar"
                    :src="'https://api.dicebear.com/9.x/'+avatar+'/svg?seed='+form.avatar_uuid"
                    class="w-16 h-16 rounded-full hover:outline hover:outline-primary hover:cursor-pointer"
                    :class="form.avatar_style === avatar ? 'outline outline-primary' : ''"
                    alt="user photo"
                >
            </div>

            <div class="flex justify-center">
                <a
                    href="#"
                    @click.prevent="generateAvatarUUID"
                    class="px-0 py-0 text-primary hover:text-primary/80 inline-flex items-center hover:underline"
                >
                    <svg class="w-8 h-8" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.651 7.65a7.131 7.131 0 0 0-12.68 3.15M18.001 4v4h-4m-7.652 8.35a7.13 7.13 0 0 0 12.68-3.15M6 20v-4h4"/>
                    </svg>
                </a>
            </div>

            <div class="flex items-center gap-4">
                <PrimaryButton :disabled="form.processing">Сохранить</PrimaryButton>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p v-if="form.recentlySuccessful" class="text-sm text-base-content/70">Сохранено.</p>
                </Transition>
            </div>
        </form>
    </div>
</template>
