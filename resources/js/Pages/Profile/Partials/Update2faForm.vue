<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import {useClipboard} from "@vueuse/core";
import {ref} from "vue";

const auth2fa = ref(usePage().props.auth2fa);

const form = useForm({
    'secret': auth2fa.value.secret
});

const submit = () => {
    form.patch(route('profile.update.auth2fa'), {
        preserveScroll: true,
        onFinish: () => {
            form.reset();
            auth2fa.value = usePage().props.auth2fa
        }
    });
}

const { copy, copied } = useClipboard()

</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium">Настройка 2FA авторизации</h2>

            <p class="mt-1 text-sm text-base-content/70">
                Вы можете сканировать QR-код или самостоятельно скопировать секретный ключ.
            </p>
        </header>

        <form class="mt-6 space-y-6">
            <template v-if="auth2fa.qr">
                <div class="flex justify-center rounded">
                    <div v-html="auth2fa.qr" class="card overflow-hidden"></div>
                </div>
                <div class="flex justify-center">
                    <div class="flex gap-2">
                        <span class="text-base-content/70">Секретный ключ:</span>
                        <div class="tooltip tooltip-top" :data-tip="copied ? 'Скопировано!' : 'Скопировать'">
                            <span
                                class="text-base-content hover:text-base-content/70 hover:cursor-pointer"
                                @click.prevent="copy(auth2fa.secret)"
                            >{{auth2fa.secret}}</span>
                        </div>
                    </div>
                </div>

                <div class="alert alert-info">
                    <svg class="w-4 h-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5ZM9.5 4a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3ZM12 15H8a1 1 0 0 1 0-2h1v-3H8a1 1 0 0 1 0-2h2a1 1 0 0 1 1 1v4h1a1 1 0 0 1 0 2Z"/>
                    </svg>
                    <div>
                        Необходимо скачать приложение Google Authenticator
                    </div>
                </div>

                <div class="alert alert-info">
                    <svg class="w-4 h-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5ZM9.5 4a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3ZM12 15H8a1 1 0 0 1 0-2h1v-3H8a1 1 0 0 1 0-2h2a1 1 0 0 1 1 1v4h1a1 1 0 0 1 0 2Z"/>
                    </svg>
                    <div>
                        Не забудьте нажать "Сохранить" после добавления кода.
                    </div>
                </div>
            </template>
            <template v-else>
                <div class="alert alert-info">
                    <svg class="w-4 h-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5ZM9.5 4a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3ZM12 15H8a1 1 0 0 1 0-2h1v-3H8a1 1 0 0 1 0-2h2a1 1 0 0 1 1 1v4h1a1 1 0 0 1 0 2Z"/>
                    </svg>
                    <div>
                        Вы уже настроили 2FA на аккаунте. Если хотите сбросить 2FA, то обратитесь к администратору.
                    </div>
                </div>
            </template>

            <div v-if="auth2fa.qr" class="flex items-center gap-4">
                <PrimaryButton
                    @click="submit"
                    :disabled="form.processing"
                >
                    Сохранить
                </PrimaryButton>

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
    </section>
</template>
