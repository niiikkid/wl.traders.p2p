<script setup>
import axios from 'axios';
import {onMounted, onUnmounted, ref, watch} from 'vue';

const props = defineProps({
    modelValue: {
        type: [String, Number, null],
        default: '',
    },
    searchRoute: {
        type: String,
        required: true,
    },
});

const emit = defineEmits(['update:modelValue']);

const searchQuery = ref('');
const traders = ref([]);
const loading = ref(false);
const showDropdown = ref(false);
const selectedTrader = ref(null);

const searchTraders = async () => {
    if (!searchQuery.value.trim() && !props.modelValue) {
        traders.value = [];
        return;
    }

    loading.value = true;

    try {
        const response = await axios.get(props.searchRoute, {
            params: {query: searchQuery.value},
        });

        traders.value = response.data;
        showDropdown.value = true;
    } catch (error) {
        console.error('Ошибка при поиске трейдера:', error);
    } finally {
        loading.value = false;
    }
};

let searchTimeout;
watch(searchQuery, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        searchTraders();
    }, 300);
});

const selectTrader = (trader) => {
    selectedTrader.value = trader;
    searchQuery.value = trader.label;
    emit('update:modelValue', trader.value);
    showDropdown.value = false;
};

const clearSelection = () => {
    selectedTrader.value = null;
    searchQuery.value = '';
    emit('update:modelValue', '');
    traders.value = [];
};

watch(
    () => props.modelValue,
    async (newValue) => {
        if (!newValue) {
            selectedTrader.value = null;
            searchQuery.value = '';
            return;
        }

        if (!selectedTrader.value || String(selectedTrader.value.value) !== String(newValue)) {
            try {
                const response = await axios.get(props.searchRoute, {
                    params: {query: String(newValue)},
                });

                const trader = response.data.find((item) => String(item.value) === String(newValue));
                if (trader) {
                    selectedTrader.value = trader;
                    searchQuery.value = trader.label;
                }
            } catch (error) {
                console.error('Ошибка при загрузке трейдера:', error);
            }
        }
    },
    {immediate: true}
);

const onClickOutside = (event) => {
    if (!event.target.closest('.trader-search-select')) {
        showDropdown.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', onClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', onClickOutside);
    clearTimeout(searchTimeout);
});
</script>

<template>
    <div class="w-full trader-search-select">
        <label class="label p-0">
            <span class="label-text">Трейдер</span>
        </label>
        <div class="form-control relative">
            <input
                type="text"
                class="input input-sm input-bordered w-full"
                placeholder="Введите имя или email..."
                v-model="searchQuery"
                @focus="showDropdown = true"
                @input="showDropdown = true"
            >

            <button
                v-if="selectedTrader"
                type="button"
                class="btn btn-ghost btn-xs absolute right-1 top-1"
                @click="clearSelection"
            >
                x
            </button>

            <span v-if="loading" class="loading loading-spinner loading-sm absolute right-3 top-3"></span>

            <div
                v-if="showDropdown && traders.length > 0"
                class="menu menu-sm bg-base-100 rounded-box absolute z-10 w-full mt-1 shadow"
            >
                <ul>
                    <li
                        v-for="trader in traders"
                        :key="trader.value"
                        @click="selectTrader(trader)"
                    >
                        <a>{{ trader.label }}</a>
                    </li>
                </ul>
            </div>

            <div
                v-if="showDropdown && searchQuery && !loading && traders.length === 0"
                class="alert alert-info shadow mt-1 absolute z-10 w-full"
            >
                <span>Ничего не найдено</span>
            </div>
        </div>
    </div>
</template>
