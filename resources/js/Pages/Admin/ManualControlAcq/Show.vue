<script setup>
import axios from 'axios';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import ManualControlConfirmModal from './ManualControlConfirmModal.vue';
import ManualControlLayout from '@/Layouts/ManualControlLayout.vue';
import { useModalStore } from '@/store/modal.js';
import { playNotificationAudio } from '@/utils/notificationAudioPlayer.js';
import AppTooltip from '@/Components/AppTooltip.vue';

const modal_store = useModalStore();

const confirmationCol1 = [
    { value: 'otp_code', title: 'OTP code' },
    { value: 'in_app_confirmation', title: 'In-app confirmation' },
    { value: 'bank_call', title: 'Bank call' },
];

const confirmationCol2 = [
    { value: 'otp_code_and_pin_code', title: 'OTP code and PIN code' },
    { value: 'sms_with_instructions', title: 'SMS with instructions' },
];

const processingRingRadius = 42;
const processingRingCircumference = 2 * Math.PI * processingRingRadius;
const HISTORY_DISPLAY_LIMIT = 5;
const POLL_INTERVAL_MS = 3000;
const DEFAULT_NEW_OFFER_SOUND_TRACK = 'radwimps.mp3';
const DEFAULT_CONFIRM_CODE_SOUND_TRACK = 'LetWealthCome.mp3';
const rejectReasons = [
    'Ошибка обработки',
    'Недостаточно средств',
    'Недействительные реквизиты карты',
    'Превышен лимит карты',
    'Подозрение на мошенничество',
    'Отменено плательщиком',
];

const page_props = usePage().props;
const audio_tracks = ref(page_props.audioTracks ?? []);
const manual_control_route_prefix = computed(() => {
    if (route().current('support.*')) {
        return 'support';
    }

    return 'admin';
});
const manual_control_route_name = (action) => `${manual_control_route_prefix.value}.manual-control-acq.${action}`;

const resolve_default_sound_track = (track, fallback_track) => {
    const allowed_tracks = audio_tracks.value.map((item) => item.value);

    if (track && allowed_tracks.includes(track)) {
        return track;
    }

    if (fallback_track && allowed_tracks.includes(fallback_track)) {
        return fallback_track;
    }

    return audio_tracks.value[0]?.value ?? null;
};

const normalize_sound_settings = (settings) => {
    const next_settings = settings ?? {};
    const new_offer = next_settings.new_offer ?? {};
    const confirm_code = next_settings.confirm_code ?? {};

    return {
        new_offer: {
            enabled: Boolean(new_offer.enabled ?? true),
            track: resolve_default_sound_track(new_offer.track ?? null, DEFAULT_NEW_OFFER_SOUND_TRACK),
        },
        confirm_code: {
            enabled: Boolean(confirm_code.enabled ?? true),
            track: resolve_default_sound_track(confirm_code.track ?? null, DEFAULT_CONFIRM_CODE_SOUND_TRACK),
        },
    };
};

const sound_settings = ref(normalize_sound_settings(page_props.soundSettings));

const incoming_offer_preview = ref(null);
const incoming_queue_waiting_count = ref(0);
const pay_in_queue_active = ref([]);
const pay_in_queue_history_visible = ref([]);
const history_total_count = ref(0);
const selected_item_id = ref(null);
const copiedField = ref('');
const copied_confirmation_history_key = ref('');
const notification_settings_dialog = ref(null);
const reject_reason_dialog = ref(null);
const selected_reject_reason = ref('');
const notification_sound_new_offer_enabled = ref(sound_settings.value.new_offer.enabled);
const notification_sound_new_offer_preset = ref(sound_settings.value.new_offer.track);
const notification_sound_confirm_code_enabled = ref(sound_settings.value.confirm_code.enabled);
const notification_sound_confirm_code_preset = ref(sound_settings.value.confirm_code.track);
const is_state_loading = ref(false);
const is_take_processing = ref(false);
const is_reject_processing = ref(false);
const is_confirmation_type_processing = ref(false);
const is_confirm_processing = ref(false);
const is_working = ref(false);
const is_work_toggle_processing = ref(false);
const is_sound_settings_processing = ref(false);
const is_initial_state_loaded = ref(false);
const action_error_message = ref('');

let timerInterval = null;
let statePollInterval = null;
let copiedFieldTimeout = null;
let copied_confirmation_history_timeout = null;
let stateRequestSerial = 0;

const pay_in_queue_all = computed(() => [...pay_in_queue_active.value, ...pay_in_queue_history_visible.value]);
const incoming_offer_visible = computed(() => Boolean(incoming_offer_preview.value));
const history_hidden_count = computed(() => Math.max(0, history_total_count.value - HISTORY_DISPLAY_LIMIT));
const selected_item = computed(() => pay_in_queue_all.value.find((item) => item.id === selected_item_id.value) ?? null);
const active_queue_items = computed(() => pay_in_queue_active.value);
const history_queue_items = computed(() => pay_in_queue_history_visible.value);
const is_selected_history = computed(() => selected_item.value?.is_history === true);
const notification_sound_options = computed(() => {
    return audio_tracks.value.map((item) => ({
        ...item,
        label: String(item.name ?? '')
            .replace(/\.mp3$/i, ''),
    }));
});

const resolve_track_url = (track_name) => {
    if (!track_name) {
        return null;
    }

    return audio_tracks.value.find((item) => item.value === track_name)?.url ?? null;
};

const current_sound_settings_payload = () => {
    return {
        new_offer: {
            enabled: notification_sound_new_offer_enabled.value,
            track: notification_sound_new_offer_preset.value,
        },
        confirm_code: {
            enabled: notification_sound_confirm_code_enabled.value,
            track: notification_sound_confirm_code_preset.value,
        },
    };
};

const apply_sound_settings = (settings) => {
    sound_settings.value = normalize_sound_settings(settings);
    notification_sound_new_offer_enabled.value = sound_settings.value.new_offer.enabled;
    notification_sound_new_offer_preset.value = sound_settings.value.new_offer.track;
    notification_sound_confirm_code_enabled.value = sound_settings.value.confirm_code.enabled;
    notification_sound_confirm_code_preset.value = sound_settings.value.confirm_code.track;
};

const save_sound_settings = async () => {
    if (is_sound_settings_processing.value) {
        return;
    }

    is_sound_settings_processing.value = true;

    try {
        const response = await axios.patch(
            route(manual_control_route_name('sound-settings.update')),
            current_sound_settings_payload(),
        );

        const next_sound_settings = response?.data?.data?.sound_settings ?? current_sound_settings_payload();
        apply_sound_settings(next_sound_settings);
        action_error_message.value = '';
    } catch (error) {
        action_error_message.value = error?.response?.data?.message ?? 'Не удалось сохранить настройки звука.';
        apply_sound_settings(sound_settings.value);
    } finally {
        is_sound_settings_processing.value = false;
    }
};

const toggle_new_offer_sound_enabled = async () => {
    await save_sound_settings();
};

const toggle_confirm_code_sound_enabled = async () => {
    await save_sound_settings();
};

const select_new_offer_sound = async () => {
    await save_sound_settings();
};

const select_confirm_code_sound = async () => {
    await save_sound_settings();
};

const prepare_modal_open = () => {
    try {
        const selection = window.getSelection?.();
        if (selection && selection.rangeCount > 0) {
            selection.removeAllRanges();
        }
    } catch (error) {
        // ignored
    }

    const active_element = document.activeElement;
    if (active_element && typeof active_element.blur === 'function') {
        active_element.blur();
    }
};

const open_notification_settings_modal = () => {
    prepare_modal_open();
    notification_settings_dialog.value?.showModal();
};

const close_notification_settings_modal = () => {
    notification_settings_dialog.value?.close();
};

const open_reject_reason_modal = () => {
    selected_reject_reason.value = '';
    prepare_modal_open();
    reject_reason_dialog.value?.showModal();
};

const close_reject_reason_modal = () => {
    reject_reason_dialog.value?.close();
    selected_reject_reason.value = '';
};

const pick_reject_reason = (reason) => {
    selected_reject_reason.value = reason;
};

const format_mm_ss = (total_seconds) => {
    const normalized_seconds = Math.max(0, Number(total_seconds) || 0);
    const minutes = Math.floor(normalized_seconds / 60);
    const seconds = normalized_seconds % 60;

    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
};

const format_date_time = (timestamp) => {
    const normalized_ts = Number(timestamp);

    if (!Number.isFinite(normalized_ts) || normalized_ts <= 0) {
        return '—';
    }

    return new Date(normalized_ts * 1000).toLocaleString('ru-RU');
};

const card_tail_label = (display) => {
    const digits = String(display).replace(/\D/g, '');

    if (digits.length >= 4) {
        return `•••• ${digits.slice(-4)}`;
    }

    return display;
};

const processingTime = computed(() => {
    const seconds = selected_item.value?.processing_elapsed_seconds ?? 0;

    return format_mm_ss(seconds);
});

const processingProgress = computed(() => {
    const item = selected_item.value;

    if (!item) {
        return 0;
    }

    const total_seconds = Math.max(1, Number(item.processing_total_seconds) || 1);
    const elapsed_seconds = Math.max(0, Number(item.processing_elapsed_seconds) || 0);

    return Math.min(elapsed_seconds / total_seconds, 1);
});

const processingRingDashoffset = computed(() => {
    return processingRingCircumference * (1 - processingProgress.value);
});

const incomingProcessingTime = computed(() => {
    const seconds = incoming_offer_preview.value?.processing_elapsed_seconds ?? 0;

    return format_mm_ss(seconds);
});

const incomingProcessingProgress = computed(() => {
    const item = incoming_offer_preview.value;

    if (!item) {
        return 0;
    }

    const total_seconds = Math.max(1, Number(item.processing_total_seconds) || 1);
    const elapsed_seconds = Math.max(0, Number(item.processing_elapsed_seconds) || 0);

    return Math.min(elapsed_seconds / total_seconds, 1);
});

const incomingProcessingRingDashoffset = computed(() => {
    return processingRingCircumference * (1 - incomingProcessingProgress.value);
});

const processing_progress_ratio_for_item = (item) => {
    if (!item) {
        return 0;
    }

    const total_seconds = Math.max(1, Number(item.processing_total_seconds) || 1);
    const elapsed_seconds = Math.max(0, Number(item.processing_elapsed_seconds) || 0);

    return Math.min(elapsed_seconds / total_seconds, 1);
};

const processing_ring_dashoffset_for_item = (item) => {
    return processingRingCircumference * (1 - processing_progress_ratio_for_item(item));
};

const canConfirm = computed(() => {
    const item = selected_item.value;

    return Boolean(
        item
        && !item.is_history
        && item.pending_confirmation_title
        && !is_confirm_processing.value,
    );
});

const can_take_incoming_offer = computed(() => {
    return Boolean(is_working.value && incoming_offer_preview.value && !is_take_processing.value);
});

const selected_order_query_param = 'order';

const item_id_matches = (item, raw_id) => {
    if (raw_id === null || raw_id === undefined || raw_id === '') {
        return false;
    }

    return item.id === raw_id || String(item.id) === String(raw_id);
};

const parse_order_query_param_value = (raw) => {
    if (raw == null || raw === '') {
        return null;
    }

    const trimmed = String(raw).trim();
    const as_num = Number(trimmed);

    if (Number.isFinite(as_num) && String(as_num) === trimmed) {
        return as_num;
    }

    return trimmed;
};

const read_preferred_order_id_from_url = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    const params = new URLSearchParams(window.location.search);

    return parse_order_query_param_value(params.get(selected_order_query_param));
};

const replace_selected_order_in_url = (order_id) => {
    if (typeof window === 'undefined') {
        return;
    }

    const url = new URL(window.location.href);

    if (order_id == null) {
        url.searchParams.delete(selected_order_query_param);
    } else {
        url.searchParams.set(selected_order_query_param, String(order_id));
    }

    const next = `${url.pathname}${url.search}${url.hash}`;

    window.history.replaceState(window.history.state, '', next);
};

const sync_selected_item = () => {
    const all = pay_in_queue_all.value;
    const preferred_url = read_preferred_order_id_from_url();

    const resolve_canonical_id = (raw_id) => {
        const found = all.find((item) => item_id_matches(item, raw_id));

        return found ? found.id : null;
    };

    if (selected_item_id.value != null) {
        const canonical = resolve_canonical_id(selected_item_id.value);

        if (canonical != null) {
            selected_item_id.value = canonical;
            replace_selected_order_in_url(selected_item_id.value);

            return;
        }
    }

    if (preferred_url != null) {
        const canonical = resolve_canonical_id(preferred_url);

        if (canonical != null) {
            selected_item_id.value = canonical;
            replace_selected_order_in_url(selected_item_id.value);

            return;
        }
    }

    const first_active = pay_in_queue_active.value[0] ?? null;
    const first_history = pay_in_queue_history_visible.value[0] ?? null;

    selected_item_id.value = first_active?.id ?? first_history?.id ?? null;
    replace_selected_order_in_url(selected_item_id.value);
};

const normalize_processing_item = (item, previous_item = null) => {
    const created_ts = Number(item.processing_created_at_ts);
    const finished_ts = Number(item.processing_finished_at_ts);
    const expires_ts = Number(item.processing_expires_at_ts);
    const end_ts = Number(item.processing_end_at_ts);
    const is_history_item = Boolean(item.is_history);
    const resolved_end_ts = Number.isFinite(end_ts) && end_ts > 0
        ? end_ts
        : (is_history_item && Number.isFinite(finished_ts) && finished_ts > 0
            ? finished_ts
            : expires_ts);
    let elapsed_from_server_time = Number(item.processing_elapsed_seconds) || 0;

    if (Number.isFinite(created_ts) && created_ts > 0) {
        const elapsed_source_ts = (is_history_item && Number.isFinite(resolved_end_ts) && resolved_end_ts > 0)
            ? resolved_end_ts
            : Math.floor(Date.now() / 1000);
        elapsed_from_server_time = Math.max(0, elapsed_source_ts - created_ts);
    }

    let total_from_server_time = Number(item.processing_total_seconds) || 1;
    if (Number.isFinite(created_ts) && Number.isFinite(expires_ts) && expires_ts > created_ts) {
        total_from_server_time = expires_ts - created_ts;
    } else if (
        !is_history_item
        && Number.isFinite(created_ts)
        && Number.isFinite(resolved_end_ts)
        && resolved_end_ts > created_ts
    ) {
        total_from_server_time = resolved_end_ts - created_ts;
    }

    const normalized_total = Math.max(1, total_from_server_time);
    const normalized_elapsed = Math.min(
        normalized_total,
        Math.max(
            elapsed_from_server_time,
            Number(previous_item?.processing_elapsed_seconds) || 0,
        ),
    );

    return {
        ...item,
        processing_total_seconds: normalized_total,
        processing_elapsed_seconds: normalized_elapsed,
    };
};

const stop_workspace_timers = () => {
    if (timerInterval !== null) {
        window.clearInterval(timerInterval);
        timerInterval = null;
    }
};

const stop_state_polling = () => {
    if (statePollInterval !== null) {
        window.clearInterval(statePollInterval);
        statePollInterval = null;
    }
};

const start_workspace_timers = () => {
    const has_runtime_items = pay_in_queue_active.value.length > 0 || Boolean(incoming_offer_preview.value);

    if (timerInterval !== null || !has_runtime_items) {
        return;
    }

    timerInterval = window.setInterval(() => {
        pay_in_queue_active.value.forEach((item) => {
            const created_ts = Number(item.processing_created_at_ts);
            const expires_ts = Number(item.processing_expires_at_ts);
            const now_ts = Math.floor(Date.now() / 1000);

            let total_seconds = Math.max(1, Number(item.processing_total_seconds) || 1);
            if (Number.isFinite(created_ts) && Number.isFinite(expires_ts) && expires_ts > created_ts) {
                total_seconds = expires_ts - created_ts;
                item.processing_total_seconds = total_seconds;
            }

            if (Number.isFinite(created_ts) && created_ts > 0) {
                item.processing_elapsed_seconds = Math.min(
                    total_seconds,
                    Math.max(0, now_ts - created_ts),
                );
            } else {
                item.processing_elapsed_seconds = Math.min(
                    total_seconds,
                    (Number(item.processing_elapsed_seconds) || 0) + 1,
                );
            }

        });

        const incoming_offer = incoming_offer_preview.value;
        if (incoming_offer) {
            const created_ts = Number(incoming_offer.processing_created_at_ts);
            const expires_ts = Number(incoming_offer.processing_expires_at_ts);
            const now_ts = Math.floor(Date.now() / 1000);

            let total_seconds = Math.max(1, Number(incoming_offer.processing_total_seconds) || 1);
            if (Number.isFinite(created_ts) && Number.isFinite(expires_ts) && expires_ts > created_ts) {
                total_seconds = expires_ts - created_ts;
                incoming_offer.processing_total_seconds = total_seconds;
            }

            if (Number.isFinite(created_ts) && created_ts > 0) {
                incoming_offer.processing_elapsed_seconds = Math.min(
                    total_seconds,
                    Math.max(0, now_ts - created_ts),
                );
            } else {
                incoming_offer.processing_elapsed_seconds = Math.min(
                    total_seconds,
                    (Number(incoming_offer.processing_elapsed_seconds) || 0) + 1,
                );
            }
        }
    }, 1000);
};

const start_state_polling = () => {
    if (statePollInterval !== null) {
        return;
    }

    statePollInterval = window.setInterval(() => {
        load_state();
    }, POLL_INTERVAL_MS);
};

const sync_runtime_activity_by_work_status = () => {
    const has_runtime_items = pay_in_queue_active.value.length > 0 || Boolean(incoming_offer_preview.value);
    start_state_polling();

    if (has_runtime_items) {
        start_workspace_timers();
        return;
    }

    stop_workspace_timers();
};

const latest_confirmation_code_key = (item) => {
    const latest_code = item?.confirmation_codes?.[0] ?? item?.confirmation_code ?? null;

    if (!latest_code?.display) {
        return null;
    }

    return `${latest_code.display}|${latest_code.created_at_ts ?? ''}`;
};

const make_active_confirmation_code_snapshot = (items) => {
    return new Map(
        (items ?? []).map((item) => [String(item.id), latest_confirmation_code_key(item)]),
    );
};

const play_new_offer_sound_if_needed = (has_new_incoming_offer) => {
    if (!has_new_incoming_offer || !notification_sound_new_offer_enabled.value) {
        return;
    }

    const sound_url = resolve_track_url(notification_sound_new_offer_preset.value);
    if (!sound_url) {
        return;
    }

    playNotificationAudio(sound_url, { interrupt: true });
};

const play_confirm_code_sound_if_needed = (active_items, previous_snapshot) => {
    if (!notification_sound_confirm_code_enabled.value) {
        return;
    }

    const has_new_code = (active_items ?? []).some((item) => {
        const item_id = String(item.id);
        if (!previous_snapshot.has(item_id)) {
            return false;
        }

        const key = latest_confirmation_code_key(item);
        const previous_key = previous_snapshot.get(item_id);

        return Boolean(key && key !== previous_key);
    });

    if (!has_new_code) {
        return;
    }

    const sound_url = resolve_track_url(notification_sound_confirm_code_preset.value);
    if (!sound_url) {
        return;
    }

    playNotificationAudio(sound_url, { interrupt: true });
};

const apply_state = (state) => {
    const previous_incoming_offer_id = incoming_offer_preview.value?.id ?? null;
    const previous_confirmation_snapshot = make_active_confirmation_code_snapshot(pay_in_queue_active.value);
    is_working.value = Boolean(state.is_working);

    const next_active_items = state.active_queue_items ?? [];
    const current_active_by_id = new Map(
        pay_in_queue_active.value.map((item) => [String(item.id), item]),
    );

    pay_in_queue_active.value = next_active_items.map((item) => {
        const previous_item = current_active_by_id.get(String(item.id));

        return normalize_processing_item(item, previous_item);
    });

    incoming_queue_waiting_count.value = Math.max(0, Number(state.incoming_queue_waiting_count ?? 0));
    incoming_offer_preview.value = state.incoming_offer
        ? normalize_processing_item(state.incoming_offer, incoming_offer_preview.value)
        : null;
    const next_history_items = state.history_queue_items ?? [];
    const current_history_by_id = new Map(
        pay_in_queue_history_visible.value.map((item) => [String(item.id), item]),
    );
    pay_in_queue_history_visible.value = next_history_items.map((item) => {
        const previous_item = current_history_by_id.get(String(item.id));

        return normalize_processing_item(item, previous_item);
    });
    history_total_count.value = Number(state.history_total_count ?? 0);
    sync_selected_item();
    sync_runtime_activity_by_work_status();

    if (is_initial_state_loaded.value) {
        const has_new_incoming_offer = Boolean(
            incoming_offer_preview.value
            && incoming_offer_preview.value.id !== previous_incoming_offer_id,
        );

        play_new_offer_sound_if_needed(has_new_incoming_offer);
        play_confirm_code_sound_if_needed(pay_in_queue_active.value, previous_confirmation_snapshot);
    } else {
        is_initial_state_loaded.value = true;
    }
};

const load_state = async () => {
    const requestSerial = ++stateRequestSerial;
    is_state_loading.value = true;

    try {
        const response = await axios.get(route(manual_control_route_name('state')));
        if (requestSerial !== stateRequestSerial) {
            return;
        }

        const state = response?.data?.data ?? {};
        apply_state(state);
    } catch (error) {
        // ignored
    } finally {
        if (requestSerial === stateRequestSerial) {
            is_state_loading.value = false;
        }
    }
};

const execute_set_work_status = async (next_work_status) => {
    if (is_work_toggle_processing.value) {
        return;
    }

    is_work_toggle_processing.value = true;

    try {
        const response = await axios.post(route(manual_control_route_name('work-status')), {
            is_working: next_work_status,
        });
        const state = response?.data?.data ?? {};
        apply_state(state);
        action_error_message.value = '';
    } catch (error) {
        action_error_message.value = error?.response?.data?.message ?? 'Не удалось изменить режим работы.';
        await load_state();
    } finally {
        is_work_toggle_processing.value = false;
    }
};

const request_toggle_work_status = () => {
    if (is_work_toggle_processing.value) {
        return;
    }

    const next_work_status = !is_working.value;

    modal_store.openConfirmModal({
        title: next_work_status ? 'Встать в работу?' : 'Выйти из работы?',
        body: next_work_status
            ? 'Вы уверены, что хотите начать работу с заявками Manual Control ACQ?'
            : 'Вы уверены, что хотите выйти из режима работы? Новые заявки перестанут приходить, а текущие можно будет завершить.',
        confirm_button_name: next_work_status ? 'Включить' : 'Выключить',
        cancel_button_name: 'Отмена',
        confirm: () => {
            execute_set_work_status(next_work_status);
        },
    });
};

const execute_take_incoming_offer = async () => {
    const incoming_offer = incoming_offer_preview.value;

    if (!is_working.value || !incoming_offer || is_take_processing.value) {
        return;
    }

    const taken_order_id = incoming_offer.id;
    is_take_processing.value = true;

    try {
        await axios.post(route(manual_control_route_name('take'), { order: taken_order_id }));
        action_error_message.value = '';
        await load_state();
        if (pay_in_queue_active.value.some((item) => item.id === taken_order_id)) {
            selected_item_id.value = taken_order_id;
            replace_selected_order_in_url(taken_order_id);
        }
    } catch (error) {
        action_error_message.value = error?.response?.data?.message ?? 'Не удалось взять заявку в обработку.';
        await load_state();
    } finally {
        is_take_processing.value = false;
    }
};

const execute_reject_offer = async (order_id, reject_reason = null) => {
    if (!order_id || is_reject_processing.value) {
        return;
    }

    is_reject_processing.value = true;

    try {
        await axios.post(route(manual_control_route_name('reject'), { order: order_id }), {
            reject_reason,
        });
        action_error_message.value = '';
        await load_state();
    } catch (error) {
        action_error_message.value = error?.response?.data?.message ?? 'Не удалось отклонить заявку.';
        await load_state();
    } finally {
        is_reject_processing.value = false;
    }
};

const request_take_incoming_offer = () => {
    if (!can_take_incoming_offer.value) {
        return;
    }

    const preview = incoming_offer_preview.value;
    const payin_label = preview?.payin_id.display ?? '—';

    modal_store.openConfirmModal({
        title: 'Взять заявку в работу?',
        body: `Вы подтверждаете взятие новой заявки с Order ID ${payin_label}.`,
        confirm_button_name: 'Взять',
        cancel_button_name: 'Отмена',
        confirm: () => {
            execute_take_incoming_offer();
        },
    });
};

const request_decline_incoming_offer = () => {
    const preview = incoming_offer_preview.value;

    if (!is_working.value || !preview) {
        return;
    }

    const payin_label = preview.payin_id.display ?? '—';

    modal_store.openConfirmModal({
        title: 'Отклонить входящую заявку?',
        body: `Вы подтверждаете отклонение заявки с Order ID ${payin_label}.`,
        confirm_button_name: 'Отклонить',
        cancel_button_name: 'Отмена',
        confirm: () => {
            execute_reject_offer(preview.id);
        },
    });
};

const select_queue_item = (item_id) => {
    selected_item_id.value = item_id;
    replace_selected_order_in_url(item_id);
};

const apply_confirmation_type = async (confirmation_type, confirmation_title) => {
    const item = selected_item.value;

    if (!item || item.is_history) {
        return;
    }

    if (is_confirmation_type_processing.value) {
        return;
    }

    is_confirmation_type_processing.value = true;

    try {
        await axios.post(route(manual_control_route_name('set-confirmation-type'), { order: item.id }), {
            confirmation_type,
        });
        action_error_message.value = '';
        item.pending_confirmation_title = confirmation_title;
        await load_state();
    } catch (error) {
        action_error_message.value = error?.response?.data?.message ?? 'Не удалось установить тип подтверждения.';
        await load_state();
    } finally {
        is_confirmation_type_processing.value = false;
    }
};

const request_select_confirmation_type = (confirmation_type, confirmation_title) => {
    const item = selected_item.value;

    if (!item || item.is_history || is_confirmation_type_processing.value) {
        return;
    }

    modal_store.openConfirmModal({
        title: 'Выбрать тип подтверждения?',
        body: `Действие: установить тип «${confirmation_title}» для заявки с Order ID ${item.payin_id.display}.`,
        confirm_button_name: 'Выбрать',
        cancel_button_name: 'Отмена',
        confirm: () => {
            apply_confirmation_type(confirmation_type, confirmation_title);
        },
    });
};

const confirmationButtonClass = (option) => {
    const base = 'btn h-auto min-h-8 w-full whitespace-normal px-3 py-1.5 text-center text-xs font-medium normal-case sm:min-h-9';
    const active = selected_item.value?.confirmation_type === option.value;

    return active ? `${base} btn-primary` : `${base} btn-outline`;
};

const execute_confirm_payment = async () => {
    if (!canConfirm.value) {
        return;
    }

    const item = selected_item.value;
    if (!item) {
        return;
    }

    is_confirm_processing.value = true;

    try {
        await axios.post(route(manual_control_route_name('confirm'), { order: item.id }));
        action_error_message.value = '';
        await load_state();
    } catch (error) {
        action_error_message.value = error?.response?.data?.message ?? 'Не удалось подтвердить заявку.';
        await load_state();
    } finally {
        is_confirm_processing.value = false;
    }
};

const request_confirm_payment = () => {
    if (!canConfirm.value) {
        return;
    }

    const item = selected_item.value;

    if (!item) {
        return;
    }

    modal_store.openConfirmModal({
        title: 'Подтвердить операцию?',
        body: `Действие: подтвердить заявку с Order ID ${item.payin_id.display} с типом «${item.pending_confirmation_title}».`,
        confirm_button_name: 'Confirm',
        cancel_button_name: 'Отмена',
        confirm: () => {
            return execute_confirm_payment();
        },
    });
};

const request_reject_application = () => {
    const item = selected_item.value;

    if (!item || item.is_history) {
        return;
    }

    open_reject_reason_modal();
};

const submit_reject_with_reason = async () => {
    const item = selected_item.value;

    if (!item || item.is_history || is_reject_processing.value) {
        return;
    }

    if (!selected_reject_reason.value) {
        return;
    }

    await execute_reject_offer(item.id, selected_reject_reason.value);

    if (!is_reject_processing.value) {
        close_reject_reason_modal();
    }
};

const copyField = async (fieldKey) => {
    const item = selected_item.value;

    if (!item) {
        return;
    }

    const key_map = {
        payinId: 'payin_id',
        incomingBank: 'incoming_bank',
        amount: 'amount',
        cardNumber: 'card_number',
        expiryDate: 'expiry_date',
        cardholderName: 'cardholder_name',
    };

    const prop = key_map[fieldKey];
    const raw_value = prop ? item[prop]?.copy : undefined;

    if (!raw_value) {
        return;
    }

    const value =
        fieldKey === 'cardholderName' ? String(raw_value).toUpperCase() : raw_value;

    try {
        await navigator.clipboard.writeText(value);
        copiedField.value = fieldKey;

        if (copiedFieldTimeout) {
            window.clearTimeout(copiedFieldTimeout);
        }

        copiedFieldTimeout = window.setTimeout(() => {
            copiedField.value = '';
        }, 1500);
    } catch (error) {
        // ignored
    }
};

const confirmation_history_copy_key = (order_id, index) => `${order_id}-confirmation-history-${index}`;

const copy_confirmation_history_code = async (order_id, entry, index) => {
    const value = entry?.copy;

    if (!value || !order_id) {
        return;
    }

    const history_key = confirmation_history_copy_key(order_id, index);

    try {
        await navigator.clipboard.writeText(value);
        copied_confirmation_history_key.value = history_key;

        if (copied_confirmation_history_timeout) {
            window.clearTimeout(copied_confirmation_history_timeout);
        }

        copied_confirmation_history_timeout = window.setTimeout(() => {
            copied_confirmation_history_key.value = '';
        }, 1500);
    } catch (error) {
        // ignored
    }
};

const is_confirmation_history_copied = (order_id, index) => {
    return copied_confirmation_history_key.value === confirmation_history_copy_key(order_id, index);
};

onMounted(async () => {
    await load_state();
    sync_runtime_activity_by_work_status();
});

onBeforeUnmount(() => {
    stop_workspace_timers();
    stop_state_polling();

    if (copiedFieldTimeout) {
        window.clearTimeout(copiedFieldTimeout);
    }

    if (copied_confirmation_history_timeout) {
        window.clearTimeout(copied_confirmation_history_timeout);
    }

    selected_reject_reason.value = '';
});
</script>

<template>
    <ManualControlLayout>
        <Head title="Manual Control ACQ" />

        <ManualControlConfirmModal />

        <div class="flex min-h-0 w-full flex-1 flex-col">
        <div class="flex min-h-0 w-full flex-1 flex-col lg:flex-row lg:items-stretch">
            <!-- Левая колонка: очередь оператора (DaisyUI menu + badges) -->
            <aside
                class="flex max-h-[40vh] shrink-0 flex-col border-b border-base-300 bg-base-200/80 lg:max-h-none lg:w-80 lg:border-b-0 lg:border-r"
            >
                <div class="flex items-center gap-2 border-b border-base-300 px-3 py-2.5">
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-semibold uppercase tracking-wide text-base-content/55">
                            Мои подтверждения
                        </p>
                        <p class="mt-0.5 text-[11px] leading-snug text-base-content/45">
                            Активные заявки и история.
                        </p>
                    </div>
                    <label
                        class="flex shrink-0 items-center gap-1.5"
                        :title="is_working ? 'Режим работы включен' : 'Режим работы выключен'"
                    >
                        <span class="text-[10px] font-medium uppercase tracking-wide text-base-content/50">
                            Работа
                        </span>
                        <input
                            type="checkbox"
                            class="toggle toggle-success toggle-sm"
                            :checked="is_working"
                            :disabled="is_work_toggle_processing"
                            @click.prevent="request_toggle_work_status"
                        >
                    </label>
                    <button
                        type="button"
                        class="btn btn-ghost btn-square btn-sm shrink-0"
                        aria-label="Настройки звуков уведомлений"
                        title="Настройки уведомлений"
                        @click="open_notification_settings_modal"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.5"
                            stroke="currentColor"
                            class="size-5"
                            aria-hidden="true"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.281Z"
                            />
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"
                            />
                        </svg>
                    </button>
                </div>
                <nav class="min-h-0 flex-1 overflow-y-auto px-2 py-2" aria-label="Очередь заявок">
                    <div
                        v-if="!is_working"
                        class="mb-3 rounded-box bg-base-100 px-3 py-2.5 text-xs leading-snug text-base-content/65"
                    >
                        Режим работы выключен. Новые заявки не поступают, но текущие можно завершить.
                    </div>
                    <div
                        v-if="incoming_offer_visible && incoming_offer_preview"
                        class="card mb-3 border border-accent/30 bg-base-100 shadow-sm ring-1 ring-accent/15"
                        role="status"
                        aria-live="polite"
                    >
                        <div
                            v-if="incoming_queue_waiting_count > 0"
                            class="border-b border-accent/20 bg-accent/5 px-3 py-1.5 text-[11px] font-medium text-base-content/75"
                        >
                            В очереди еще {{ incoming_queue_waiting_count }} заявок
                        </div>
                        <div class="card-body gap-3 p-3">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <h3 class="card-title text-sm font-semibold text-base-content">
                                    Новая заявка
                                    <span class="badge badge-accent badge-sm font-medium normal-case">Live</span>
                                </h3>
                                <div class="flex shrink-0 items-center gap-1.5">
                                    <div class="pointer-events-none flex size-4 shrink-0" aria-hidden="true">
                                        <svg
                                            class="size-4 -rotate-90"
                                            viewBox="0 0 100 100"
                                        >
                                            <circle
                                                cx="50"
                                                cy="50"
                                                :r="processingRingRadius"
                                                fill="none"
                                                stroke="currentColor"
                                                class="text-base-300"
                                                stroke-width="8"
                                            />
                                            <circle
                                                cx="50"
                                                cy="50"
                                                :r="processingRingRadius"
                                                fill="none"
                                                stroke="currentColor"
                                                class="text-primary"
                                                stroke-width="8"
                                                stroke-linecap="round"
                                                :stroke-dasharray="processingRingCircumference"
                                                :stroke-dashoffset="incomingProcessingRingDashoffset"
                                            />
                                        </svg>
                                    </div>
                                    <span class="text-xs font-semibold tabular-nums text-base-content">
                                        {{ incomingProcessingTime }}
                                    </span>
                                </div>
                            </div>

                            <div class="space-y-2 rounded-box border border-base-200 bg-base-200/30 px-2.5 py-2 text-xs">
                                <div class="flex items-baseline justify-between gap-2">
                                    <span class="shrink-0 font-medium uppercase tracking-wide text-base-content/50">Order ID</span>
                                    <span class="font-mono font-semibold tabular-nums text-base-content">
                                        {{ incoming_offer_preview.payin_id.display }}
                                    </span>
                                </div>
                                <div class="flex items-baseline justify-between gap-2">
                                    <span class="shrink-0 font-medium uppercase tracking-wide text-base-content/50">Банк</span>
                                    <span class="min-w-0 truncate text-right font-medium text-base-content">
                                        {{ incoming_offer_preview.incoming_bank.display }}
                                    </span>
                                </div>
                                <div class="flex items-baseline justify-between gap-2">
                                    <span class="shrink-0 font-medium uppercase tracking-wide text-base-content/50">Сумма</span>
                                    <span class="shrink-0 font-semibold tabular-nums text-base-content">
                                        {{ incoming_offer_preview.amount.display }}
                                    </span>
                                </div>
                            </div>

                            <div class="card-actions mt-0 grid grid-cols-2 gap-2">
                                <button
                                    type="button"
                                    class="btn btn-outline btn-error btn-sm h-9 min-h-9 w-full font-medium normal-case"
                                    @click="request_decline_incoming_offer"
                                >
                                    Отклонить
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-primary btn-sm h-9 min-h-9 w-full font-medium normal-case"
                                    :class="{ 'btn-disabled pointer-events-none opacity-60': !can_take_incoming_offer }"
                                    :disabled="!can_take_incoming_offer"
                                    @click="request_take_incoming_offer"
                                >
                                    Взять
                                </button>
                            </div>
                        </div>
                    </div>

                    <p class="px-2 pb-1 text-[10px] font-semibold uppercase tracking-wider text-base-content/40">
                        Текущие
                    </p>
                    <ul
                        v-if="active_queue_items.length"
                        class="menu menu-sm w-full rounded-box bg-base-100 p-0 shadow-sm"
                    >
                        <li v-for="item in active_queue_items" :key="item.id" class="w-full">
                            <button
                                type="button"
                                class="flex h-auto min-h-0 w-full flex-col items-stretch gap-1 rounded-lg py-2.5 text-left"
                                :class="selected_item_id === item.id ? 'menu-active' : ''"
                                @click="select_queue_item(item.id)"
                            >
                                <div class="flex w-full items-start justify-between gap-2">
                                    <span class="font-mono text-xs font-semibold tabular-nums text-base-content">
                                        {{ card_tail_label(item.card_number.display) }}
                                    </span>
                                    <span class="shrink-0 text-xs font-semibold tabular-nums text-base-content">
                                        {{ item.amount.display }}
                                    </span>
                                </div>
                                <div class="flex w-full flex-row items-end justify-between gap-2 text-[10px] text-base-content/55">
                                    <div class="flex min-w-0 flex-1 flex-wrap items-center gap-x-2 gap-y-1">
                                        <span class="tabular-nums">
                                            Processing {{ format_mm_ss(item.processing_elapsed_seconds) }}
                                        </span>
                                        <span
                                            v-if="item.pending_confirmation_title"
                                            class="inline-flex max-w-full items-center gap-1 truncate"
                                        >
                                            <span class="badge badge-primary badge-xs shrink-0 font-medium normal-case">
                                                {{ item.pending_confirmation_title }}
                                            </span>
                                        </span>
                                    </div>
                                    <div
                                        class="pointer-events-none flex size-3 shrink-0 translate-y-px"
                                        aria-hidden="true"
                                    >
                                        <svg
                                            class="size-3 -rotate-90"
                                            viewBox="0 0 100 100"
                                        >
                                            <circle
                                                cx="50"
                                                cy="50"
                                                :r="processingRingRadius"
                                                fill="none"
                                                stroke="currentColor"
                                                class="text-base-300"
                                                stroke-width="8"
                                            />
                                            <circle
                                                cx="50"
                                                cy="50"
                                                :r="processingRingRadius"
                                                fill="none"
                                                stroke="currentColor"
                                                class="text-primary"
                                                stroke-width="8"
                                                stroke-linecap="round"
                                                :stroke-dasharray="processingRingCircumference"
                                                :stroke-dashoffset="processing_ring_dashoffset_for_item(item)"
                                            />
                                        </svg>
                                    </div>
                                </div>
                            </button>
                        </li>
                    </ul>
                    <div
                        v-else
                        class="flex min-h-[4.5rem] w-full flex-col items-center justify-center rounded-box bg-base-100 px-3 py-5 text-center shadow-sm"
                        role="status"
                    >
                        <p class="max-w-[14rem] text-xs leading-snug text-base-content/50">
                            Пока нет активных сделок.
                        </p>
                    </div>

                    <div class="divider my-3 text-[10px] font-semibold uppercase tracking-wider text-base-content/40 before:bg-base-300 after:bg-base-300">
                        История (последние {{ HISTORY_DISPLAY_LIMIT }})
                    </div>

                    <ul
                        v-if="history_queue_items.length"
                        class="menu menu-sm w-full rounded-box bg-base-100/60 p-0 opacity-90 shadow-sm"
                    >
                        <li v-for="item in history_queue_items" :key="item.id" class="w-full">
                            <button
                                type="button"
                                class="flex h-auto min-h-0 w-full flex-col items-stretch gap-1 rounded-lg py-2.5 text-left"
                                :class="selected_item_id === item.id ? 'menu-active' : ''"
                                @click="select_queue_item(item.id)"
                            >
                                <div class="flex w-full items-start justify-between gap-2">
                                    <span class="font-mono text-xs font-medium tabular-nums text-base-content">
                                        {{ card_tail_label(item.card_number.display) }}
                                    </span>
                                    <span class="shrink-0 text-xs font-medium tabular-nums text-base-content">
                                        {{ item.amount.display }}
                                    </span>
                                </div>
                                <div class="flex w-full flex-row items-end justify-between gap-2 text-[10px] text-base-content/45">
                                    <div class="min-w-0 flex-1 tabular-nums">
                                        <span>{{ format_mm_ss(item.processing_elapsed_seconds) }}</span>
                                        <span class="ml-1.5">завершено</span>
                                    </div>
                                    <div
                                        class="pointer-events-none flex size-3 shrink-0 translate-y-px opacity-80"
                                        aria-hidden="true"
                                    >
                                        <svg
                                            class="size-3 -rotate-90"
                                            viewBox="0 0 100 100"
                                        >
                                            <circle
                                                cx="50"
                                                cy="50"
                                                :r="processingRingRadius"
                                                fill="none"
                                                stroke="currentColor"
                                                class="text-base-300/80"
                                                stroke-width="8"
                                            />
                                            <circle
                                                cx="50"
                                                cy="50"
                                                :r="processingRingRadius"
                                                fill="none"
                                                stroke="currentColor"
                                                class="text-base-content/35"
                                                stroke-width="8"
                                                stroke-linecap="round"
                                                :stroke-dasharray="processingRingCircumference"
                                                :stroke-dashoffset="processing_ring_dashoffset_for_item(item)"
                                            />
                                        </svg>
                                    </div>
                                </div>
                            </button>
                        </li>
                    </ul>
                    <div
                        v-else
                        class="flex min-h-[4.5rem] w-full flex-col items-center justify-center rounded-box bg-base-100/60 px-3 py-5 text-center opacity-90 shadow-sm"
                        role="status"
                    >
                        <p class="max-w-[14rem] text-xs leading-snug text-base-content/50">
                            История пока пуста.
                        </p>
                    </div>

                    <div
                        v-if="history_hidden_count > 0"
                        class="mt-2 rounded-box bg-base-200/50 px-2.5 py-2 text-[10px] leading-snug text-base-content/50"
                        role="note"
                    >
                        Показаны последние {{ HISTORY_DISPLAY_LIMIT }} из {{ history_total_count }}.
                        Ещё {{ history_hidden_count }} шт. старше в этом списке не подгружаются.
                    </div>
                </nav>
            </aside>

            <!-- Основная рабочая зона -->
            <div v-if="selected_item" class="mx-auto flex min-h-0 w-full min-w-0 max-w-2xl flex-1 flex-col gap-4 overflow-y-auto px-3 py-4 lg:px-6 lg:py-5">
                <header class="card bg-base-100 shadow">
                    <div class="card-body gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <h1 class="text-lg font-semibold text-base-content sm:text-xl">
                            Manual Control ACQ
                        </h1>

                        <div class="flex shrink-0 flex-row items-center gap-3 px-1 py-1 sm:gap-4">
                            <div class="relative flex size-[2.7rem] shrink-0 items-center justify-center">
                                <svg
                                    class="absolute inset-0 size-[2.7rem] -rotate-90"
                                    viewBox="0 0 100 100"
                                    aria-hidden="true"
                                >
                                    <circle
                                        cx="50"
                                        cy="50"
                                        :r="processingRingRadius"
                                        fill="none"
                                        stroke="currentColor"
                                        class="text-base-300"
                                        stroke-width="8"
                                    />
                                    <circle
                                        cx="50"
                                        cy="50"
                                        :r="processingRingRadius"
                                        fill="none"
                                        stroke="currentColor"
                                        class="text-primary"
                                        stroke-width="8"
                                        stroke-linecap="round"
                                        :stroke-dasharray="processingRingCircumference"
                                        :stroke-dashoffset="processingRingDashoffset"
                                    />
                                </svg>
                            </div>
                            <div class="flex min-w-0 flex-col items-start justify-center gap-0.5 text-left">
                                <p class="text-xs font-medium uppercase tracking-[0.2em] text-base-content/55">
                                    Processing Time
                                </p>
                                <p class="text-base font-semibold tabular-nums text-base-content sm:text-lg">
                                    {{ processingTime }}
                                </p>
                            </div>
                        </div>
                    </div>
                </header>

                <div v-if="action_error_message" class="alert alert-error text-sm">
                    <span>{{ action_error_message }}</span>
                </div>

                <section
                    v-if="selected_item.confirmation_codes?.length"
                    class="card bg-base-100 shadow"
                >
                    <div class="card-body gap-2.5 p-4 sm:p-5">
                        <div class="flex items-center justify-between gap-2">
                            <h2 class="text-sm font-semibold uppercase tracking-wide text-base-content/65">
                                Коды подтверждения
                            </h2>
                            <span class="badge badge-neutral badge-sm">
                                {{ selected_item.confirmation_codes.length }}
                            </span>
                        </div>

                        <div class="flex flex-wrap content-start gap-2">
                            <button
                                v-for="(confirmation_code_item, index) in selected_item.confirmation_codes"
                                :key="`${selected_item.id}-confirmation-code-${index}`"
                                type="button"
                                class="group flex max-w-full min-w-[5.5rem] flex-col items-stretch gap-0.5 rounded-lg border border-dashed border-primary/35 bg-base-200/40 px-2 py-1.5 text-left transition hover:border-primary/55 hover:bg-base-200/70 active:scale-[0.99] focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
                                :title="is_confirmation_history_copied(selected_item.id, index) ? 'Скопировано' : 'Скопировать код'"
                                @click="copy_confirmation_history_code(selected_item.id, confirmation_code_item, index)"
                            >
                                <span class="flex items-center justify-between gap-1">
                                    <span
                                        class="min-w-0 truncate"
                                        :class="is_confirmation_history_copied(selected_item.id, index)
                                            ? 'font-sans text-[10px] font-medium leading-tight tracking-normal text-success/75 sm:text-[11px]'
                                            : 'font-mono text-xs font-semibold tracking-[0.06em] text-base-content sm:text-sm'"
                                    >
                                        <template v-if="is_confirmation_history_copied(selected_item.id, index)">
                                            Скопировано
                                        </template>
                                        <template v-else>
                                            {{ confirmation_code_item.display }}
                                        </template>
                                    </span>
                                    <span
                                        class="shrink-0 rounded p-0.5 text-primary/70 transition group-hover:bg-primary/10 group-hover:text-primary"
                                        :class="is_confirmation_history_copied(selected_item.id, index) ? 'bg-success/15 text-success' : ''"
                                        aria-hidden="true"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-3.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 7.5V6.108c0-1.135.845-2.098 1.976-2.192.373-.03.748-.057 1.123-.08M15.75 18H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08M15.75 18.75v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5A3.375 3.375 0 0 0 6.375 7.5H5.25m11.9-3.664A2.251 2.251 0 0 0 15 2.25h-1.5a2.251 2.251 0 0 0-2.15 1.586m5.8 0c.065.21.1.433.1.664v.75h-6V4.5c0-.231.035-.454.1-.664M6.75 7.5H4.875c-.621 0-1.125.504-1.125 1.125v12c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V16.5a9 9 0 0 0-9-9Z" />
                                        </svg>
                                    </span>
                                </span>
                                <span class="block text-[10px] leading-tight text-base-content/55">
                                    {{ format_date_time(confirmation_code_item.created_at_ts) }}
                                </span>
                            </button>
                        </div>
                    </div>
                </section>

                <section class="card overflow-hidden bg-primary text-primary-content shadow">
                    <div class="card-body gap-4 p-4 sm:gap-7 sm:p-6">
                        <div class="grid gap-3 sm:grid-cols-2 sm:gap-6">
                            <div class="space-y-1 sm:space-y-2">
                                <p class="text-xs font-medium uppercase tracking-[0.2em] text-primary-content/70">
                                    ORDER ID
                                </p>
                                <button
                                    type="button"
                                    class="group flex cursor-pointer items-center gap-2 rounded-md text-left text-base font-semibold transition hover:text-primary-content/80 active:scale-[0.99] sm:text-lg"
                                    @click="copyField('payinId')"
                                >
                                    <span>{{ selected_item.payin_id.display }}</span>
                                    <AppTooltip
                                        :tip="copiedField === 'payinId' ? 'Скопировано' : ''"
                                        :open="copiedField === 'payinId'"
                                        placement="top"
                                        wrapper-class="inline-flex items-center justify-center rounded-full p-1 transition group-hover:bg-primary-content/10 group-hover:text-primary-content group-active:scale-95"
                                    >
                                        <span :class="copiedField === 'payinId' ? 'bg-primary-content/20 text-primary-content' : 'text-primary-content/75'">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-3.5 shrink-0 sm:size-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 7.5V6.108c0-1.135.845-2.098 1.976-2.192.373-.03.748-.057 1.123-.08M15.75 18H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08M15.75 18.75v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5A3.375 3.375 0 0 0 6.375 7.5H5.25m11.9-3.664A2.251 2.251 0 0 0 15 2.25h-1.5a2.251 2.251 0 0 0-2.15 1.586m5.8 0c.065.21.1.433.1.664v.75h-6V4.5c0-.231.035-.454.1-.664M6.75 7.5H4.875c-.621 0-1.125.504-1.125 1.125v12c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V16.5a9 9 0 0 0-9-9Z" />
                                        </svg>
                                        </span>
                                    </AppTooltip>
                                </button>
                            </div>

                            <div class="space-y-1 text-left sm:space-y-2 sm:text-right">
                                <p class="text-xs font-medium uppercase tracking-[0.2em] text-primary-content/70">
                                    AMOUNT
                                </p>
                                <button
                                    type="button"
                                    class="group flex cursor-pointer items-center gap-2 rounded-md text-left text-base font-semibold transition hover:text-primary-content/80 active:scale-[0.99] sm:ml-auto sm:justify-end sm:text-lg"
                                    @click="copyField('amount')"
                                >
                                    <span>{{ selected_item.amount.display }}</span>
                                    <AppTooltip
                                        :tip="copiedField === 'amount' ? 'Скопировано' : ''"
                                        :open="copiedField === 'amount'"
                                        placement="top"
                                        wrapper-class="inline-flex items-center justify-center rounded-full p-1 transition group-hover:bg-primary-content/10 group-hover:text-primary-content group-active:scale-95"
                                    >
                                        <span :class="copiedField === 'amount' ? 'bg-primary-content/20 text-primary-content' : 'text-primary-content/75'">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-3.5 shrink-0 sm:size-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 7.5V6.108c0-1.135.845-2.098 1.976-2.192.373-.03.748-.057 1.123-.08M15.75 18H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08M15.75 18.75v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5A3.375 3.375 0 0 0 6.375 7.5H5.25m11.9-3.664A2.251 2.251 0 0 0 15 2.25h-1.5a2.251 2.251 0 0 0-2.15 1.586m5.8 0c.065.21.1.433.1.664v.75h-6V4.5c0-.231.035-.454.1-.664M6.75 7.5H4.875c-.621 0-1.125.504-1.125 1.125v12c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V16.5a9 9 0 0 0-9-9Z" />
                                        </svg>
                                        </span>
                                    </AppTooltip>
                                </button>
                            </div>
                        </div>

                        <div class="space-y-1 sm:space-y-2">
                            <p class="text-xs font-medium uppercase tracking-[0.2em] text-primary-content/70">
                                Card Number
                            </p>
                            <button
                                type="button"
                                class="group flex cursor-pointer flex-wrap items-center gap-2 rounded-md text-left transition hover:text-primary-content/80 active:scale-[0.99]"
                                @click="copyField('cardNumber')"
                            >
                                <span class="break-words text-base font-semibold tracking-[0.16em] sm:text-2xl sm:tracking-[0.22em]">
                                    {{ selected_item.card_number.display }}
                                </span>
                                <AppTooltip
                                    :tip="copiedField === 'cardNumber' ? 'Скопировано' : ''"
                                    :open="copiedField === 'cardNumber'"
                                    placement="top"
                                    wrapper-class="inline-flex items-center justify-center rounded-full p-1 transition group-hover:bg-primary-content/10 group-hover:text-primary-content group-active:scale-95"
                                >
                                    <span :class="copiedField === 'cardNumber' ? 'bg-primary-content/20 text-primary-content' : 'text-primary-content/75'">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-3.5 shrink-0 sm:size-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 7.5V6.108c0-1.135.845-2.098 1.976-2.192.373-.03.748-.057 1.123-.08M15.75 18H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08M15.75 18.75v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5A3.375 3.375 0 0 0 6.375 7.5H5.25m11.9-3.664A2.251 2.251 0 0 0 15 2.25h-1.5a2.251 2.251 0 0 0-2.15 1.586m5.8 0c.065.21.1.433.1.664v.75h-6V4.5c0-.231.035-.454.1-.664M6.75 7.5H4.875c-.621 0-1.125.504-1.125 1.125v12c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V16.5a9 9 0 0 0-9-9Z" />
                                    </svg>
                                    </span>
                                </AppTooltip>
                            </button>
                        </div>

                        <div
                            class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between sm:gap-6"
                        >
                            <div
                                class="min-w-0 space-y-1 sm:space-y-2"
                                :class="selected_item.cardholder_name?.display ? 'flex-1' : ''"
                            >
                                <template v-if="selected_item.cardholder_name?.display">
                                    <p class="text-xs font-medium uppercase tracking-[0.2em] text-primary-content/70">
                                        Cardholder
                                    </p>
                                    <button
                                        type="button"
                                        class="group flex w-full min-w-0 cursor-pointer items-center gap-2 rounded-md text-left text-base font-semibold transition hover:text-primary-content/80 active:scale-[0.99] sm:text-lg"
                                        @click="copyField('cardholderName')"
                                    >
                                        <span class="min-w-0 truncate uppercase">{{ selected_item.cardholder_name.display }}</span>
                                        <AppTooltip
                                            :tip="copiedField === 'cardholderName' ? 'Скопировано' : ''"
                                            :open="copiedField === 'cardholderName'"
                                            placement="top"
                                            wrapper-class="inline-flex shrink-0 items-center justify-center rounded-full p-1 transition group-hover:bg-primary-content/10 group-hover:text-primary-content group-active:scale-95"
                                        >
                                            <span :class="copiedField === 'cardholderName' ? 'bg-primary-content/20 text-primary-content' : 'text-primary-content/75'">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-3.5 shrink-0 sm:size-4">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 7.5V6.108c0-1.135.845-2.098 1.976-2.192.373-.03.748-.057 1.123-.08M15.75 18H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08M15.75 18.75v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5A3.375 3.375 0 0 0 6.375 7.5H5.25m11.9-3.664A2.251 2.251 0 0 0 15 2.25h-1.5a2.251 2.251 0 0 0-2.15 1.586m5.8 0c.065.21.1.433.1.664v.75h-6V4.5c0-.231.035-.454.1-.664M6.75 7.5H4.875c-.621 0-1.125.504-1.125 1.125v12c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V16.5a9 9 0 0 0-9-9Z" />
                                            </svg>
                                            </span>
                                        </AppTooltip>
                                    </button>
                                </template>
                                <template v-else>
                                    <p class="text-xs font-medium uppercase tracking-[0.2em] text-primary-content/70">
                                        Expiry Date
                                    </p>
                                    <button
                                        type="button"
                                        class="group flex cursor-pointer items-center gap-2 rounded-md text-left text-base font-semibold transition hover:text-primary-content/80 active:scale-[0.99] sm:text-lg"
                                        @click="copyField('expiryDate')"
                                    >
                                        <span>{{ selected_item.expiry_date.display }}</span>
                                        <AppTooltip
                                            :tip="copiedField === 'expiryDate' ? 'Скопировано' : ''"
                                            :open="copiedField === 'expiryDate'"
                                            placement="top"
                                            wrapper-class="inline-flex items-center justify-center rounded-full p-1 transition group-hover:bg-primary-content/10 group-hover:text-primary-content group-active:scale-95"
                                        >
                                            <span :class="copiedField === 'expiryDate' ? 'bg-primary-content/20 text-primary-content' : 'text-primary-content/75'">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-3.5 shrink-0 sm:size-4">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 7.5V6.108c0-1.135.845-2.098 1.976-2.192.373-.03.748-.057 1.123-.08M15.75 18H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08M15.75 18.75v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5A3.375 3.375 0 0 0 6.375 7.5H5.25m11.9-3.664A2.251 2.251 0 0 0 15 2.25h-1.5a2.251 2.251 0 0 0-2.15 1.586m5.8 0c.065.21.1.433.1.664v.75h-6V4.5c0-.231.035-.454.1-.664M6.75 7.5H4.875c-.621 0-1.125.504-1.125 1.125v12c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V16.5a9 9 0 0 0-9-9Z" />
                                            </svg>
                                            </span>
                                        </AppTooltip>
                                    </button>
                                </template>
                            </div>
                            <div
                                v-if="selected_item.cardholder_name?.display"
                                class="min-w-0 space-y-1 text-left sm:space-y-2 sm:shrink-0 sm:text-right"
                            >
                                <p class="text-xs font-medium uppercase tracking-[0.2em] text-primary-content/70">
                                    Expiry Date
                                </p>
                                <button
                                    type="button"
                                    class="group flex cursor-pointer items-center gap-2 rounded-md text-left text-base font-semibold transition hover:text-primary-content/80 active:scale-[0.99] sm:ml-auto sm:justify-end sm:text-lg"
                                    @click="copyField('expiryDate')"
                                >
                                    <span>{{ selected_item.expiry_date.display }}</span>
                                    <AppTooltip
                                        :tip="copiedField === 'expiryDate' ? 'Скопировано' : ''"
                                        :open="copiedField === 'expiryDate'"
                                        placement="top"
                                        wrapper-class="inline-flex items-center justify-center rounded-full p-1 transition group-hover:bg-primary-content/10 group-hover:text-primary-content group-active:scale-95"
                                    >
                                        <span :class="copiedField === 'expiryDate' ? 'bg-primary-content/20 text-primary-content' : 'text-primary-content/75'">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-3.5 shrink-0 sm:size-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 7.5V6.108c0-1.135.845-2.098 1.976-2.192.373-.03.748-.057 1.123-.08M15.75 18H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08M15.75 18.75v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5A3.375 3.375 0 0 0 6.375 7.5H5.25m11.9-3.664A2.251 2.251 0 0 0 15 2.25h-1.5a2.251 2.251 0 0 0-2.15 1.586m5.8 0c.065.21.1.433.1.664v.75h-6V4.5c0-.231.035-.454.1-.664M6.75 7.5H4.875c-.621 0-1.125.504-1.125 1.125v12c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V16.5a9 9 0 0 0-9-9Z" />
                                        </svg>
                                        </span>
                                    </AppTooltip>
                                </button>
                            </div>
                        </div>
                    </div>
                </section>

                <section
                    v-if="is_selected_history && selected_item.outcome_status"
                    class="card bg-base-100 shadow"
                >
                    <div class="card-body gap-2 p-4 sm:p-5">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-base-content/50">
                            Итог сделки
                        </p>
                        <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-baseline sm:gap-x-3 sm:gap-y-1">
                            <span
                                class="badge badge-sm w-fit font-semibold normal-case"
                                :class="
                                    selected_item.outcome_status === 'accepted'
                                        ? 'badge-success'
                                        : 'badge-error'
                                "
                            >
                                {{
                                    selected_item.outcome_status === 'accepted'
                                        ? 'Accepted'
                                        : 'Rejected'
                                }}
                            </span>
                            <p
                                v-if="
                                    selected_item.outcome_status === 'rejected'
                                        && selected_item.reject_reason
                                "
                                class="text-sm leading-snug text-base-content/75"
                            >
                                {{ selected_item.reject_reason }}
                            </p>
                        </div>
                    </div>
                </section>

                <section class="card bg-base-100 shadow">
                    <div class="card-body gap-4 p-4 sm:p-5">
                        <div
                            v-if="is_selected_history"
                            class="alert alert-info py-2 text-sm"
                            role="status"
                        >
                            <span>История: только просмотр.</span>
                        </div>

                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <h2 class="text-lg font-semibold text-base-content">
                                Confirmation Type
                            </h2>
                            <span
                                v-if="selected_item.pending_confirmation_title"
                                class="badge badge-primary max-w-full shrink-0 truncate text-xs font-medium normal-case sm:max-w-[min(100%,18rem)] sm:text-sm"
                                :title="selected_item.pending_confirmation_title"
                            >
                                {{ selected_item.pending_confirmation_title }}
                            </span>
                        </div>

                        <div class="grid grid-cols-1 items-stretch gap-3 md:grid-cols-3">
                            <div class="flex flex-col gap-2">
                                <button
                                    v-for="option in confirmationCol1"
                                    :key="option.title"
                                    type="button"
                                    :class="confirmationButtonClass(option)"
                                    :disabled="is_selected_history || is_confirmation_type_processing"
                                    @click="request_select_confirmation_type(option.value, option.title)"
                                >
                                    {{ option.title }}
                                </button>
                            </div>

                            <div class="flex flex-col gap-2">
                                <button
                                    v-for="option in confirmationCol2"
                                    :key="option.title"
                                    type="button"
                                    :class="confirmationButtonClass(option)"
                                    :disabled="is_selected_history || is_confirmation_type_processing"
                                    @click="request_select_confirmation_type(option.value, option.title)"
                                >
                                    {{ option.title }}
                                </button>
                            </div>

                            <div
                                class="flex min-h-0 w-full flex-col gap-2 md:h-full"
                                :class="selected_item.pending_confirmation_title ? 'justify-center' : 'justify-start'"
                            >
                                <div v-if="!is_selected_history" class="flex w-full flex-col gap-2 rounded-box bg-base-200/40 p-3">
                                    <button
                                        type="button"
                                        class="btn btn-primary btn-sm h-auto min-h-9 w-full whitespace-normal px-3 py-2 text-xs font-medium normal-case"
                                        :class="{ 'btn-disabled pointer-events-none opacity-50': !canConfirm }"
                                        :disabled="!canConfirm"
                                        @click="request_confirm_payment"
                                    >
                                        Confirm
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-error btn-sm h-auto min-h-9 w-full whitespace-normal px-3 py-2 text-xs font-medium normal-case"
                                        @click="request_reject_application"
                                    >
                                        Reject
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <div
                v-else
                class="flex flex-1 flex-col items-center justify-center gap-2 px-4 py-12 text-center text-sm text-base-content/60"
            >
                {{ is_working ? 'Нет выбранной заявки.' : 'Новые заявки отключены. Выберите текущую заявку в очереди.' }}
            </div>
        </div>

        <dialog
            ref="reject_reason_dialog"
            tabindex="0"
            class="fixed inset-0 z-[998] m-0 max-h-none w-full max-w-none border-0 bg-transparent p-0 text-base-content outline-none backdrop:bg-base-300/55 [&:not([open])]:hidden open:flex open:flex-col open:items-stretch open:justify-end sm:open:items-center sm:open:justify-center sm:open:p-4"
        >
            <div class="relative z-[1] mt-auto w-full max-w-md rounded-t-box bg-base-100 p-6 shadow-2xl sm:mt-0 sm:rounded-box">
                <form method="dialog">
                    <button
                        type="submit"
                        class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2"
                        aria-label="Закрыть"
                        :disabled="is_reject_processing"
                    >
                        ✕
                    </button>
                </form>

                <h3 class="pr-10 text-lg font-bold text-base-content">
                    Причина отклонения
                </h3>
                <p class="mt-2 text-sm text-base-content/60">
                    Выберите причину отклонения заявки.
                </p>

                <div class="mt-4 flex max-w-full flex-col gap-2">
                    <button
                        v-for="reason in rejectReasons"
                        :key="reason"
                        type="button"
                        class="rounded-box border px-3 py-2.5 text-left text-sm font-normal leading-snug normal-case transition-colors"
                        :class="selected_reject_reason === reason
                            ? 'border-primary bg-primary/10 text-base-content'
                            : 'border-base-300 bg-base-100 text-base-content hover:border-base-content/30 hover:bg-base-200/80'"
                        :disabled="is_reject_processing"
                        @click="pick_reject_reason(reason)"
                    >
                        {{ reason }}
                    </button>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button
                        type="button"
                        class="btn btn-sm btn-ghost"
                        :disabled="is_reject_processing"
                        @click="close_reject_reason_modal"
                    >
                        Отмена
                    </button>
                    <button
                        type="button"
                        class="btn btn-sm btn-error"
                        :class="{ 'btn-disabled pointer-events-none opacity-60': is_reject_processing || !selected_reject_reason }"
                        :disabled="is_reject_processing || !selected_reject_reason"
                        @click="submit_reject_with_reason"
                    >
                        Отклонить
                    </button>
                </div>
            </div>
            <form method="dialog" class="absolute inset-0 z-0 grid place-items-stretch">
                <button type="submit" class="min-h-full w-full cursor-pointer bg-transparent text-transparent" aria-label="Закрыть">
                    close
                </button>
            </form>
        </dialog>

        <dialog
            ref="notification_settings_dialog"
            tabindex="0"
            class="fixed inset-0 z-[998] m-0 max-h-none w-full max-w-none border-0 bg-transparent p-0 text-base-content outline-none backdrop:bg-base-300/55 [&:not([open])]:hidden open:flex open:flex-col open:items-stretch open:justify-end sm:open:items-center sm:open:justify-center sm:open:p-4"
        >
            <div class="relative z-[1] mt-auto w-full max-w-md rounded-t-box bg-base-100 p-6 shadow-2xl sm:mt-0 sm:rounded-box">
                <form method="dialog">
                    <button
                        type="submit"
                        class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2"
                        aria-label="Закрыть"
                    >
                        ✕
                    </button>
                </form>
                <h3 class="pr-10 text-lg font-bold text-base-content">
                    Звуки уведомлений
                </h3>
                <p class="mt-2 text-sm text-base-content/60">
                    Включите уведомления и выберите звук для каждого события.
                </p>

                <div class="mt-5 space-y-4">
                    <div class="rounded-box p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-base-content">
                                    Новая заявка
                                </p>
                                <p class="mt-0.5 text-xs leading-snug text-base-content/50">
                                    Когда в очередь попадает новая заявка.
                                </p>
                            </div>
                            <input
                                v-model="notification_sound_new_offer_enabled"
                                type="checkbox"
                                class="toggle toggle-primary shrink-0"
                                aria-label="Звук при новой заявке"
                                :disabled="is_sound_settings_processing"
                                @change="toggle_new_offer_sound_enabled"
                            >
                        </div>
                        <div class="form-control mt-3 w-full">
                            <label class="label py-1 pt-0" for="mc-acq-sound-new-offer">
                                <span class="label-text text-xs text-base-content/55">Звук</span>
                            </label>
                            <select
                                id="mc-acq-sound-new-offer"
                                v-model="notification_sound_new_offer_preset"
                                class="select select-bordered select-sm w-full"
                                :disabled="!notification_sound_new_offer_enabled || is_sound_settings_processing || !notification_sound_options.length"
                                @change="select_new_offer_sound"
                            >
                                <option
                                    v-for="track in notification_sound_options"
                                    :key="`new-${track.value}`"
                                    :value="track.value"
                                >
                                    {{ track.label }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="rounded-box p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-base-content">
                                    Код подтверждения
                                </p>
                                <p class="mt-0.5 text-xs leading-snug text-base-content/50">
                                    Когда приходит OTP или другой код от банка.
                                </p>
                            </div>
                            <input
                                v-model="notification_sound_confirm_code_enabled"
                                type="checkbox"
                                class="toggle toggle-primary shrink-0"
                                aria-label="Звук при коде подтверждения"
                                :disabled="is_sound_settings_processing"
                                @change="toggle_confirm_code_sound_enabled"
                            >
                        </div>
                        <div class="form-control mt-3 w-full">
                            <label class="label py-1 pt-0" for="mc-acq-sound-confirm">
                                <span class="label-text text-xs text-base-content/55">Звук</span>
                            </label>
                            <select
                                id="mc-acq-sound-confirm"
                                v-model="notification_sound_confirm_code_preset"
                                class="select select-bordered select-sm w-full"
                                :disabled="!notification_sound_confirm_code_enabled || is_sound_settings_processing || !notification_sound_options.length"
                                @change="select_confirm_code_sound"
                            >
                                <option
                                    v-for="track in notification_sound_options"
                                    :key="`confirm-${track.value}`"
                                    :value="track.value"
                                >
                                    {{ track.label }}
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <div v-if="!notification_sound_options.length" class="mt-4 text-xs text-base-content/60">
                    Аудиофайлы не найдены в `public/audio`.
                </div>

                <div class="mt-6 flex justify-end">
                    <button
                        type="button"
                        class="btn btn-primary btn-sm"
                        @click="close_notification_settings_modal"
                    >
                        Готово
                    </button>
                </div>
            </div>
            <form method="dialog" class="absolute inset-0 z-0 grid place-items-stretch">
                <button type="submit" class="min-h-full w-full cursor-pointer bg-transparent text-transparent" aria-label="Закрыть">
                    close
                </button>
            </form>
        </dialog>

        </div>
    </ManualControlLayout>
</template>
