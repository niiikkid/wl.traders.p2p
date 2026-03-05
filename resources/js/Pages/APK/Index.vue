<script setup>
import {Head, usePage} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {useClipboard} from "@vueuse/core";

defineOptions({ layout: AuthenticatedLayout })
</script>

<template>
    <Head title="Приложение для подтверждения по СМС и Push"/>

    <div>
        <section class="py-8 antialiased md:py-16">
            <div class="mx-auto max-w-screen-xl px-4 2xl:px-0">
                <div class="mx-auto max-w-3xl space-y-6 sm:space-y-8">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white sm:text-2xl">Приложение для подтверждения по СМС и Push</h2>

                    <ol class="relative border-s border-gray-200 dark:border-gray-700">
                        <li class="mb-8 ms-6">
                                  <span
                                      class="absolute -start-2.5 flex h-5 w-5 items-center justify-center rounded-full bg-gray-200 dark:bg-gray-800 dark:ring-gray-900">
                                    <svg class="h-3 w-3 text-gray-500 dark:text-gray-400" aria-hidden="true"
                                         xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none"
                                         viewBox="0 0 24 24">
                                      <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                            stroke-width="2" d="M5 11.917 9.724 16.5 19 7.5"/>
                                    </svg>
                                  </span>
                            <h3 class="mb-1.5 text-lg font-semibold leading-none text-gray-900 dark:text-white">
                                1. Скачайте и установите APK
                            </h3>
                            <p class="mb-3 text-base font-normal text-gray-500 dark:text-gray-400">
                                Для получения СМС нужно приложение, которое доступно только для Android
                            </p>
                            <p class="text-base font-normal text-gray-500 dark:text-gray-400">
                                <a :href="route('app.download')" class="text-primary">Скачать</a>
                            </p>
                        </li>
                        <li class="mb-10 ms-6">
                                  <span
                                      class="absolute -start-2.5 flex h-5 w-5 items-center justify-center rounded-full bg-gray-200 dark:bg-gray-800 dark:ring-gray-900">
                                    <svg class="h-3 w-3 text-gray-500 dark:text-gray-400" aria-hidden="true"
                                         xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none"
                                         viewBox="0 0 24 24">
                                      <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                            stroke-width="2" d="M5 11.917 9.724 16.5 19 7.5"/>
                                    </svg>
                                  </span>
                            <h3 class="mb-1.5 text-lg font-semibold leading-none text-gray-900 dark:text-white">
                                2. Запустите приложение и укажите данные
                            </h3>
                            <p class="text-base font-normal text-gray-500 dark:text-gray-400">
                                Откройте приложение, нажмите на плюс и создайте новый метод. При создании впишите:
                            </p>

                            <p class="text-base font-normal text-gray-500 dark:text-gray-400">

                            </p>
                        </li>

                        <li class="ms-6">
                                  <span
                                      class="absolute -start-2.5 flex h-5 w-5 items-center justify-center rounded-full bg-gray-200 dark:bg-gray-800 dark:ring-gray-900">
                                    <svg class="h-3 w-3 text-gray-500 dark:text-gray-400" aria-hidden="true"
                                         xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none"
                                         viewBox="0 0 24 24">
                                      <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                            stroke-width="2" d="M5 11.917 9.724 16.5 19 7.5"/>
                                    </svg>
                                  </span>
                            <h3 class="mb-1.5 text-lg font-semibold leading-none text-gray-900 dark:text-white">
                                3. Сохраните и всё. Всё готово!
                            </h3>
                            <p class="text-base font-normal text-gray-500 dark:text-gray-400">
                                При получении СМС от банка, мы его получим от Вашего телефона. Главное, чтобы он работал
                                :)
                            </p>
                        </li>
                    </ol>
                </div>
            </div>
        </section>
    </div>
</template>

