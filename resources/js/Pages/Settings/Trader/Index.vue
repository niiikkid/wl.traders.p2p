<script setup>
import {Head, useForm, usePage} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Multiselect from "@/Components/Form/Multiselect.vue";
import {computed, ref} from "vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import InputHelper from "@/Components/InputHelper.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import {filterMarketOptions} from "@/utils/market.js";

const page = usePage();
const settings = page.props.settings;
const markets = computed(() => filterMarketOptions(page.props.markets));

const form = useForm({
    allowed_markets: settings.allowed_markets,
});

const selectedValues = ref([]);

const submit = () => {
    form.patch(route('trader.settings.update'), {
        preserveScroll: true,
        onError: (result) => form.reset(),
    });
};

defineOptions({ layout: AuthenticatedLayout })
</script>

<template>
    <Head title="Настройки" />

    <div>
        <div>
            <div class="mx-auto space-y-6">
                <div class="flex justify-between items-center">
                    <h2 class="text-xl sm:text-4xl">Настройки</h2>
                </div>

                <div class="card bg-base-100 shadow">
                    <div class="card-body">
                    <section>
                        <header>
                            <h2 class="text-lg font-medium">Ваши персональные настройки сервиса</h2>
                        </header>

                        <form @submit.prevent="submit" class="mt-6 space-y-6">
                            <div>
                                <InputLabel
                                    for="allowed_markets"
                                    value="Разрешенные источники курса для обмена USDT"
                                    :error="!!form.errors.allowed_markets"
                                />

                                <Multiselect
                                    class="mt-1"
                                    v-model="form.allowed_markets"
                                    :options="markets"
                                    label-key="name"
                                ></Multiselect>

                                <InputError :message="form.errors.allowed_markets" class="mt-2" />
                                <InputHelper v-if="! form.errors.allowed_markets" model-value="Вы будете получать сделки только от тех мерчантов, которые использую выбранный источник курсов обмена USDT. Оставьте пустым, чтобы получать сделки от всех мерчантов."></InputHelper>
                            </div>

                            <div class="flex items-center gap-4">
                                <PrimaryButton :disabled="form.processing">Сохранить</PrimaryButton>

                                <Transition
                                    enter-active-class="transition ease-in-out"
                                    enter-from-class="opacity-0"
                                    leave-active-class="transition ease-in-out"
                                    leave-to-class="opacity-0"
                                >
                                    <p v-if="form.recentlySuccessful" class="text-sm opacity-70">Сохранено.</p>
                                </Transition>
                            </div>
                        </form>
                    </section>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
