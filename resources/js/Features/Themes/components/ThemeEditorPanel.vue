<script setup>
import { computed } from 'vue';
import { useThemeGeneratorStore } from '../stores/themeGenerator.js';
import ThemeColorPicker from './ThemeColorPicker.vue';
import {
    BINARY_TOKENS,
    BORDER_OPTIONS,
    COLOR_GROUPS,
    RADIUS_OPTIONS,
    RADIUS_TOKENS,
    SIZE_OPTIONS,
    SIZE_TOKENS,
    validateThemeName,
} from '../lib/theme-schema.js';

const store = useThemeGeneratorStore();

const swatchLabel = (token) => token.replace('--color-', '').replace(/-/g, ' ');

const nameError = computed(() => (store.draft ? validateThemeName(store.draft.name) : null));

const tokenValue = (key) => store.draftTokens[key];

const setToken = (key, value) => store.updateToken(key, value);

const toggleBinary = (key) => {
    setToken(key, tokenValue(key) === '1' ? '0' : '1');
};
</script>

<template>
    <div v-if="store.draft" class="flex h-full flex-col">
        <div class="space-y-3 border-b border-base-content/10 p-3">
            <div>
                <label class="mb-1 block text-[11px] font-semibold uppercase opacity-60">Название темы</label>
                <input
                    type="text"
                    class="input input-sm input-bordered w-full"
                    :class="{ 'input-error': nameError }"
                    :value="store.draft.name"
                    @input="store.setName($event.target.value)"
                />
                <p v-if="nameError" class="mt-1 text-xs text-error">{{ nameError }}</p>
            </div>
            <div class="flex gap-2">
                <button type="button" class="btn btn-sm flex-1" @click="store.randomizeActive()">
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h4l12 16h0M20 4h-4M4 20h4l3-4M15 15l5 5m0-4v4h-4"/></svg>
                    Random
                </button>
                <button type="button" class="btn btn-sm flex-1" @click="store.cssModalOpen = true">
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m8 6-6 6 6 6M16 6l6 6-6 6"/></svg>
                    CSS
                </button>
            </div>
        </div>

        <div class="flex-1 space-y-6 overflow-y-auto p-3">
            <section>
                <h4 class="mb-2 text-[11px] font-semibold uppercase opacity-50">Цвета</h4>
                <div class="space-y-3">
                    <div v-for="group in COLOR_GROUPS" :key="group.key">
                        <p class="mb-1 text-xs font-medium opacity-70">{{ group.label }}</p>
                        <div class="grid gap-1.5" :class="group.key === 'base' ? 'grid-cols-2' : 'grid-cols-2'">
                            <ThemeColorPicker
                                v-for="token in group.swatches"
                                :key="token"
                                :token="token"
                                :label="swatchLabel(token)"
                            />
                        </div>
                    </div>
                </div>
            </section>

            <section>
                <h4 class="mb-2 text-[11px] font-semibold uppercase opacity-50">Радиусы</h4>
                <div class="space-y-3">
                    <div v-for="item in RADIUS_TOKENS" :key="item.key">
                        <div class="mb-1 flex items-baseline justify-between">
                            <span class="text-xs font-medium">{{ item.label }}</span>
                            <span class="text-[10px] opacity-50">{{ item.hint }}</span>
                        </div>
                        <div class="join w-full">
                            <button
                                v-for="option in RADIUS_OPTIONS"
                                :key="option"
                                type="button"
                                class="btn btn-xs join-item flex-1"
                                :class="tokenValue(item.key) === option ? 'btn-primary' : 'btn-ghost'"
                                @click="setToken(item.key, option)"
                            >{{ option.replace('rem', '') }}</button>
                        </div>
                    </div>
                </div>
            </section>

            <section>
                <h4 class="mb-2 text-[11px] font-semibold uppercase opacity-50">Размеры</h4>
                <div class="space-y-3">
                    <div v-for="item in SIZE_TOKENS" :key="item.key">
                        <div class="mb-1 flex items-baseline justify-between">
                            <span class="text-xs font-medium">{{ item.label }}</span>
                            <span class="text-[10px] opacity-50">{{ item.hint }}</span>
                        </div>
                        <div class="join w-full">
                            <button
                                v-for="option in SIZE_OPTIONS"
                                :key="option"
                                type="button"
                                class="btn btn-xs join-item flex-1"
                                :class="tokenValue(item.key) === option ? 'btn-primary' : 'btn-ghost'"
                                @click="setToken(item.key, option)"
                            >{{ parseFloat(option) }}</button>
                        </div>
                    </div>
                    <div>
                        <span class="mb-1 block text-xs font-medium">Толщина рамки</span>
                        <div class="join w-full">
                            <button
                                v-for="option in BORDER_OPTIONS"
                                :key="option"
                                type="button"
                                class="btn btn-xs join-item flex-1"
                                :class="tokenValue('--border') === option ? 'btn-primary' : 'btn-ghost'"
                                @click="setToken('--border', option)"
                            >{{ option }}</button>
                        </div>
                    </div>
                </div>
            </section>

            <section>
                <h4 class="mb-2 text-[11px] font-semibold uppercase opacity-50">Эффекты</h4>
                <div class="space-y-2">
                    <label v-for="item in BINARY_TOKENS" :key="item.key" class="flex cursor-pointer items-center justify-between">
                        <span class="text-xs font-medium">{{ item.label }}</span>
                        <input
                            type="checkbox"
                            class="toggle toggle-sm toggle-primary"
                            :checked="tokenValue(item.key) === '1'"
                            @change="toggleBinary(item.key)"
                        />
                    </label>
                </div>
            </section>

            <section>
                <h4 class="mb-2 text-[11px] font-semibold uppercase opacity-50">Опции</h4>
                <div class="space-y-2">
                    <label class="flex cursor-pointer items-center justify-between">
                        <span class="text-xs font-medium">Тёмная цветовая схема</span>
                        <input
                            type="checkbox"
                            class="toggle toggle-sm"
                            :checked="store.draft.colorScheme === 'dark'"
                            @change="store.setColorScheme($event.target.checked ? 'dark' : 'light')"
                        />
                    </label>
                    <label class="flex cursor-pointer items-center justify-between">
                        <span class="text-xs font-medium">Тема по умолчанию</span>
                        <input type="checkbox" class="toggle toggle-sm" :checked="store.draft.isDefault" @change="store.setDefault($event.target.checked)" />
                    </label>
                    <label class="flex cursor-pointer items-center justify-between">
                        <span class="text-xs font-medium">Тёмная по умолчанию</span>
                        <input type="checkbox" class="toggle toggle-sm" :checked="store.draft.isPrefersDark" @change="store.setPrefersDark($event.target.checked)" />
                    </label>
                    <button
                        v-if="store.isDraftCustom"
                        type="button"
                        class="btn btn-outline btn-error btn-xs mt-2 w-full"
                        @click="store.removeTheme()"
                    >Удалить тему</button>
                </div>
            </section>
        </div>
    </div>
</template>
