<script setup>
import {computed, nextTick, onMounted, onUnmounted, ref} from "vue";
import DateTime from "@/Components/DateTime.vue";
import { useAppClipboard } from '@/composables/useAppClipboard.js';
import { useViewStore } from '@/store/view.js';

const viewStore = useViewStore();

const props = defineProps({
    user: {
        type: Object,
        required: true,
    },
});

const isOpen = ref(false);
const trigger = ref(null);
const popover = ref(null);
const position = ref({ top: 0, left: 0 });
const boolToText = (value) => value ? 'Да' : 'Нет';
const telegramTag = computed(() => props.user.telegram_tag || null);
const { copy, copied } = useAppClipboard();

const updatePosition = () => {
    if (!trigger.value) {
        return;
    }

    const rect = trigger.value.getBoundingClientRect();
    const popoverWidth = 416; // 26rem
    const viewportPadding = 8;
    const maxLeft = window.innerWidth - popoverWidth - viewportPadding;
    const left = Math.min(Math.max(rect.left, viewportPadding), Math.max(maxLeft, viewportPadding));

    position.value = {
        top: rect.bottom + 8,
        left,
    };
};

const open = async () => {
    isOpen.value = true;
    await nextTick();
    updatePosition();
};

const close = () => {
    isOpen.value = false;
};

const toggle = async () => {
    if (isOpen.value) {
        close();
        return;
    }

    await open();
};

const onDocumentClick = (event) => {
    if (!isOpen.value) {
        return;
    }

    if (trigger.value?.contains(event.target) || popover.value?.contains(event.target)) {
        return;
    }

    close();
};

const onEscape = (event) => {
    if (event.key === 'Escape' && isOpen.value) {
        close();
    }
};

const onViewportChange = () => {
    if (!isOpen.value) {
        return;
    }

    updatePosition();
};

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onEscape);
    window.addEventListener('resize', onViewportChange);
    window.addEventListener('scroll', onViewportChange, true);
});

onUnmounted(() => {
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onEscape);
    window.removeEventListener('resize', onViewportChange);
    window.removeEventListener('scroll', onViewportChange, true);
});
</script>

<template>
    <div class="inline-block">
        <div
            ref="trigger"
            tabindex="0"
            role="button"
            class="cursor-pointer"
            @click.stop="toggle"
            @keydown.enter.prevent="toggle"
            @keydown.space.prevent="toggle"
        >
            <slot />
        </div>

        <teleport to="body">
            <div
                v-if="isOpen"
                ref="popover"
                class="fixed z-[80] w-[26rem] max-w-[calc(100vw-1rem)] max-h-[70vh] overflow-y-auto rounded-box border border-base-300 bg-base-100 shadow-xl p-3"
                :style="{ top: `${position.top}px`, left: `${position.left}px` }"
                @click.stop
            >
                <div class="flex items-center gap-3">
                    <img :src="'https://api.dicebear.com/9.x/'+props.user.avatar_style+'/svg?seed='+props.user.avatar_uuid" class="w-10 h-10 rounded-full" alt="user photo">
                    <div class="min-w-0">
                        <div class="font-medium truncate">{{ props.user.email }}</div>
                        <div class="text-xs text-base-content/70">{{ props.user.role?.name || '—' }}</div>
                    </div>
                    <div class="ml-auto flex flex-col items-center">
                        <div>
                            <span class="badge badge-soft badge-primary">{{ props.user.user_team?.name || '—' }}</span>
                        </div>
                        <div class="mt-1 text-xs">
                            <div v-if="telegramTag" class="flex items-center justify-center min-w-[96px]">
                                <button
                                    type="button"
                                    class="link link-primary"
                                    :class="{ 'text-success': copied }"
                                    :title="copied ? 'Скопировано' : 'Нажмите, чтобы скопировать'"
                                    @click="copy(telegramTag)"
                                >
                                    {{ copied ? 'Скопировано' : telegramTag }}
                                </button>
                            </div>
                            <span v-else class="text-base-content/60">—</span>
                        </div>
                    </div>
                </div>

                <div class="divider my-2"></div>

                <div class="grid grid-cols-2 gap-x-3 gap-y-1 text-xs">
                    <div class="text-base-content/70">ID</div>
                    <div class="font-medium text-right">{{ props.user.id }}</div>

                    <div class="text-base-content/70">Баланс</div>
                    <div class="font-medium text-right">{{ props.user.balance }} $</div>

                    <div class="text-base-content/70">Онлайн</div>
                    <div class="font-medium text-right">{{ boolToText(!!props.user.is_online) }}</div>

                    <div class="text-base-content/70">VIP</div>
                    <div class="font-medium text-right">{{ boolToText(!!props.user.is_vip) }}</div>

                    <div class="text-base-content/70">Трафик</div>
                    <div class="font-medium text-right">{{ boolToText(!!props.user.stop_traffic) }}</div>

                    <div class="text-base-content/70">Без устройства</div>
                    <div class="font-medium text-right">{{ boolToText(!!props.user.can_work_without_device) }}</div>

                    <div class="text-base-content/70">Выплаты включены</div>
                    <div class="font-medium text-right">{{ boolToText(!!props.user.payouts_enabled) }}</div>

                    <div class="text-base-content/70">Холд включен</div>
                    <div class="font-medium text-right">{{ boolToText(!!props.user.payout_hold_enabled) }}</div>

                    <div class="text-base-content/70">Холд (мин)</div>
                    <div class="font-medium text-right">{{ props.user.payout_hold_minutes ?? 0 }}</div>

                    <div class="text-base-content/70">Лимит активных выплат</div>
                    <div class="font-medium text-right">{{ props.user.payout_active_payouts_limit ?? 1 }}</div>

                    <div class="text-base-content/70">Создан</div>
                    <div class="font-medium text-right">
                        <DateTime :data="props.user.created_at" :simple="true" />
                    </div>

                    <div class="text-base-content/70">Блокировка</div>
                    <div class="font-medium text-right">
                        <DateTime v-if="props.user.banned_at" :data="props.user.banned_at" :simple="true" />
                        <span v-else>—</span>
                    </div>

                    <template v-if="viewStore.isAdminViewMode && props.user.banned_at && props.user.banned_by?.email">
                        <div class="text-base-content/70">Заблокировал</div>
                        <div class="font-medium text-right truncate" :title="props.user.banned_by.email">
                            {{ props.user.banned_by.email }}
                        </div>
                    </template>

                    <div class="text-base-content/70">Архив</div>
                    <div class="font-medium text-right">
                        <DateTime v-if="props.user.archived_at" :data="props.user.archived_at" :simple="true" />
                        <span v-else>—</span>
                    </div>
                </div>

                <div
                    v-if="viewStore.isAdminViewMode && props.user.banned_at && props.user.ban_reason"
                    class="mt-3 rounded-box border border-error/30 bg-error/5 p-3 text-xs"
                >
                    <div class="text-base-content/70 mb-1">Причина блокировки</div>
                    <div class="whitespace-pre-wrap break-words">{{ props.user.ban_reason }}</div>
                </div>
            </div>
        </teleport>
    </div>
</template>
