<script setup>
import { ref, onMounted, onUnmounted, provide, nextTick } from "vue";

const isOpen = ref(false);
const dropdown = ref(null);
const button = ref(null);
const dropdownPosition = ref({ top: 0, left: 0 });

const toggleDropdown = async () => {
    isOpen.value = !isOpen.value;

    if (isOpen.value) {
        await nextTick();

        if (button.value && dropdown.value) {
            const rect = button.value.getBoundingClientRect();
            dropdownPosition.value = {
                top: rect.bottom + window.scrollY + 4,
                left: rect.right + window.scrollX - 240,
            };
        }
    }
};

const closeDropdown = () => {
    isOpen.value = false;
};

provide("closeMenu", closeDropdown);

const overlay = ref(null);

const handleClickOutside = (event) => {
    if (
        dropdown.value &&
        !dropdown.value.contains(event.target) &&
        button.value &&
        !button.value.contains(event.target) &&
        overlay.value &&
        !overlay.value.contains(event.target)
    ) {
        isOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener("click", handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener("click", handleClickOutside);
});
</script>

<template>
    <div class="relative inline-block">
        <button
            @click="toggleDropdown"
            ref="button"
            class="btn btn-ghost btn-circle btn-sm"
            type="button"
            aria-label="Информация"
        >
            <svg class="w-5 h-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 7h.01M12 11v6m9-5a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
            </svg>
        </button>

        <teleport to="body">
            <div
                v-if="isOpen"
                ref="overlay"
                class="fixed inset-0 z-40"
                @click="closeDropdown"
            ></div>

            <div
                v-if="isOpen"
                ref="dropdown"
                class="absolute z-50 bg-base-100 border border-base-300 rounded-box shadow-lg pointer-events-auto"
                :style="{ top: dropdownPosition.top + 'px', left: dropdownPosition.left + 'px', minWidth: '220px' }"
            >
                <div class="p-3">
                    <slot />
                </div>
            </div>
        </teleport>
    </div>
</template>
