<script setup>
import {roleLabel as resolveRoleLabel} from '@/utils/roleLabel.js';
import {usePage} from '@inertiajs/vue3';
import {computed} from 'vue';

const appName = computed(() => usePage().props.app?.name ?? '');
const currentYear = computed(() => new Date().getFullYear());
const roleLabel = computed(() => resolveRoleLabel(usePage().props.auth?.role?.name));
</script>

<template>
    <footer class="flex items-center justify-between gap-4 border-t border-base-300/50 px-4 py-2 text-[11px] text-base-content/45">
        <div>
            © {{ currentYear }} {{ appName }}
            <span class="mx-1.5">·</span>
            Powered by Couch Protocol
        </div>
        <span v-if="roleLabel" class="shrink-0">{{ roleLabel }}</span>
    </footer>
</template>
