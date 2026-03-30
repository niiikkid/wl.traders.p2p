<script setup>
import Modal from "@/Components/Modals/Modal.vue";
import ModalHeader from "@/Components/Modals/Components/ModalHeader.vue";
import ModalBody from "@/Components/Modals/Components/ModalBody.vue";
import ModalFooter from "@/Components/Modals/Components/ModalFooter.vue";
import TextInput from "@/Components/TextInput.vue";
import InputError from "@/Components/InputError.vue";
import {useModalStore} from "@/store/modal.js";
import {storeToRefs} from "pinia";
import {ref, watch} from "vue";
import {router} from "@inertiajs/vue3";

const modalStore = useModalStore();
const {userTeamManageModal} = storeToRefs(modalStore);

const loading = ref(false);
const createProcessing = ref(false);
const updateProcessingIds = ref({});
const deleteProcessingIds = ref({});

const createForm = ref({
    name: '',
});

const createErrors = ref({});
const updateErrors = ref({});
const teams = ref([]);

const close = () => {
    modalStore.closeModal('userTeamManage');
};

const resetState = () => {
    loading.value = false;
    createProcessing.value = false;
    updateProcessingIds.value = {};
    deleteProcessingIds.value = {};
    createForm.value = { name: '' };
    createErrors.value = {};
    updateErrors.value = {};
    teams.value = [];
};

const setUpdateProcessing = (id, state) => {
    updateProcessingIds.value = {
        ...updateProcessingIds.value,
        [id]: state,
    };
};

const setDeleteProcessing = (id, state) => {
    deleteProcessingIds.value = {
        ...deleteProcessingIds.value,
        [id]: state,
    };
};

const loadTeams = () => {
    loading.value = true;
    axios.get(route('admin.user-teams.index'))
        .then((response) => {
            const list = response.data?.data || [];
            teams.value = list.map((team) => ({
                id: team.id,
                name: team.name,
                users_count: team.users_count || 0,
            }));
        })
        .finally(() => {
            loading.value = false;
        });
};

const createTeam = () => {
    createProcessing.value = true;
    createErrors.value = {};

    axios.post(route('admin.user-teams.store'), createForm.value, {
        headers: {'Accept': 'application/json'},
    }).then(() => {
        createForm.value.name = '';
        loadTeams();
        router.reload({only: ['users']});
    }).catch((error) => {
        if (error.response?.data?.errors) {
            createErrors.value = error.response.data.errors;
        }
    }).finally(() => {
        createProcessing.value = false;
    });
};

const updateTeam = (team) => {
    setUpdateProcessing(team.id, true);
    updateErrors.value = {...updateErrors.value, [team.id]: {}};

    axios.patch(route('admin.user-teams.update', team.id), {
        name: team.name,
    }, {
        headers: {'Accept': 'application/json'},
    }).then((response) => {
        const updated = response.data?.data;
        if (updated) {
            team.users_count = updated.users_count || 0;
        }
        router.reload({only: ['users']});
    }).catch((error) => {
        if (error.response?.data?.errors) {
            updateErrors.value = {
                ...updateErrors.value,
                [team.id]: error.response.data.errors,
            };
        }
    }).finally(() => {
        setUpdateProcessing(team.id, false);
    });
};

const confirmDeleteTeam = (team) => {
    modalStore.openConfirmModal({
        title: `Удалить команду "${team.name}"?`,
        body: 'У пользователей этой команды поле команды будет очищено.',
        confirm_button_name: 'Удалить',
        confirm: () => {
            setDeleteProcessing(team.id, true);
            axios.delete(route('admin.user-teams.destroy', team.id), {
                headers: {'Accept': 'application/json'},
            }).then(() => {
                teams.value = teams.value.filter((item) => item.id !== team.id);
                router.reload({only: ['users']});
            }).finally(() => {
                setDeleteProcessing(team.id, false);
            });
        },
    });
};

watch(
    () => userTeamManageModal.value.showed,
    (state) => {
        if (state) {
            resetState();
            loadTeams();
        } else {
            resetState();
        }
    }
);
</script>

<template>
    <Modal :show="userTeamManageModal.showed" @close="close" maxWidth="2xl">
        <ModalHeader @close="close" title="Команды пользователей" />
        <ModalBody>
            <div class="rounded-box border border-base-300 p-4 mb-4">
                <div class="text-sm font-medium mb-2">Добавить команду</div>
                <div class="flex flex-col md:flex-row md:items-center gap-2">
                    <TextInput
                        v-model="createForm.name"
                        type="text"
                        class="w-full input-sm"
                        placeholder="Название команды"
                        maxlength="255"
                        :disabled="createProcessing"
                    />
                    <button
                        type="button"
                        class="btn btn-primary btn-sm"
                        :class="{ 'btn-disabled': createProcessing }"
                        :disabled="createProcessing"
                        @click="createTeam"
                    >
                        Добавить
                    </button>
                </div>
                <InputError class="mt-2" :message="createErrors.name?.[0]" />
            </div>

            <div v-if="loading" class="py-6 text-center">
                <span class="loading loading-spinner loading-md"></span>
            </div>

            <div v-else-if="!teams.length" class="text-center text-sm text-base-content/70 py-6">
                Команды пока не созданы
            </div>

            <div v-else class="space-y-3">
                <div
                    v-for="team in teams"
                    :key="team.id"
                    class="rounded-box border border-base-300 p-3"
                >
                    <div class="text-xs text-base-content/70 mb-2">
                        В команде: <span class="badge badge-soft badge-primary">{{ team.users_count }}</span>
                    </div>
                    <div class="flex flex-col md:flex-row md:items-center gap-2">
                        <div class="w-full md:flex-1">
                            <TextInput
                                v-model="team.name"
                                type="text"
                                class="w-full input-sm"
                                maxlength="255"
                                :disabled="updateProcessingIds[team.id] || deleteProcessingIds[team.id]"
                            />
                            <InputError class="mt-2" :message="updateErrors[team.id]?.name?.[0]" />
                        </div>
                        <div class="flex items-center gap-2 md:flex-shrink-0">
                            <button
                                type="button"
                                class="btn btn-outline btn-square btn-sm"
                                :class="{ 'btn-disabled': updateProcessingIds[team.id] || deleteProcessingIds[team.id] }"
                                :disabled="updateProcessingIds[team.id] || deleteProcessingIds[team.id]"
                                @click="updateTeam(team)"
                                title="Сохранить"
                                aria-label="Сохранить"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                            </button>
                            <button
                                type="button"
                                class="btn btn-outline btn-error btn-square btn-sm"
                                :class="{ 'btn-disabled': updateProcessingIds[team.id] || deleteProcessingIds[team.id] }"
                                :disabled="updateProcessingIds[team.id] || deleteProcessingIds[team.id]"
                                @click="confirmDeleteTeam(team)"
                                title="Удалить"
                                aria-label="Удалить"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </ModalBody>
        <ModalFooter>
            <button type="button" class="btn btn-sm" @click="close">Закрыть</button>
        </ModalFooter>
    </Modal>
</template>
