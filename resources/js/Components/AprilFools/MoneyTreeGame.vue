<script setup>
import {computed, onBeforeUnmount, onMounted, ref} from 'vue';
import {useModalStore} from '@/store/modal.js';

const MIN_PHASE = 1
const MAX_PHASE = 5
const WATER_COOLDOWN_MS = 5 * 60 * 1000
const CYCLE_DURATION_MS = 10 * 60 * 1000
const BLESSING_DURATION_MS = 5 * 60 * 1000
const STORAGE_KEY = 'money-tree:priest-state'
const FULL_DROUGHT_CYCLE_MESSAGE = 'Чадо моё, тут уже всякая надежда иссякла — одна засуха и уныние. Уже не до отсчёта: пора полить, да не медлить.'
const LOW_STAGE_THRESHOLD = 3
const highStageDecayMessages = [
    'Чадо моё, чуть отвлёкся, и стадия уже сползла. Не ропщи, а просто поливай вовремя.',
    'Любезный мой, было уже лучше, да забота ослабла. Ещё немного усердия, и всё вернётся.',
    'Сын мой, время прошло без воды, вот и отступили мы от благодати. Не унывай, поправим.',
    'Чадо возлюбленное, до изобилия было рукой подать, да полив запоздал. На Бога уповай и не зевай.',
    'Видишь, как быстро всё меняется без заботы. Храни внимание к древу, и батюшка снова повеселеет.',
    'Чадо моё, не беда, что откатились. Подлей воды, и милость снова придёт к лавочке.',
]
const lowStageDecayMessages = [
    'Чадо моё, опять спустились в уныние. Поливай вовремя, а то и листва грустит, и батюшка хмурится.',
    'Сын мой, без заботы всё быстро уходит в тоску. Подай воды и не доводи до полной сухости.',
    'Любезный мой, не забывай про древо, а то у лавочки снова поселится печаль.',
    'Видишь, до чего дошло: ни радости, ни свежести. Полей скорее, пока надежда не усохла.',
    'Пора воды подать, чадо. А то останемся и без прибыли, и без благодати.',
    'Чадо моё неразумное, не ропщи на сухость, а лучше подлей влаги и исправь дело.',
    'Сын мой, всё опять ушло в печаль. Ещё немного заботы, и жизнь вернётся.',
    'Возлюбленное моё чадо, не оставляй всё на самотёк. Без полива тут одна тоска душевная.',
    'Любезный мой, Господь да поможет, но и ты древо без присмотра не бросай.',
    'Чадо моё, не бойся, ещё не всё потеряно. Подай воды, и батюшка смягчится.',
    'Сын мой, храни послушание в малом: вовремя полей, и сердце батюшки оттает.',
]

const phaseMeta = {
    1: {
        title: 'Фаза 1: Ропот и смущение духа',
        priestMessage: 'Чадо моё возлюбленное, не ропщи, а глянь на батюшку: купюры под ногами лежат, а в руки не даются. Стою в смущении духа и жду от тебя усердия.',
    },
    2: {
        title: 'Фаза 2: Глубокое уныние',
        priestMessage: 'Сын мой, не унывай, но посмотри: сижу на лавочке, к небу взываю и с терпением ожидаю. Богатство рядом, да всё будто мимо проходит, а мне одна печаль.',
    },
    3: {
        title: 'Фаза 3: Тоска душевная',
        priestMessage: 'Чадо моё любезное, тут уже тоска душевная. И глаза смежил, и голову склонил: рядом всё блестит, а на сердце ни мира, ни утешения. Подай воды и верни надежду.',
    },
    4: {
        title: 'Фаза 4: Обретение надежды',
        priestMessage: 'Любезный мой, вот и первая милость. И мешочек к лавочке снизошёл, и монета под руку пришла. Пребывай в надежде: твоё старание начинает приносить плод.',
    },
    5: {
        title: 'Фаза 5: Изобилие и благодать',
        priestMessage: 'Возлюбленное моё чадо, вот теперь благодать и утешение духовное. И мешки полны, и золото рядом, и сердце батюшки мирно: такому усердию и я рад, и казна довольна.',
    },
}

const waterMessages = [
    'Чадо моё, хорошо полил. Сразу видно: жизнь в крону возвращается, и батюшке теплее.',
    'Сын мой, забота твоя не прошла мимо. Листва ожила, и на душе у меня полегчало.',
    'Любезный мой, с таким поливом древо не пропадёт, и у лавочки уже веселее.',
    'Полил вовремя, чадо, и сразу перемена к лучшему: крона свежее, батюшка добрее.',
    'Чадо возлюбленное, вот это дело. Влага пошла, а с нею и надежда на достаток.',
    'Сын мой, не сомневайся: после такого полива и благодать ближе, и мешочек не за горами.',
    'Любезный мой, благодарю за усердие. Вижу, что древо тебе не безразлично.',
    'Чадо моё любезное, ещё немного такой заботы, и тоска совсем отступит.',
    'Возлюбленное моё чадо, мир тебе и Божие благословение за своевременную воду.',
    'Сын мой, верно идёшь: полив есть, значит и путь к изобилию снова открыт.',
    'Чадо моё, молодец. Вода вовремя пришла, а вместе с нею и настроение у батюшки поправилось.',
    'Любезный мой, правильно сделал, что не пожалел воды. Древо откликнулось сразу.',
]

const phase = ref(MIN_PHASE)
const lastPhaseChangeAt = ref(Date.now())
const nextAllowedWaterAt = ref(0)
const disputesBlessingUntil = ref(0)
const trafficBlessingUntil = ref(0)
const feedbackMessage = ref('')
const imageLoadError = ref(false)
const tickNow = ref(Date.now())
const isPanelHidden = ref(false)
const modalStore = useModalStore()

let tickInterval = null

const clampPhase = (value) => Math.max(MIN_PHASE, Math.min(MAX_PHASE, value))

const imageSrc = computed(() => `/priest/${phase.value}.png`)
const activePhaseMeta = computed(() => phaseMeta[phase.value] ?? phaseMeta[MIN_PHASE])
const expectedImagePath = computed(() => imageSrc.value)
const canWater = computed(() => tickNow.value >= nextAllowedWaterAt.value)
const canPrayAgainstDisputes = computed(() => phase.value >= 4)
const canBlessMerchant = computed(() => phase.value >= 5)
const hasDisputesBlessing = computed(() => tickNow.value < disputesBlessingUntil.value)
const hasTrafficBlessing = computed(() => tickNow.value < trafficBlessingUntil.value)
const priestText = computed(() => feedbackMessage.value || activePhaseMeta.value.priestMessage)
const isGrowthAtFullDrought = computed(() => phase.value === MIN_PHASE)

const waterCooldownLeftMs = computed(() => Math.max(0, nextAllowedWaterAt.value - tickNow.value))
const cycleLeftMs = computed(() => {
    const elapsed = tickNow.value - lastPhaseChangeAt.value
    return Math.max(0, CYCLE_DURATION_MS - elapsed)
})
const disputesBlessingLeftMs = computed(() => Math.max(0, disputesBlessingUntil.value - tickNow.value))
const trafficBlessingLeftMs = computed(() => Math.max(0, trafficBlessingUntil.value - tickNow.value))

const formatDuration = (value) => {
    const totalSeconds = Math.max(0, Math.floor(value / 1000))
    const minutes = Math.floor(totalSeconds / 60)
    const seconds = totalSeconds % 60

    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`
}

const cycleLeftText = computed(() => formatDuration(cycleLeftMs.value))
const waterCooldownText = computed(() => formatDuration(waterCooldownLeftMs.value))
const disputesBlessingLeftText = computed(() => formatDuration(disputesBlessingLeftMs.value))
const trafficBlessingLeftText = computed(() => formatDuration(trafficBlessingLeftMs.value))

const pickRandomMessage = (messages, excludedMessage = '') => {
    const normalizedMessages = messages.filter(Boolean)

    if (normalizedMessages.length === 0) {
        return ''
    }

    const availableMessages = normalizedMessages.filter((message) => message !== excludedMessage)
    const pool = availableMessages.length > 0 ? availableMessages : normalizedMessages

    return pool[Math.floor(Math.random() * pool.length)] ?? pool[0]
}

const persistState = () => {
    try {
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify({
            phase: phase.value,
            lastPhaseChangeAt: lastPhaseChangeAt.value,
            nextAllowedWaterAt: nextAllowedWaterAt.value,
            disputesBlessingUntil: disputesBlessingUntil.value,
            trafficBlessingUntil: trafficBlessingUntil.value,
            feedbackMessage: feedbackMessage.value,
            isPanelHidden: isPanelHidden.value,
        }))
    } catch (error) {
        // ignore storage errors
    }
}

const processLifecycle = () => {
    const now = Date.now()
    tickNow.value = now

    if (now - lastPhaseChangeAt.value < CYCLE_DURATION_MS) {
        return
    }

    const elapsedCycles = Math.floor((now - lastPhaseChangeAt.value) / CYCLE_DURATION_MS)
    if (elapsedCycles <= 0) {
        return
    }

    const nextPhase = clampPhase(phase.value - elapsedCycles)
    if (nextPhase !== phase.value) {
        const previousPhase = phase.value
        const excludedBeforeDecay = feedbackMessage.value || phaseMeta[previousPhase]?.priestMessage || ''
        phase.value = nextPhase
        if (nextPhase <= LOW_STAGE_THRESHOLD) {
            feedbackMessage.value = pickRandomMessage(lowStageDecayMessages, excludedBeforeDecay)
                || lowStageDecayMessages[0]
        } else {
            feedbackMessage.value = pickRandomMessage(highStageDecayMessages, excludedBeforeDecay)
                || highStageDecayMessages[0]
        }
        imageLoadError.value = false
    }

    lastPhaseChangeAt.value += elapsedCycles * CYCLE_DURATION_MS
    persistState()
}

const waterTree = () => {
    processLifecycle()

    if (!canWater.value) {
        feedbackMessage.value = `Не спеши, чадо моё. С терпением ожидай ещё ${waterCooldownText.value}, потом снова подашь воды.`
        return
    }

    const previousPhase = phase.value
    const excludedBeforeWater = feedbackMessage.value || phaseMeta[previousPhase]?.priestMessage || ''
    phase.value = clampPhase(phase.value + 1)
    lastPhaseChangeAt.value = Date.now()
    nextAllowedWaterAt.value = lastPhaseChangeAt.value + WATER_COOLDOWN_MS
    imageLoadError.value = false
    feedbackMessage.value = pickRandomMessage(
        waterMessages,
        excludedBeforeWater,
    ) || 'Полив принят, чадо моё. Господь да поможет.'

    persistState()
    tickNow.value = Date.now()
}

const prayAgainstDisputes = () => {
    if (!canPrayAgainstDisputes.value) {
        feedbackMessage.value = 'Сын мой, сперва приведи всё в порядок. Пока батюшка сам в скорби, молитва о тишине в сделках не взойдёт.'
        return
    }

    disputesBlessingUntil.value = Date.now() + BLESSING_DURATION_MS
    feedbackMessage.value = 'Чадо моё возлюбленное, помолюсь о тебе: пусть сделки будут защищены, споры умолкнут, а мир тебе и Божие благословение.'
    persistState()
    tickNow.value = Date.now()
}

const blessTraffic = () => {
    if (!canBlessMerchant.value) {
        feedbackMessage.value = 'Любезный мой, рано просить о жирных сделках. Сперва доведи всё до благодати, потом и мерчанта благословим.'
        return
    }

    trafficBlessingUntil.value = Date.now() + BLESSING_DURATION_MS
    feedbackMessage.value = 'Сын мой, благословляю мерчанта на трафик обильный и чеки щедрые. Пребывай в надежде: батюшка уже за достаток хлопочет.'
    persistState()
    tickNow.value = Date.now()
}

const handleImageError = () => {
    imageLoadError.value = true
}

const hidePanel = () => {
    isPanelHidden.value = true
    persistState()
}

const showPanel = () => {
    isPanelHidden.value = false
    persistState()
}

const confirmHidePanel = () => {
    modalStore.openConfirmModal({
        title: 'Скрыть панель батюшки?',
        body: 'Батюшка, конечно, расстроится. Нехорошо это, не по-христиански. Точно скрываем?',
        confirm_button_name: 'Все равно скрыть',
        cancel_button_name: 'Оставить как есть',
        confirm: hidePanel,
    })
}

onMounted(() => {
    try {
        const rawValue = window.localStorage.getItem(STORAGE_KEY)
        if (rawValue) {
            const parsedValue = JSON.parse(rawValue)
            phase.value = clampPhase(Number(parsedValue.phase) || MIN_PHASE)
            lastPhaseChangeAt.value = Number(parsedValue.lastPhaseChangeAt) || Date.now()
            nextAllowedWaterAt.value = Number(parsedValue.nextAllowedWaterAt) || 0
            disputesBlessingUntil.value = Number(parsedValue.disputesBlessingUntil) || 0
            trafficBlessingUntil.value = Number(parsedValue.trafficBlessingUntil) || 0
            feedbackMessage.value = String(parsedValue.feedbackMessage || '')
            isPanelHidden.value = Boolean(parsedValue.isPanelHidden)
        }
    } catch (error) {
        // ignore storage errors
    }

    processLifecycle()

    tickInterval = window.setInterval(() => {
        processLifecycle()
    }, 1000)
})

onBeforeUnmount(() => {
    if (tickInterval) {
        window.clearInterval(tickInterval)
        tickInterval = null
    }
})
</script>

<template>
    <div v-if="isPanelHidden" class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body p-3">
            <button
                type="button"
                class="btn btn-ghost btn-sm"
                @click="showPanel"
            >
                Показать любимое денежное древо батюшки
            </button>
        </div>
    </div>

    <div v-else class="card border border-success/20 bg-base-100 shadow-sm">
        <div class="card-body p-4 gap-3">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="badge badge-success badge-outline mb-1">
                        <span class="sm:hidden">Любимое дерево</span>
                        <span class="hidden sm:inline">Любимое денежное древо батюшки.</span>
                    </div>
                    <p class="text-xs text-base-content/70">{{ activePhaseMeta.title }}</p>
                </div>

                <div class="flex items-center gap-1">
                    <button
                        type="button"
                        class="btn btn-ghost btn-xs"
                        @click="confirmHidePanel"
                    >
                        Скрыть
                    </button>

                    <div class="dropdown dropdown-end">
                        <button type="button" tabindex="0" class="btn btn-ghost btn-xs btn-square" aria-label="Наставление">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                            </svg>
                        </button>
                        <div tabindex="0" class="dropdown-content z-30 mt-2 w-72 rounded-box border border-base-300 bg-base-100 p-3 shadow">
                            <div class="text-sm font-semibold text-base-content">Наставление</div>
                            <div class="mt-2 space-y-2 text-xs text-base-content/70">
                                <p>Своевременно поливай любимое денежное древо батюшки, чтобы батюшка не впадал в духовную тоску.</p>
                                <p>Батюшка — хранитель сей площадки: от его расположения зависят и милости, и кары небесные.</p>
                                <p>Кто заботится о древе и не бросает его без полива, тот получает от батюшки милости небесные.</p>
                                <p>Каждые 10 минут сменяется стадия: ухаживаешь исправно — батюшка идет от скорби к благодати, забываешь — обратно в уныние.</p>
                                <p>На четвёртой стадии откроется первая особая милость от батюшки.</p>
                                <p>На пятой стадии, когда изобилие полное, появится вторая милость.</p>
                                <p>Задача проста: довести дерево до полного изобилия и вернуть батюшке мир, улыбку и мешочки с золотом у лавочки.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="h-72 rounded-box bg-base-200/60 overflow-hidden flex items-end justify-center p-1">
                <img
                    v-if="!imageLoadError"
                    :src="imageSrc"
                    alt="Батюшка и его саженье"
                    class="max-h-full max-w-full scale-115 rounded-xl object-contain object-bottom select-none"
                    draggable="false"
                    @error="handleImageError"
                >
                <div v-else class="text-center text-xs text-base-content/60 px-3">
                    Нет изображения для стадии {{ phase }}.
                    <div class="mt-1 font-mono">{{ expectedImagePath }}</div>
                </div>
            </div>

            <div class="rounded-box bg-success/12 border border-success/25 px-3 py-2 text-xs leading-relaxed text-base-content shadow-sm">
                {{ priestText }}
            </div>

            <div class="text-[11px] text-base-content/60 space-y-1">
                <p v-if="isGrowthAtFullDrought">{{ FULL_DROUGHT_CYCLE_MESSAGE }}</p>
                <p v-else>До следующего цикла жизни: {{ cycleLeftText }}</p>
                <p v-if="!canWater">Полив снова доступен через: {{ waterCooldownText }}</p>
            </div>

            <div v-if="hasDisputesBlessing || hasTrafficBlessing" class="flex flex-wrap gap-2">
                <div v-if="hasDisputesBlessing" class="badge badge-success badge-outline">
                    Сделки защищены {{ disputesBlessingLeftText }}
                </div>
                <div v-if="hasTrafficBlessing" class="badge badge-warning badge-outline">
                    Мерчант освящен {{ trafficBlessingLeftText }}
                </div>
            </div>

            <button
                type="button"
                class="btn btn-primary btn-sm"
                :class="{ 'btn-disabled': !canWater }"
                :disabled="!canWater"
                @click="waterTree"
            >
                Полить дерево
            </button>

            <div
                v-if="(canPrayAgainstDisputes && !hasDisputesBlessing) || (canBlessMerchant && !hasTrafficBlessing)"
                class="grid grid-cols-1 gap-2 sm:grid-cols-2"
            >
                <button
                    v-if="canPrayAgainstDisputes && !hasDisputesBlessing"
                    type="button"
                    class="btn btn-success btn-sm h-auto min-h-0 flex-col items-stretch gap-1 py-2.5 px-3 text-left whitespace-normal"
                    @click="prayAgainstDisputes"
                >
                    <span class="font-semibold leading-tight">Попросить батюшку помолиться от споров</span>
                    <span class="text-[10px] font-normal leading-snug opacity-90">Чтобы споры не лезли в сделки и день прошёл в благодати.</span>
                </button>
                <button
                    v-if="canBlessMerchant && !hasTrafficBlessing"
                    type="button"
                    class="btn btn-warning btn-sm h-auto min-h-0 flex-col items-stretch gap-1 py-2.5 px-3 text-left whitespace-normal"
                    @click="blessTraffic"
                >
                    <span class="font-semibold leading-tight">Освятить мерчанта на жирные сделки</span>
                    <span class="text-[10px] font-normal leading-snug opacity-90">Благословение на щедрый трафик и толстые чеки.</span>
                </button>
            </div>
        </div>
    </div>
</template>
