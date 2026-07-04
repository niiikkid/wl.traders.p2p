<script setup>
import { getCurrentInstance, computed } from 'vue';
import CurrencyDisplay from '@/Components/Currency/CurrencyDisplay.vue';
import CurrencyPairDisplay from '@/Components/Currency/CurrencyPairDisplay.vue';

const props = defineProps({
    label: {
        type: String,
    },
    items: {
        type: Array,
    },
    value: {
        type: String,
    },
    name: {
        type: String,
    },
    currencyIcons: {
        type: Boolean,
        default: false,
    },
    pairBase: {
        type: String,
        default: null,
    },
});

const model = defineModel({
    required: true,
});

const change = (item, event) => {
    if (event.target.checked) {
        model.value = item;
    } else {
        model.value = null;
    }
};

const { uid } = getCurrentInstance();

const selectedLabel = computed(() => {
    if (!model.value) {
        return null;
    }

    const item = (props.items ?? []).find((entry) => entry[props.value] === model.value);

    return item?.[props.name] ?? String(model.value).toUpperCase();
});
</script>

<template>
    <div class="dropdown">
        <div tabindex="0" role="button" class="input input-bordered w-48 flex items-center justify-between focus:outline-none focus:ring-0">
            <span class="flex min-w-0 items-center gap-2 truncate">
                <template v-if="model">
                    <span v-if="!currencyIcons" class="truncate">{{ 'Валюта: ' }}<span class="ml-1">{{ model }}</span></span>
                    <template v-else>
                        <CurrencyPairDisplay
                            v-if="pairBase"
                            :base-currency="pairBase"
                            :quote-currency="model"
                            :show-label="false"
                            size="sm"
                            :icon-size="18"
                        />
                        <CurrencyDisplay
                            v-else
                            :currency="model"
                            :show-label="false"
                            size="sm"
                            :icon-size="18"
                        />
                        <span class="truncate text-sm">{{ selectedLabel }}</span>
                    </template>
                </template>
                <template v-else>{{ label }}</template>
            </span>
            <svg class="w-2.5 h-2.5 ms-3 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 10 6">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 4 4 4-4"/>
            </svg>
        </div>

        <ul tabindex="0" class="dropdown-content z-[100] flex w-52 flex-col flex-nowrap gap-0.5 rounded-box border border-base-300 bg-base-100 p-2 shadow">
            <li v-for="(item, index) in items" :key="index" class="w-full">
                <label class="label cursor-pointer justify-start gap-3 px-2 py-2">
                    <input
                        :id="'radio-'+uid+'-'+index"
                        type="radio"
                        :name="'radio'+uid"
                        :value="item[value]"
                        :checked="model === item[value]"
                        @change="change(item[value], $event)"
                        class="radio radio-sm"
                    >
                    <span :for="'radio-'+uid+'-'+index" class="label-text flex min-w-0 items-center gap-2 text-sm">
                        <CurrencyPairDisplay
                            v-if="currencyIcons && pairBase"
                            :base-currency="pairBase"
                            :quote-currency="item[value]"
                            :show-label="false"
                            size="sm"
                            :icon-size="18"
                        />
                        <CurrencyDisplay
                            v-else-if="currencyIcons"
                            :currency="item[value]"
                            :show-label="false"
                            size="sm"
                            :icon-size="18"
                        />
                        <span class="truncate">{{ item[name] }}</span>
                    </span>
                </label>
            </li>
        </ul>
    </div>
</template>
