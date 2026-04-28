<script setup>
import {Head, Link} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

defineOptions({layout: AuthenticatedLayout});
</script>

<template>
    <Head title="API Интеграция"/>

    <div class="antialiased">
        <div class="mx-auto max-w-7xl">
            <h2 class="text-3xl font-bold text-base-content mb-6">
                API Интеграция
            </h2>

            <div class="card bg-base-100 shadow">
                <div class="card-body gap-4">
                    <p class="text-base-content/80">
                        Здесь будет документация и инструменты для новой версии API. Раздел пока в разработке.
                    </p>
                    <div>
                        <Link
                            :href="route('integration.index')"
                            class="link link-primary font-medium"
                        >
                            Перейти к документации и консоли Legacy API
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
