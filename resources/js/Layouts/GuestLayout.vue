<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();

const appName = computed(() => page.props.app?.name ?? 'P2P');
const appSlogan = computed(() => page.props.app?.slogan ?? '');

const highlights = [
    'Безопасные расчёты и контроль операций',
    'Учёт балансов и история транзакций',
    'Мониторинг в реальном времени',
];
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-base-200 px-4 py-8 sm:py-12">
        <div class="w-full max-w-5xl">
            <div class="flex flex-col gap-10 lg:flex-row lg:items-center lg:gap-14 xl:gap-20">
                <section class="hidden flex-1 lg:block">
                    <div class="max-w-sm">
                        <div class="flex justify-start">
                            <div class="flex items-center gap-2">
                                <svg class="size-10 shrink-0 text-primary xl:size-12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.562 3.266a.5.5 0 0 1 .876 0L15.39 8.87a1 1 0 0 0 1.516.294L21.183 5.5a.5.5 0 0 1 .798.519l-2.834 10.246a1 1 0 0 1-.956.734H5.81a1 1 0 0 1-.957-.734L2.02 6.02a.5.5 0 0 1 .798-.519l4.276 3.664a1 1 0 0 0 1.516-.294zM5 21h14" />
                                </svg>

                                <h1 class="text-4xl font-extrabold tracking-tight xl:text-5xl">
                                    {{ appName }}
                                </h1>
                            </div>
                        </div>

                        <p v-if="appSlogan" class="mt-2 text-sm font-medium text-base-content/70">
                            {{ appSlogan }}
                        </p>

                        <ul class="mt-8 space-y-3 text-sm text-base-content/75">
                            <li
                                v-for="item in highlights"
                                :key="item"
                                class="flex items-start gap-3"
                            >
                                <span class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-primary/15 text-primary">
                                    <svg class="size-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                                    </svg>
                                </span>
                                {{ item }}
                            </li>
                        </ul>

                        <p class="mt-6 text-xs text-base-content/40">
                            Powered by Couch Protocol
                        </p>
                    </div>
                </section>

                <section class="mx-auto w-full max-w-md lg:mx-0 lg:flex-1">
                    <div class="card border border-base-300 bg-base-100 shadow-xl sm:rounded-plate">
                        <div class="card-body p-6 sm:p-8">
                            <slot />
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>
