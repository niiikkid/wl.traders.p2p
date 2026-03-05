<script setup>
import { ref, onMounted, onUnmounted, provide, nextTick } from "vue";

const props = defineProps({
    buttonClass: {
        type: String,
        default: 'btn btn-ghost btn-circle btn-sm',
    },
});

const isOpen = ref(false);
const dropdown = ref(null);
const button = ref(null);
const dropdownPosition = ref({ top: 0, left: 0, width: 0 });

const toggleDropdown = async () => {
    isOpen.value = !isOpen.value;

    if (isOpen.value) {
        await nextTick(); // Ждём ререндер перед получением координат

        if (button.value && dropdown.value) {
            const rect = button.value.getBoundingClientRect();
            dropdownPosition.value = {
                top: rect.bottom + window.scrollY + 4, // Отступ 4px
                left: rect.right + window.scrollX - 120, // Сдвигаем влево на 120px от правого края кнопки
                width: rect.width,
            };
        }
    }
};

const closeDropdown = () => {
    isOpen.value = false;
};

// Передаём `closeMenu` дочерним компонентам
provide("closeMenu", closeDropdown);

const overlay = ref(null);

// Закрытие при клике вне меню
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
            :class="props.buttonClass"
            type="button"
        >
            <slot name="icon">
                <svg class="w-6 h-6" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M12 6h.01M12 12h.01M12 18h.01"/>
                </svg>
            </slot>
        </button>

        <!-- Используем teleport, чтобы меню было вне ограничений таблицы -->
        <teleport to="body">
            <!-- Overlay для блокировки кликов под дропдауном -->
            <div
                v-if="isOpen"
                ref="overlay"
                class="fixed inset-0 z-40"
                @click="closeDropdown"
            ></div>
            
            <!-- Дропдаун меню -->
            <div
                v-if="isOpen"
                ref="dropdown"
                class="absolute z-50 bg-base-100 border border-base-300 rounded-box shadow-lg pointer-events-auto"
                :style="{ top: dropdownPosition.top + 'px', left: dropdownPosition.left + 'px' }"
            >
                <ul class="menu p-2">
                    <slot />
                </ul>
            </div>
        </teleport>
    </div>
</template>

