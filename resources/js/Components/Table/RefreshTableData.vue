<script setup>
import {onMounted, ref} from "vue";
import {router, usePoll} from "@inertiajs/vue3";
import {useTableFiltersStore} from "@/store/tableFilters.js";

const tableFiltersStore = useTableFiltersStore();

const intervals = ref([
    {name:'Не обновлять', value:0},
    {name:'Каждые 15с', value:15000},
    {name:'Каждые 30с', value:30000},
    {name:'Каждые 60с', value:60000},
]);

const emit = defineEmits(['refreshStarted', 'refreshFinished']);
const storageKey = `refresh-storage-orders`;
let interval = localStorage.getItem(storageKey) ? localStorage.getItem(storageKey) : 0
if (parseInt(interval, 10) === 5000) {
    interval = 10000;
}
if (parseInt(interval, 10) === 10000) {
    interval = 15000;
}
if (parseInt(interval, 10) === 20000) {
    interval = 30000;
}

const refreshInterval = ref(interval);

const { start, stop } = usePoll(refreshInterval.value, {
        onStart() {
            emit('refreshStarted');
            animateProgress(100, refreshInterval.value);
        },
        async onFinish() {
            await new Promise(resolve => setTimeout(resolve, 2500));
            emit('refreshFinished');
        }
    }, {keepAlive: true, autoStart: false}
);

const offset = ref(100); // Начальное значение stroke-dashoffset

function animateProgress(targetPercent, duration = 1000) {
    const startOffset = 100; // Начальное значение
    const targetOffset = 100 - targetPercent; // Конечное значение
    const startTime = performance.now();

    function step(currentTime) {
        const elapsedTime = currentTime - startTime;
        const progress = Math.min(elapsedTime / duration, 1); // Прогресс анимации (от 0 до 1)
        offset.value = startOffset - (startOffset - targetOffset) * progress;

        if (progress < 1) {
            requestAnimationFrame(step); // Продолжаем анимацию
        }
    }

    requestAnimationFrame(step);
}

onMounted(() => {
    if (refreshInterval.value > 0) {
        start();
        animateProgress(100, refreshInterval.value);
    } else {
        stop();
    }
})

const storeRefreshInterval = () => {
    localStorage.setItem(storageKey, refreshInterval.value);
}

const reloadPage = () => {
    storeRefreshInterval();

    router.visit(route(route().current()), {
        data: tableFiltersStore.getQueryData,
        preserveScroll: true
    });
}


</script>

<template>
    <div class="flex items-center gap-3">
        <div v-show="refreshInterval > 0" class="flex justify-center items-center">
            <div class="relative w-5 h-5">
                <!-- Фоновый круг -->
                <svg class="w-full h-full" viewBox="0 0 36 36">
                    <path
                        class="text-base-300"
                        d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="4"
                    />
                </svg>
                <!-- Прогресс -->
                <svg class="absolute top-0 left-0 w-full h-full" viewBox="0 0 36 36">
                    <path
                        class="text-primary"
                        :style="{ strokeDashoffset: offset }"
                        d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="4"
                        stroke-dasharray="100, 100"
                    />
                </svg>
            </div>
        </div>

        <div class="dropdown dropdown-end">
            <div tabindex="0" role="button" class="btn btn-outline btn-xs">
                {{ intervals.find(i => i.value == refreshInterval)?.name || 'Интервал обновления' }}
                <svg class="w-2.5 h-2.5 ms-2" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 10 6">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 4 4 4-4"/>
                </svg>
            </div>
            <ul tabindex="0" class="dropdown-content menu p-2 shadow bg-base-100 rounded-box w-52">
                <li v-for="option in intervals" :key="option.value">
                    <a @click.prevent="refreshInterval = option.value; reloadPage();">{{ option.name }}</a>
                </li>
            </ul>
        </div>
    </div>
</template>

<style scoped>

</style>
