<script setup>
import {usePage, router} from '@inertiajs/vue3';
import {computed, onMounted, onUnmounted, ref} from 'vue'
import NavBar from "@/Layouts/Partials/NavBar.vue";
import {useViewStore} from "@/store/view.js";
import RoleMenu from "@/Layouts/Partials/Sidebar/RoleMenu.vue";
import RatesCard from "@/Layouts/Partials/Sidebar/RatesCard.vue";
import ImpersonateButton from "@/Layouts/Partials/Sidebar/ImpersonateButton.vue";
import {playNotificationAudio} from "@/utils/notificationAudioPlayer.js";
import PaymentDetailScheduleManagerModal from '@/Modals/PaymentDetailSchedule/PaymentDetailScheduleManagerModal.vue';
import ModalsHost from '@/Components/Modal/ModalsHost.vue';
import AppFooter from '@/Layouts/Partials/AppFooter.vue';

const viewStore = useViewStore();

const rates = ref(usePage().props.data.rates);
const role = usePage().props.auth.role;
const isImpersonated = ref(usePage().props.auth.is_impersonated);
const notificationSoundSettings = ref(usePage().props.notificationsSound ?? null);
const notificationLatestEventIds = ref({
    order_assigned: null,
    dispute_opened: null,
    message_received: null,
});
const isNotificationLatestEventIdsInitialized = ref(false);
const notificationPollInterval = ref(null);
const notificationLeaderHeartbeatInterval = ref(null);
const isNotificationPollingRequestRunning = ref(false);
const notificationTabId = typeof crypto !== 'undefined' && crypto.randomUUID
    ? crypto.randomUUID()
    : `${Date.now()}-${Math.random().toString(16).slice(2)}`;
const NOTIFICATION_SOUND_LEADER_TTL_MS = 8000;
const NOTIFICATION_SOUND_LEADER_HEARTBEAT_MS = 3000;
const NOTIFICATION_POLL_INTERVAL_MS = 5000;

const syncNotificationSoundSettingsFromProps = () => {
    notificationSoundSettings.value = usePage().props.notificationsSound ?? null;
};

const canPollNotifications = computed(() => {
    return usePage().props.auth?.is_trader === true;
});

const getNotificationSoundLeaderStorageKey = () => {
    const userId = usePage().props.auth?.user?.id ?? 'guest';
    return `notifications:sound:leader:${userId}`;
};

const readNotificationSoundLeader = () => {
    try {
        const rawValue = window.localStorage.getItem(getNotificationSoundLeaderStorageKey());

        if (!rawValue) {
            return null;
        }

        const parsedValue = JSON.parse(rawValue);
        if (!parsedValue?.tabId || !parsedValue?.updatedAt) {
            return null;
        }

        return parsedValue;
    } catch (error) {
        return null;
    }
};

const writeNotificationSoundLeader = () => {
    try {
        window.localStorage.setItem(
            getNotificationSoundLeaderStorageKey(),
            JSON.stringify({
                tabId: notificationTabId,
                updatedAt: Date.now(),
            })
        );
    } catch (error) {
        // ignored
    }
};

const removeNotificationSoundLeaderIfOwned = () => {
    try {
        const currentLeader = readNotificationSoundLeader();
        if (currentLeader?.tabId === notificationTabId) {
            window.localStorage.removeItem(getNotificationSoundLeaderStorageKey());
        }
    } catch (error) {
        // ignored
    }
};

const isNotificationSoundLeaderAlive = (leaderData) => {
    if (!leaderData?.updatedAt) {
        return false;
    }

    return Date.now() - Number(leaderData.updatedAt) < NOTIFICATION_SOUND_LEADER_TTL_MS;
};

const isCurrentTabNotificationSoundLeader = () => {
    const currentLeader = readNotificationSoundLeader();
    return currentLeader?.tabId === notificationTabId;
};

const tryAcquireNotificationSoundLeader = ({force = false} = {}) => {
    if (!canPollNotifications.value) {
        return false;
    }

    const currentLeader = readNotificationSoundLeader();
    const shouldAcquire = force
        || !isNotificationSoundLeaderAlive(currentLeader)
        || currentLeader?.tabId === notificationTabId;

    if (!shouldAcquire) {
        return false;
    }

    writeNotificationSoundLeader();

    return true;
};

const startNotificationSoundLeaderHeartbeat = () => {
    stopNotificationSoundLeaderHeartbeat();

    if (!canPollNotifications.value) {
        return;
    }

    tryAcquireNotificationSoundLeader();

    notificationLeaderHeartbeatInterval.value = setInterval(() => {
        if (isCurrentTabNotificationSoundLeader()) {
            writeNotificationSoundLeader();
            return;
        }

        tryAcquireNotificationSoundLeader();
    }, NOTIFICATION_SOUND_LEADER_HEARTBEAT_MS);
};

const stopNotificationSoundLeaderHeartbeat = () => {
    if (notificationLeaderHeartbeatInterval.value) {
        clearInterval(notificationLeaderHeartbeatInterval.value);
        notificationLeaderHeartbeatInterval.value = null;
    }
};

const playNotificationSoundForEvent = (eventKey) => {
    const eventSettings = notificationSoundSettings.value?.[eventKey];
    if (!eventSettings?.enabled || !eventSettings?.track) {
        return;
    }

    if (document.visibilityState !== 'visible') {
        return;
    }

    if (!isCurrentTabNotificationSoundLeader()) {
        return;
    }

    playNotificationAudio(`/audio/${eventSettings.track}`, {interrupt: false});
};

const detectNewestEvent = (latestEventIds) => {
    if (!isNotificationLatestEventIdsInitialized.value) {
        return null;
    }

    const eventPriority = ['message_received', 'dispute_opened', 'order_assigned'];

    for (const eventKey of eventPriority) {
        const previousValue = Number(notificationLatestEventIds.value?.[eventKey] ?? 0);
        const nextValue = Number(latestEventIds?.[eventKey] ?? 0);

        if (nextValue > previousValue) {
            return eventKey;
        }
    }

    return null;
};

const pollNotifications = async () => {
    if (!canPollNotifications.value || isNotificationPollingRequestRunning.value) {
        return;
    }

    isNotificationPollingRequestRunning.value = true;

    try {
        const response = await window.axios.get(route('notifications.ping'));
        const latestEventIds = response?.data?.latest_event_ids ?? {
            order_assigned: 0,
            dispute_opened: 0,
            message_received: 0,
        };

        const newestEvent = detectNewestEvent(latestEventIds);
        notificationLatestEventIds.value = latestEventIds;
        isNotificationLatestEventIdsInitialized.value = true;

        if (newestEvent) {
            playNotificationSoundForEvent(newestEvent);
        }
    } catch (error) {
        // ignored
    } finally {
        isNotificationPollingRequestRunning.value = false;
    }
};

const startNotificationsPolling = () => {
    stopNotificationsPolling();

    if (!canPollNotifications.value) {
        return;
    }

    startNotificationSoundLeaderHeartbeat();
    notificationPollInterval.value = setInterval(pollNotifications, NOTIFICATION_POLL_INTERVAL_MS);
};

const stopNotificationsPolling = () => {
    if (notificationPollInterval.value) {
        clearInterval(notificationPollInterval.value);
        notificationPollInterval.value = null;
    }

    stopNotificationSoundLeaderHeartbeat();
};

const handleNotificationVisibilityChange = () => {
    if (!canPollNotifications.value) {
        return;
    }

    if (document.visibilityState === 'visible') {
        tryAcquireNotificationSoundLeader({force: true});
        return;
    }

    removeNotificationSoundLeaderIfOwned();
};

const handleNotificationBeforeUnload = () => {
    removeNotificationSoundLeaderIfOwned();
};

const roleToMode = (roleName) => {
    if (roleName === 'Super Admin') {
        return 'admin';
    }
    if (roleName === 'Merchant') {
        return 'merchant';
    }
    if (roleName === 'Trader') {
        return 'trader';
    }
    if (roleName === 'Team Leader') {
        return 'leader';
    }
    if (roleName === 'Support') {
        return 'support';
    }
    return 'trader';
};

const setViewMode = (mode) => {
    if (mode === 'admin') {
        viewStore.setAdminViewMode();
        return;
    }
    if (mode === 'merchant') {
        viewStore.setMerchantViewMode();
        return;
    }
    if (mode === 'trader') {
        viewStore.setTraderViewMode();
        return;
    }
    if (mode === 'leader') {
        viewStore.setTeamLeaderViewMode();
        return;
    }
    if (mode === 'support') {
        viewStore.setSupportViewMode();
        return;
    }
    viewStore.setTraderViewMode();
};

const resolveViewMode = () => {
    if (route().current('trader.*')
        || route().current('notifications.*')
        || route().current('payment-details.*')
        || route().current('orders.*')
        || route().current('disputes.*')
        || route().current('wallet.*')
        || route().current('sms-logs.*')
    ) {
        return 'trader';
    }

    if (route().current('admin.*')) {
        return 'admin';
    }

    if (route().current('leader.*')) {
        return 'leader';
    }

    if (route().current('support.*')) {
        return 'support';
    }

    if (
        route().current('merchant.*')
        || route().current('merchants.*')
        || route().current('integration.*')
        || route().current('payments.*')
    ) {
        return 'merchant';
    }

    if (role?.name === 'Super Admin' && viewStore.viewMode) {
        return viewStore.viewMode;
    }

    return roleToMode(role?.name);
};

const applyViewMode = () => {
    setViewMode(resolveViewMode());
};

// initialize components based on data attribute selectors
onMounted(() => {
    applyViewMode();
    syncNotificationSoundSettingsFromProps();
    startNotificationsPolling();
    window.addEventListener('visibilitychange', handleNotificationVisibilityChange);
    window.addEventListener('beforeunload', handleNotificationBeforeUnload);
})

const getMobileDrawer = () => document.getElementById('mobile-drawer');

const toggleSidebar = () => {
    const drawer = getMobileDrawer();
    if (drawer instanceof HTMLInputElement) {
        drawer.checked = !drawer.checked;
    }
}

const closeMobileDrawer = () => {
    const drawer = getMobileDrawer();
    if (drawer instanceof HTMLInputElement) {
        drawer.checked = false;
    }
}

router.on('success', (event) => {
    applyViewMode();
    rates.value = usePage().props.data.rates;
    isImpersonated.value = usePage().props.auth.is_impersonated;
    syncNotificationSoundSettingsFromProps();
    startNotificationsPolling();
    closeMobileDrawer();
})

onUnmounted(() => {
    stopNotificationsPolling();
    window.removeEventListener('visibilitychange', handleNotificationVisibilityChange);
    window.removeEventListener('beforeunload', handleNotificationBeforeUnload);
    removeNotificationSoundLeaderIfOwned();
});

</script>

<template>
    <div>
        <div class="drawer bg-base-200">
        <!-- Mobile drawer toggle -->
        <input id="mobile-drawer" type="checkbox" class="drawer-toggle" />

        <!-- Mobile drawer side (daisyUI structure) -->
        <div class="drawer-side lg:hidden">
            <label for="mobile-drawer" class="drawer-overlay"></label>
            <aside class="min-h-full w-75 sm:w-80 bg-base-100">
<!--                <div class="p-7 pb-0">
                    <div class="text-4xl font-semibold">{{$page.props.app.name}}</div>
                    <div class="text-xs font-medium text-base-content/70">Надежный процессинг</div>
                </div>-->
                <div class="h-20"></div>

                <div class="p-4 space-y-4">
                    <ImpersonateButton v-if="isImpersonated" />
                    <div class="card bg-base-100">
                        <div>
                            <RoleMenu />
                        </div>
                    </div>

                    <RatesCard :rates="rates" variant="mobile" />
                </div>
            </aside>
        </div>

        <!-- Main content -->
        <div class="drawer-content flex flex-col min-h-screen space-y-1">
            <div class="z-50">
                <!-- Navbar -->
                <NavBar @toggle-sidebar="toggleSidebar"/>
            </div>

            <!-- Page content -->
            <div class="container mx-auto px-4 pb-6 pt-1 flex-1">
                <div class="flex gap-6">
                    <!-- Desktop sidebar -->
                    <aside class="hidden lg:block space-y-4 pt-2 w-60" aria-label="Sidebar">
                        <ImpersonateButton v-if="isImpersonated" />
                        <div class="card bg-base-100  shadow w-60">
                            <RoleMenu />
                        </div>

                        <RatesCard :rates="rates" variant="desktop" />
                    </aside>

                    <!-- Main content area -->
                    <main class="w-full lg:w-[calc(100%_-_17.5rem)] pt-2">
                        <slot />
                    </main>
                </div>
            </div>

            <AppFooter />
        </div>
        </div>

        <PaymentDetailScheduleManagerModal v-if="viewStore.isTraderViewMode" />
        <ModalsHost />
    </div>
</template>
