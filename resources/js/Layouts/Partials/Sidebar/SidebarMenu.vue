<script setup>
import SidebarNavItem from '@/Layouts/Partials/Sidebar/SidebarNavItem.vue';

defineProps({
    items: { type: Array, default: () => [] },
});
</script>

<template>
    <ul class="menu menu-md w-full space-y-0.5">
        <slot name="prepend" />
        <template v-for="item in items" :key="item.key">
            <SidebarNavItem
                v-if="item.show !== false"
                :href="item.href"
                :label="item.label"
                :icon="item.icon"
                :icon-class="item.iconClass"
                :active="item.active"
                :badge="item.badge"
                :badge-class="item.badgeClass ?? 'badge-primary'"
                :span-class="item.spanClass"
                :external="item.external"
            />
        </template>
    </ul>
</template>
