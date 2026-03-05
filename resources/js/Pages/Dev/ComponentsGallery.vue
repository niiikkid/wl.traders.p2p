<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SectionTitle from '@/Components/SectionTitle.vue'

// Alerts
import AlertError from '@/Components/Alerts/AlertError.vue'
import AlertInfo from '@/Components/Alerts/AlertInfo.vue'
import AlertWarning from '@/Components/Alerts/AlertWarning.vue'
import {Head} from "@inertiajs/vue3";
import FiltersPanel from '@/Components/Filters/FiltersPanel.vue'
import InputFilter from '@/Components/Filters/Pertials/InputFilter.vue'
import DropdownFilter from '@/Components/Filters/Pertials/DropdownFilter.vue'
import DateFilter from '@/Components/Filters/Pertials/DateFilter.vue'
import FilterCheckbox from '@/Components/Filters/Pertials/FilterCheckbox.vue'
import {onMounted, reactive, ref} from 'vue'
import {useTableFiltersStore} from '@/store/tableFilters.js'
import TextInputBlock from '@/Components/Form/TextInputBlock.vue'
import NumberInputBlock from '@/Components/Form/NumberInputBlock.vue'
import Multiselect from '@/Components/Form/Multiselect.vue'
import DropDownWithCheckbox from '@/Components/Form/DropDownWithCheckbox.vue'
import DropDownWithRadio from '@/Components/Form/DropDownWithRadio.vue'
import TimepickerInput from '@/Components/Form/TimepickerInput.vue'
import Dropzone from '@/Components/Form/Dropzone.vue'
import SaveButton from '@/Components/Form/SaveButton.vue'
import TextInput from '@/Components/TextInput.vue'
import Pagination from '@/Components/Pagination/Pagination.vue'
import RefreshTableData from '@/Components/Table/RefreshTableData.vue'
import TableActionsDropdown from '@/Components/Table/TableActionsDropdown.vue'
import TableAction from '@/Components/Table/TableAction.vue'
import FailAction from '@/Components/Table/FailAction.vue'
import SuccessAction from '@/Components/Table/SuccessAction.vue'
import ShowAction from '@/Components/Table/ShowAction.vue'
import EditAction from '@/Components/Table/EditAction.vue'
import AddMobileIcon from '@/Components/AddMobileIcon.vue'
import Checkbox from '@/Components/Checkbox.vue'
import Clock from '@/Components/Clock.vue'
import CopyAddress from '@/Components/CopyAddress.vue'
import CopyPaymentText from '@/Components/CopyPaymentText.vue'
import CopyUUID from '@/Components/CopyUUID.vue'
import DateTime from '@/Components/DateTime.vue'
import DisplayID from '@/Components/DisplayID.vue'
import DisplayUUID from '@/Components/DisplayUUID.vue'
import DisputeStatus from '@/Components/DisputeStatus.vue'
import EmptyTable from '@/Components/EmptyTable.vue'
import GatewayLogo from '@/Components/GatewayLogo.vue'
import GoBackButton from '@/Components/GoBackButton.vue'
import InputError from '@/Components/InputError.vue'
import InputHelper from '@/Components/InputHelper.vue'
import InputLabel from '@/Components/InputLabel.vue'
import InvoiceStatus from '@/Components/InvoiceStatus.vue'
import IsActiveStatus from '@/Components/IsActiveStatus.vue'
import NumberInput from '@/Components/NumberInput.vue'
import OrderStatus from '@/Components/OrderStatus.vue'
import PaymentDetail from '@/Components/PaymentDetail.vue'
import PaymentDetailLimit from '@/Components/PaymentDetailLimit.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import ProgressNumber from '@/Components/ProgressNumber.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'
import Select from '@/Components/Select.vue'
import TextArea from '@/Components/TextArea.vue'

defineOptions({ layout: AuthenticatedLayout })

const tableFiltersStore = useTableFiltersStore();

// Открываем панель фильтров по умолчанию для демо
if (typeof window !== 'undefined') {
    localStorage.setItem('display-filters-components-gallery-demo', 'display');
}

onMounted(() => {
    tableFiltersStore.setFiltersVariants({
        status: [
            { name: 'Новые', value: 'new' },
            { name: 'В работе', value: 'in_progress' },
            { name: 'Завершённые', value: 'done' },
        ],
        category: [
            { name: 'Карты', value: 'card' },
            { name: 'Банки', value: 'bank' },
            { name: 'Крипто', value: 'crypto' },
        ],
    });

    tableFiltersStore.setFilters({
        id: '',
        name: '',
        status: '',
        category: '',
        date: '',
        onlyActive: false,
    });

    // Инициализация демо-таймера
    if (clockRef && clockRef.value && typeof clockRef.value.initializeClock === 'function') {
        clockRef.value.initializeClock();
    }
});

// Демо-данные для блока форм
const demoForm = reactive({
    data: {
        title: '',
        amount: null,
        baseInput: '',
    },
    errors: {},
    clearErrors(field) {
        if (this.errors && this.errors[field]) {
            delete this.errors[field];
        }
    },
});

const demoMulti = ref([]);
const demoMultiOptions = [
    { label: 'Опция A', value: 'A' },
    { label: 'Опция B', value: 'B' },
    { label: 'Опция C', value: 'C' },
];

const demoCheckboxItems = [
    { name: '09:00', value: '09:00' },
    { name: '12:00', value: '12:00' },
    { name: '18:00', value: '18:00' },
];
const selectedTimes = ref([]);

const demoRadioItems = [
    { name: 'RUB', value: 'RUB' },
    { name: 'USD', value: 'USD' },
    { name: 'EUR', value: 'EUR' },
];
const selectedCurrency = ref(null);

const demoTime = ref('');
const demoFile = ref(null);
const saveDone = ref(false);

// Pagination demo state
const navPage = ref(1);
const pagPage = ref(3);
const tablePage = ref(1);
const totalItems = ref(95);
const perPage = ref(10);

// Table demo state
const isRefreshing = ref(false);

// Extra components demo state
const demoChecked = ref(false);
const clockRef = ref(null);
const clockNow = ref(new Date().toISOString());
const clockExpires = ref(new Date(Date.now() + 5 * 60 * 1000).toISOString());
const demoCopyText = ref('12345.67');
const demoUUID = ref('550e8400-e29b-41d4-a716-446655440000');
const demoID = ref('USER-1234567890');
const demoDate = ref(new Date().toISOString());
const demoHelperText = ref('Подсказка к полю: будет показан вспомогательный текст.');
const demoErrorText = ref('Ошибка: некорректное значение.');
const demoLabelError = ref(false);
const demoNumber = ref(0);
const dailyCurrent = ref('35');
const dailyLimit = ref('100');
const selectValue = ref('0');
const selectItems = ref([
    { id: '1', title: 'Опция 1' },
    { id: '2', title: 'Опция 2' },
    { id: '3', title: 'Опция 3' },
]);
const demoTextarea = ref('');
const demoTextInput = ref('');
const demoFormatText = ref('Очень длинная строка для примера сокращения и подсказок');
const demoTooltipText = ref('Это подсказка для текста');
const demoPopoverText = ref('Это всплывающее описание с дополнительной информацией.');
</script>

<template>
    <div>
        <Head title="Галерея компонентов" />

        <SectionTitle>Галерея компонентов</SectionTitle>

        <div class="w-full">
            <div>
                <div class="grid grid-cols-1 gap-2">
                    <div class="card bg-base-100 card-border shadow">
                        <div class="card-body">
                            <div class="font-semibold text-base mb-2 break-all">Alerts</div>
                            <div class="space-y-2">
                                <AlertError message="Произошла ошибка при обработке запроса. Повторите попытку позже." />
                                <AlertInfo message="Это информационное сообщение для пользователя." />
                                <AlertWarning message="Внимание: проверьте корректность введённых данных." />
                            </div>
                        </div>
                    </div>
                    <div class="card bg-base-100 card-border shadow">
                        <div class="card-body">
                            <div class="font-semibold text-base mb-2 break-all">Доп. компоненты</div>
                            <div class="space-y-4">
                                <div class="flex items-center gap-3">
                                    <span class="text-sm opacity-70">Кнопка «Добавить» (моб.)</span>
                                    <AddMobileIcon />
                                </div>

                                <div class="flex items-center gap-3">
                                    <span class="text-sm opacity-70">Чекбокс</span>
                                    <Checkbox v-model:checked="demoChecked" />
                                    <span class="text-sm">{{ demoChecked ? 'Включён' : 'Выключен' }}</span>
                                </div>

                                <div class="flex items-center gap-3">
                                    <span class="text-sm opacity-70">Копирование адреса</span>
                                    <CopyAddress text="0x12ab34cd56ef7890" />
                                </div>

                                <div class="flex items-center gap-3">
                                    <span class="text-sm opacity-70">Таймер</span>
                                    <Clock ref="clockRef" :now="clockNow" :expires_at="clockExpires" />
                                </div>

                                <div class="flex items-center gap-3">
                                    <span class="text-sm opacity-70">CopyPaymentText</span>
                                    <CopyPaymentText :text="'Скопировать сумму'" :copy_text="demoCopyText" />
                                </div>

                                <div class="flex items-center gap-3">
                                    <span class="text-sm opacity-70">CopyUUID</span>
                                    <CopyUUID :text="demoUUID" />
                                </div>

                                <div class="flex items-center gap-3">
                                    <span class="text-sm opacity-70">DateTime</span>
                                    <DateTime :data="demoDate" :plural="true" />
                                </div>

                                <div class="flex items-center gap-3">
                                    <span class="text-sm opacity-70">DisplayID</span>
                                    <DisplayID :id="demoID" />
                                </div>

                                <div class="flex items-center gap-3">
                                    <span class="text-sm opacity-70">DisplayUUID</span>
                                    <DisplayUUID :uuid="demoUUID" />
                                </div>

                                <div class="flex items-center gap-3">
                                    <span class="text-sm opacity-70">DisputeStatus</span>
                                    <DisputeStatus status="accepted" />
                                    <DisputeStatus status="pending" />
                                    <DisputeStatus status="canceled" />
                                </div>

                                <div class="flex items-center gap-3">
                                    <span class="text-sm opacity-70">EmptyTable</span>
                                    <EmptyTable />
                                </div>

                                <div class="flex items-center gap-3">
                                    <span class="text-sm opacity-70">GatewayLogo</span>
                                    <GatewayLogo :img_path="''" name="Demo gateway" />
                                </div>

                                <div class="flex items-center gap-3">
                                    <span class="text-sm opacity-70">GoBackButton</span>
                                    <GoBackButton />
                                </div>

                                <div class="flex flex-col gap-2">
                                    <span class="text-sm opacity-70">InputLabel / InputHelper / InputError</span>
                                    <InputLabel :error="demoLabelError" value="Название поля" />
                                    <InputHelper v-model="demoHelperText" />
                                    <InputError :message="demoErrorText" />
                                </div>

                                <div class="flex items-center gap-3">
                                    <span class="text-sm opacity-70">InvoiceStatus</span>
                                    <InvoiceStatus status="success" />
                                    <InvoiceStatus status="pending" />
                                    <InvoiceStatus status="fail" />
                                </div>

                                <div class="flex items-center gap-3">
                                    <span class="text-sm opacity-70">IsActiveStatus</span>
                                    <IsActiveStatus :is_active="true" />
                                    <IsActiveStatus :is_active="false" />
                                </div>

                                <div class="flex items-center gap-3">
                                    <span class="text-sm opacity-70">NumberInput</span>
                                    <NumberInput v-model="demoNumber" :error="false" />
                                </div>

                                <div class="flex items-center gap-3">
                                    <span class="text-sm opacity-70">OrderStatus</span>
                                    <OrderStatus status="success" status_name="Завершена" />
                                    <OrderStatus status="pending" status_name="Ожидает" />
                                    <OrderStatus status="fail" status_name="Отклонена" />
                                </div>

                                <div class="flex items-center gap-3">
                                    <span class="text-sm opacity-70">PaymentDetail</span>
                                    <PaymentDetail type="card" :detail="'1234567812345678'" :short="true" />
                                    <PaymentDetail type="phone" :detail="'+79991234567'" :short="true" />
                                    <PaymentDetail type="account_number" :detail="'40817810099910004312'" :short="true" />
                                </div>

                                <div class="flex items-center gap-3">
                                    <span class="text-sm opacity-70">PaymentDetailLimit</span>
                                    <PaymentDetailLimit :current_daily_limit="dailyCurrent" :daily_limit="dailyLimit" />
                                </div>

                                <div class="flex items-center gap-3">
                                </div>

                                <div class="flex items-center gap-3">
                                    <span class="text-sm opacity-70">PrimaryButton</span>
                                    <PrimaryButton>Сохранить</PrimaryButton>
                                </div>

                                <div class="flex items-center gap-3">
                                    <span class="text-sm opacity-70">SecondaryButton</span>
                                    <SecondaryButton>Отмена</SecondaryButton>
                                </div>

                                <div class="flex items-center gap-6">
                                    <span class="text-sm opacity-70">ProgressNumber</span>
                                    <div class="w-64">
                                        <ProgressNumber :current="'30'" :total="'100'" />
                                    </div>
                                </div>

                                <div class="flex flex-col gap-2">
                                    <span class="text-sm opacity-70">SectionTitle</span>
                                    <SectionTitle>
                                        <template #title>Заголовок секции</template>
                                        <template #description>Описание секции</template>
                                        <template #aside>
                                            <button class="btn btn-xs">Действие</button>
                                        </template>
                                    </SectionTitle>
                                </div>

                                <div class="flex items-start gap-6">
                                    <span class="text-sm opacity-70 mt-2">Select</span>
                                    <div class="w-64">
                                        <Select
                                            v-model="selectValue"
                                            :items="selectItems"
                                            value="id"
                                            name="title"
                                            default_title="Выберите опцию"
                                        />
                                        <div class="text-xs opacity-70 mt-1">Текущее: {{ selectValue }}</div>
                                    </div>
                                </div>

                                <div class="flex items-start gap-6">
                                    <span class="text-sm opacity-70 mt-2">TextArea</span>
                                    <div class="w-full max-w-md">
                                        <TextArea v-model="demoTextarea" :rows="3" />
                                    </div>
                                </div>

                                <div class="flex items-start gap-6">
                                    <span class="text-sm opacity-70 mt-2">TextInput</span>
                                    <div class="w-full max-w-xs">
                                        <TextInput v-model="demoTextInput" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card bg-base-100 card-border shadow">
                        <div class="card-body">
                            <div class="font-semibold text-base mb-2 break-all">Фильтры (демо)</div>
                            <div class="space-y-3">
                                <FiltersPanel name="components-gallery-demo">
                                    <InputFilter name="id" placeholder="ID" />
                                    <InputFilter name="name" placeholder="Название" />
                                    <DropdownFilter name="status" title="Статус" />
                                    <DropdownFilter name="category" title="Категория" />
                                    <DateFilter name="date" title="Дата" />
                                    <FilterCheckbox name="onlyActive" title="Только активные" />
                                </FiltersPanel>
                                <div class="text-sm opacity-70">
                                    Это демонстрация внешнего вида фильтров. Кнопки применят фильтры, перезагрузив текущую страницу.
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card bg-base-100 card-border shadow">
                        <div class="card-body">
                            <div class="font-semibold text-base mb-2 break-all">Form (демо)</div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="md:col-span-2">
                                    <InputBlock :form="demoForm" field="baseInput" label="Базовый InputBlock" helper="Любая вложенная разметка">
                                        <TextInput
                                            id="baseInput"
                                            v-model="demoForm.data.baseInput"
                                            type="text"
                                            class="block w-full"
                                            placeholder="Пример вложенного поля"
                                            :error="!!demoForm.errors['baseInput']"
                                            @input="demoForm.clearErrors('baseInput')"
                                        />
                                    </InputBlock>
                                </div>
                                <TextInputBlock
                                    v-model="demoForm.data.title"
                                    :form="demoForm"
                                    field="title"
                                    label="Название"
                                    placeholder="Введите название"
                                    helper="Короткое описание поля"
                                />

                                <NumberInputBlock
                                    v-model="demoForm.data.amount"
                                    :form="demoForm"
                                    field="amount"
                                    label="Сумма"
                                    placeholder="0"
                                    helper="Только числа"
                                />

                                <div class="md:col-span-2">
                                    <label class="label pb-1"><span class="label-text">Мультивыбор</span></label>
                                    <Multiselect :options="demoMultiOptions" v-model="demoMulti" :enable-search="true" />
                                </div>

                                <div>
                                    <DropDownWithCheckbox
                                        v-model="selectedTimes"
                                        label="Время"
                                        :items="demoCheckboxItems"
                                        value="value"
                                        name="name"
                                    />
                                </div>
                                <div>
                                    <DropDownWithRadio
                                        v-model="selectedCurrency"
                                        label="Выберите валюту"
                                        :items="demoRadioItems"
                                        value="value"
                                        name="name"
                                    />
                                </div>

                                <div>
                                    <label class="label pb-1"><span class="label-text">Время</span></label>
                                    <TimepickerInput v-model="demoTime" />
                                </div>
                                <div>
                                    <label class="label pb-1"><span class="label-text">Файл</span></label>
                                    <Dropzone v-model="demoFile" title="Перетащите файл или кликните" />
                                </div>
                            </div>

                            <div class="mt-4">
                                <SaveButton :disabled="false" :saved="saveDone" />
                            </div>
                        </div>
                    </div>
                    <div class="card bg-base-100 card-border shadow">
                        <div class="card-body">
                            <div class="font-semibold text-base mb-2 break-all">Pagination</div>
                            <div class="space-y-6">
                                <div>
                                    <div class="mb-2 text-sm opacity-70">Базовый режим (pagination)</div>
                                    <div class="flex items-center gap-3">
                                        <Pagination
                                            v-model="pagPage"
                                            :total-items="totalItems"
                                            :per-page="perPage"
                                            layout="pagination"
                                            :enable-first-and-last-buttons="true"
                                            :show-icons="true"
                                        />
                                        <div class="text-sm opacity-70">Текущая страница: {{ pagPage }}</div>
                                    </div>
                                </div>

                                <div>
                                    <div class="mb-2 text-sm opacity-70">Navigation (только навигационные кнопки)</div>
                                    <div class="flex items-center gap-3">
                                        <Pagination
                                            v-model="navPage"
                                            :total-items="totalItems"
                                            :per-page="perPage"
                                            layout="navigation"
                                            :show-labels="true"
                                            :show-icons="true"
                                        />
                                        <div class="text-sm opacity-70">Текущая страница: {{ navPage }}</div>
                                    </div>
                                </div>

                                <div>
                                    <div class="mb-2 text-sm opacity-70">Table (с счётчиками элементов)</div>
                                    <div class="flex flex-col gap-2">
                                        <Pagination
                                            v-model="tablePage"
                                            :total-items="totalItems"
                                            :per-page="perPage"
                                            layout="table"
                                            :large="false"
                                        />
                                        <div class="text-sm opacity-70">Текущая страница: {{ tablePage }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card bg-base-100 card-border shadow">
                        <div class="card-body">
                            <div class="font-semibold text-base mb-2 break-all">Table components</div>
                            <div class="space-y-6">
                                <div class="flex items-center gap-4">
                                    <div class="text-sm opacity-70">Автообновление таблицы</div>
                                    <RefreshTableData
                                        @refreshStarted="isRefreshing = true"
                                        @refreshFinished="isRefreshing = false"
                                    />
                                    <div class="text-sm" :class="isRefreshing ? 'text-primary' : 'opacity-70'">
                                        {{ isRefreshing ? 'Обновление…' : 'Ожидание обновления' }}
                                    </div>
                                </div>

                                <div>
                                    <div class="mb-2 text-sm opacity-70">Действия строки таблицы (dropdown)</div>
                                    <TableActionsDropdown>
                                        <TableAction @click="() => {}">
                                            <div class="inline-flex items-center gap-2">
                                                <svg class="w-[18px] h-[18px]" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 6h14M5 18h14"/></svg>
                                                <span>Произвольное действие</span>
                                            </div>
                                        </TableAction>
                                        <TableAction @click="() => {}">
                                            <div class="inline-flex items-center gap-2 text-success">
                                                <svg class="w-[18px] h-[18px]" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 11.917 9.724 16.5 19 7.5"/></svg>
                                                <span>Успех</span>
                                            </div>
                                        </TableAction>
                                        <TableAction @click="() => {}">
                                            <div class="inline-flex items-center gap-2 text-error">
                                                <svg class="w-[18px] h-[18px]" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 17.94 6M18 18 6.06 6"/></svg>
                                                <span>Ошибка</span>
                                            </div>
                                        </TableAction>
                                    </TableActionsDropdown>
                                </div>

                                <div>
                                    <div class="mb-2 text-sm opacity-70">Иконки действий (inline)</div>
                                    <div class="flex items-center gap-2">
                                        <ShowAction link="#" />
                                        <EditAction link="#" />
                                        <SuccessAction />
                                        <FailAction />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>

</style>


