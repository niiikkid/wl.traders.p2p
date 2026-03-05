<script setup>
import { inject, defineEmits } from "vue";

const closeMenu = inject("closeMenu");
const emit = defineEmits(["click"]);

const handleClick = (event) => {
    event.stopPropagation(); // Предотвращаем закрытие меню до выполнения действия
    emit("click"); // Вызываем переданный @click в родителе
    closeMenu(); // Закрываем меню
};
</script>

<template>
    <li>
        <a role="button" @click="handleClick" class="justify-start">
            <slot />
        </a>
    </li>
</template>
