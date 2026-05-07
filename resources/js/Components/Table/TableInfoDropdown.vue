<script setup>
import { computed, ref, onMounted, onUnmounted, provide, nextTick } from "vue";

const props = defineProps({
    buttonClass: {
        type: String,
        default: "btn btn-ghost btn-circle btn-sm",
    },
});

const isOpen = ref(false);
const dropdown = ref(null);
const button = ref(null);
const dropdownPosition = ref({ top: 0, left: 0 });
const dropdownMaxHeight = ref(null);
const dropdownWidth = 220;

const updateDropdownPosition = () => {
    if (!button.value || !dropdown.value) {
        return;
    }

    const gap = 4;
    const viewportPadding = 8;
    const rect = button.value.getBoundingClientRect();
    const currentDropdownWidth = dropdown.value.offsetWidth || dropdownWidth;
    const dropdownHeight = dropdown.value.offsetHeight;
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    const minLeft = window.scrollX + viewportPadding;
    const maxLeft = window.scrollX + viewportWidth - currentDropdownWidth - viewportPadding;
    const targetLeft = rect.right + window.scrollX - currentDropdownWidth;
    const spaceAbove = rect.top - viewportPadding - gap;
    const spaceBelow = viewportHeight - rect.bottom - viewportPadding - gap;
    const opensUp = spaceAbove > spaceBelow;
    const availableHeight = Math.max(120, opensUp ? spaceAbove : spaceBelow);
    const top = opensUp
        ? rect.top + window.scrollY - Math.min(dropdownHeight, availableHeight) - gap
        : rect.bottom + window.scrollY + gap;

    dropdownPosition.value = {
        top: Math.max(window.scrollY + viewportPadding, top),
        left: Math.max(minLeft, Math.min(targetLeft, maxLeft)),
    };
    dropdownMaxHeight.value = `${availableHeight}px`;
};

const toggleDropdown = async () => {
    isOpen.value = !isOpen.value;

    if (isOpen.value) {
        await nextTick();
        updateDropdownPosition();
    }
};

const closeDropdown = () => {
    isOpen.value = false;
};

provide("closeMenu", closeDropdown);

const overlay = ref(null);

const dropdownStyles = computed(() => ({
    top: `${dropdownPosition.value.top}px`,
    left: `${dropdownPosition.value.left}px`,
    minWidth: `${dropdownWidth}px`,
    maxHeight: dropdownMaxHeight.value ?? "none",
    overflowY: dropdownMaxHeight.value ? "auto" : "visible",
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
    document.addEventListener("click", handleClickOutside);
    window.addEventListener("resize", updateDropdownPosition);
});

onUnmounted(() => {
    document.removeEventListener("click", handleClickOutside);
    window.removeEventListener("resize", updateDropdownPosition);
});
</script>

<template>
    <div class="relative inline-block">
        <button
            @click="toggleDropdown"
            ref="button"
            :class="props.buttonClass"
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
                :style="dropdownStyles"
            >
                <div class="p-3">
                    <slot />
                </div>
            </div>
        </teleport>
    </div>
</template>
