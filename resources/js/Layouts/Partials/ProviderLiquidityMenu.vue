<script setup>
import {Link} from '@inertiajs/vue3';
</script>

<template>
    <ul class="menu p-2 gap-1">
        <li><Link :href="route('provider-liquidity.main.index')">Главная</Link></li>
        <li><Link :href="route('provider-liquidity.services.index')">Сервисы</Link></li>
        <li><Link :href="route('provider-liquidity.deals.index')">Сделки</Link></li>
        <li><Link :href="route('provider-liquidity.wallet.index')">Кошелёк</Link></li>
        <li><Link :href="route('provider-liquidity.logs.index')">Логи</Link></li>
    </ul>
</template>
