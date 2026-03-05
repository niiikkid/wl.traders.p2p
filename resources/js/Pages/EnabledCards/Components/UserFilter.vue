<script setup>
import { ref, watch, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
  modelValue: [String, Number],
});

const emit = defineEmits(['update:modelValue']);

const searchQuery = ref('');
const users = ref([]);
const loading = ref(false);
const showDropdown = ref(false);
const selectedUser = ref(null);

// Поиск пользователей
const searchUsers = async () => {
  if (!searchQuery.value.trim() && !props.modelValue) {
    users.value = [];
    return;
  }

  loading.value = true;

  try {
    const response = await axios.get('/admin/filters/users', {
      params: { query: searchQuery.value }
    });

    users.value = response.data;
    showDropdown.value = true;
  } catch (error) {
    console.error('Ошибка при поиске пользователей:', error);
  } finally {
    loading.value = false;
  }
};

// Дебаунс для поиска
let searchTimeout;
watch(searchQuery, (newVal) => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    searchUsers();
  }, 300);
});

// Выбор пользователя из списка
const selectUser = (user) => {
  selectedUser.value = user;
  searchQuery.value = user.label;
  emit('update:modelValue', user.value);
  showDropdown.value = false;
};

// Очистка выбора
const clearSelection = () => {
  selectedUser.value = null;
  searchQuery.value = '';
  emit('update:modelValue', '');
};

// Проверка, если значение приходит из родительского компонента
watch(() => props.modelValue, async (newVal) => {
  if (!newVal) {
    selectedUser.value = null;
    searchQuery.value = '';
    return;
  }

  if (!selectedUser.value || selectedUser.value.value !== newVal) {
    try {
      const response = await axios.get('/admin/filters/users', {
        params: { query: newVal }
      });

      if (response.data.length > 0) {
        const user = response.data.find(u => u.value == newVal);
        if (user) {
          selectedUser.value = user;
          searchQuery.value = user.label;
        }
      }
    } catch (error) {
      console.error('Ошибка при загрузке пользователя:', error);
    }
  }
}, { immediate: true });

// Закрытие выпадающего списка при клике вне компонента
const onClickOutside = (event) => {
  if (!event.target.closest('.user-filter')) {
    showDropdown.value = false;
  }
};

onMounted(() => {
  document.addEventListener('click', onClickOutside);
});

// Загрузка начального значения, если оно есть
onMounted(async () => {
  if (props.modelValue) {
    try {
      const response = await axios.get('/admin/filters/users', {
        params: { query: props.modelValue }
      });

      if (response.data.length > 0) {
        const user = response.data.find(u => u.value == props.modelValue);
        if (user) {
          selectedUser.value = user;
          searchQuery.value = user.label;
        }
      }
    } catch (error) {
      console.error('Ошибка при загрузке пользователя:', error);
    }
  }
});
</script>

<template>
  <div class="w-full md:w-auto user-filter">
    <label for="user-filter" class="label p-0">
      <span class="label-text">Пользователь</span>
    </label>
    <div class="form-control relative">
      <input
        id="user-filter"
        type="text"
        class="input input-sm input-bordered w-full"
        placeholder="Введите имя или email..."
        v-model="searchQuery"
        @focus="showDropdown = true"
        @input="showDropdown = true"
      />

      <button
        v-if="selectedUser"
        type="button"
        class="btn btn-ghost btn-xs absolute right-1 top-1"
        @click="clearSelection"
      >
        ✕
      </button>

      <!-- Индикатор загрузки -->
      <span v-if="loading" class="loading loading-spinner loading-sm absolute right-3 top-3"></span>

      <!-- Выпадающий список результатов -->
      <div
        v-if="showDropdown && users.length > 0"
        class="menu menu-sm bg-base-100 rounded-box absolute z-10 w-full mt-1 shadow"
      >
        <ul>
          <li
            v-for="user in users"
            :key="user.value"
            @click="selectUser(user)"
          >
            <a>{{ user.label }}</a>
          </li>
        </ul>
      </div>

      <!-- Сообщение "Ничего не найдено" -->
      <div
        v-if="showDropdown && searchQuery && !loading && users.length === 0"
        class="alert alert-info shadow mt-1 absolute z-10 w-full"
      >
        <span>Ничего не найдено</span>
      </div>
    </div>
  </div>
</template>
