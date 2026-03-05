<script setup>
import {ref, watch} from "vue";

const emit = defineEmits(['expired']);

const props = defineProps({
    expires_at: {
        type: String,
    },
    now: {
        type: String,
    },
});

const clock = ref( {
    days: "0",
    hours: "0",
    minutes: "0",
    seconds: "0",
    now: null,
});

watch(
    () => props.now,
    () => {
        clock.value.now = new Date(props.now);
    }
);

const initializeClock = () => {
    let endtime = new Date(props.expires_at);
    clock.value.now = new Date(props.now);

    function updateClock() {
        clock.value.now = new Date(Date.parse(clock.value.now) + 1000);
        var t = getTimeRemaining(endtime, clock.value.now);

        clock.value.days = t.days;
        clock.value.hours = ('0' + t.hours).slice(-2);
        clock.value.minutes = ('0' + t.minutes).slice(-2);
        clock.value.seconds = ('0' + t.seconds).slice(-2);

        if (t.total <= 0) {
            clearInterval(timeinterval);
            clock.value.days = '00';
            clock.value.hours = '00';
            clock.value.minutes = '00';
            clock.value.seconds = '00';
        } else {
            clock.value.days = t.days;
            clock.value.hours = ('0' + t.hours).slice(-2);
            clock.value.minutes = ('0' + t.minutes).slice(-2);
            clock.value.seconds = ('0' + t.seconds).slice(-2);
        }
        /*if (t.total <= 0) {
            expired();
        }*/
    }

    updateClock();
    var timeinterval = setInterval(updateClock, 1000);
}

const getTimeRemaining = (endtime, now) => {
    var t = Date.parse(endtime) - Date.parse(now);
    var seconds = Math.floor((t / 1000) % 60);
    var minutes = Math.floor((t / 1000 / 60) % 60);
    var hours = Math.floor((t / (1000 * 60 * 60)) % 24);
    var days = Math.floor(t / (1000 * 60 * 60 * 24));

    return {
        'total': t,
        'days': days,
        'hours': hours,
        'minutes': minutes,
        'seconds': seconds
    };
}

const expired = () => {
    emit('expired');
};

defineExpose({
    initializeClock
});
</script>

<template>
    <span>
        {{ clock.minutes }}:{{ clock.seconds }}
    </span>
</template>

<style scoped>

</style>
