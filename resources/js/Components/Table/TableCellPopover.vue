<script setup>
import {computed, ref, onMounted, onUnmounted, provide, nextTick} from "vue";

const isOpen = ref(false);
const dropdown = ref(null);
const button = ref(null);
const dropdownPosition = ref({top: 0, left: 0});
const isMobile = ref(false);

const toggleDropdown = async () => {
    isOpen.value = !isOpen.value;

    if (isOpen.value) {
        await nextTick();

        if (button.value && dropdown.value) {
            const rect = button.value.getBoundingClientRect();
            const dropdownWidth = dropdown.value.offsetWidth;
            const viewportWidth = window.innerWidth;
            const horizontalPadding = 8;
            const maxLeft = viewportWidth - dropdownWidth - horizontalPadding;
            const defaultLeft = rect.left + window.scrollX;
            const mobileLeft = rect.right + window.scrollX - dropdownWidth;
            const targetLeft = isMobile.value ? mobileLeft : defaultLeft;

            dropdownPosition.value = {
                top: rect.bottom + window.scrollY + 6,
                left: Math.max(horizontalPadding, Math.min(targetLeft, maxLeft)),
            };
        }
    }
};

const closeDropdown = () => {
    isOpen.value = false;
};

provide("closeMenu", closeDropdown);

const overlay = ref(null);

const updateMobileState = () => {
    isMobile.value = window.innerWidth < 768;
};

const dropdownStyles = computed(() => ({
    top: `${dropdownPosition.value.top}px`,
    left: `${dropdownPosition.value.left}px`,
    minWidth: "260px",
    maxHeight: isMobile.value ? "60vh" : "none",
    overflowY: isMobile.value ? "auto" : "visible",
}));

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
    updateMobileState();
    document.addEventListener("click", handleClickOutside);
    window.addEventListener("resize", updateMobileState);
});

onUnmounted(() => {
    document.removeEventListener("click", handleClickOutside);
    window.removeEventListener("resize", updateMobileState);
});
</script>

<template>
    <div class="relative inline-block">
        <button
            @click="toggleDropdown"
            ref="button"
            type="button"
            class="btn btn-ghost btn-sm h-auto px-0"
            aria-label="Открыть лимиты"
        >
            <slot name="trigger" />
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
                :style="dropdownStyles"
            >
                <div class="p-3">
                    <slot />
                </div>
            </div>
        </teleport>
    </div>
</template>
