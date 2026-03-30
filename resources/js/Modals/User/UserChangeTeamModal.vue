<script setup>
import Modal from "@/Components/Modals/Modal.vue";
import ModalHeader from "@/Components/Modals/Components/ModalHeader.vue";
import ModalBody from "@/Components/Modals/Components/ModalBody.vue";
import ModalFooter from "@/Components/Modals/Components/ModalFooter.vue";
import InputLabel from "@/Components/InputLabel.vue";
import InputError from "@/Components/InputError.vue";
import {useModalStore} from "@/store/modal.js";
import {storeToRefs} from "pinia";
import {ref, watch} from "vue";
import {router} from "@inertiajs/vue3";

const modalStore = useModalStore();
const {userTeamChangeModal} = storeToRefs(modalStore);

const loading = ref(false);
const processing = ref(false);
const teams = ref([]);
const user = ref(null);
const errors = ref({});

const form = ref({
    user_team_id: '',
});

const close = () => {
    modalStore.closeModal('userTeamChange');
};

const resetState = () => {
    loading.value = false;
    processing.value = false;
    teams.value = [];
    user.value = null;
    errors.value = {};
    form.value = {
        user_team_id: '',
    };
};

const loadTeams = () => {
    return axios.get(route('admin.user-teams.index'))
        .then((response) => {
            teams.value = response.data?.data || [];
        });
};

const submit = () => {
    if (!user.value) {
        return;
    }

    processing.value = true;
    errors.value = {};

    const payload = {
        user_team_id: form.value.user_team_id === '' ? null : Number(form.value.user_team_id),
    };

    axios.patch(route('admin.users.team.update', user.value.id), payload, {
        headers: {'Accept': 'application/json'},
    }).then(() => {
        close();
        router.reload({only: ['users']});
    }).catch((error) => {
        if (error.response?.data?.errors) {
            errors.value = error.response.data.errors;
        }
    }).finally(() => {
        processing.value = false;
    });
};

watch(
    () => userTeamChangeModal.value.showed,
    (state) => {
        if (state) {
            resetState();
            user.value = userTeamChangeModal.value.params?.user || null;
            form.value.user_team_id = user.value?.user_team?.id ?? '';
            loading.value = true;
            loadTeams().finally(() => {
                loading.value = false;
            });
        } else {
            resetState();
        }
    }
);
</script>

<template>
    <Modal :show="userTeamChangeModal.showed" @close="close" maxWidth="md">
        <ModalHeader @close="close" :title="user ? `Изменить команду: ${user.email}` : 'Изменить команду'" />
        <ModalBody>
            <div v-if="loading" class="py-6 text-center">
                <span class="loading loading-spinner loading-md"></span>
            </div>
            <div v-else class="space-y-4">
                <div>
                    <InputLabel for="user_team_id" value="Команда" :error="!!errors.user_team_id?.[0]" />
                    <select
                        id="user_team_id"
                        v-model="form.user_team_id"
                        class="select select-bordered w-full mt-1"
                        :disabled="processing"
                    >
                        <option value="">Без команды</option>
                        <option v-for="team in teams" :key="team.id" :value="team.id">
                            {{ team.name }} ({{ team.users_count || 0 }})
                        </option>
                    </select>
                    <InputError class="mt-2" :message="errors.user_team_id?.[0]" />
                </div>
            </div>
        </ModalBody>
        <ModalFooter>
            <button type="button" class="btn btn-sm" @click="close">Отмена</button>
            <button
                type="button"
                class="btn btn-sm btn-primary"
                :class="{ 'btn-disabled': processing }"
                :disabled="processing"
                @click="submit"
            >
                Сохранить
            </button>
        </ModalFooter>
    </Modal>
</template>
