<script setup>
import {Head, Link, router} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {ref} from "vue";
import TableActionsDropdown from "@/Components/Table/TableActionsDropdown.vue";
import TableAction from "@/Components/Table/TableAction.vue";
import ConfirmModal from "@/Components/Modals/ConfirmModal.vue";
import {useModalStore} from "@/store/modal.js";
import MainTableSection from "@/Wrappers/MainTableSection.vue";

const modalStore = useModalStore();
const props = defineProps({
    categories: Object,
});

const confirmDeleteCategory = (category) => {
    modalStore.openConfirmModal({
        title: 'Вы уверены что хотите удалить категорию "' + category.name + '"?',
        body: 'Это действие невозможно отменить. Все связи с мерчантами будут удалены.',
        confirm_button_name: 'Удалить',
        confirm: () => {
            router.delete(route('admin.categories.destroy', category.id), {
                preserveScroll: true
            });
        }
    });
};

defineOptions({ layout: AuthenticatedLayout })
</script>

<template>
    <div>
        <Head title="Категории"/>

        <MainTableSection
            title="Категории"
            :data="categories"
        >
            <template v-slot:button>
                <Link
                    :href="route('admin.categories.create')"
                    class="px-3 py-2 text-sm font-medium text-center text-white bg-blue-700 rounded-xl hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800"
                >
                    Добавить категорию
                </Link>
            </template>

            <template v-slot:body>
                <div class="relative overflow-x-auto shadow-md rounded-table">
                    <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                        <tr>
                            <th scope="col" class="px-6 py-3">
                                Название
                            </th>
                            <th scope="col" class="px-6 py-3">
                                Slug
                            </th>
                            <th scope="col" class="px-6 py-3">
                                Описание
                            </th>
                            <th scope="col" class="px-6 py-3 text-right">
                                <span class="sr-only">Действия</span>
                            </th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr v-for="category in categories.data" :key="category.id" class="bg-white border-b dark:bg-gray-800 dark:border-gray-700">
                            <th scope="row" class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                {{ category.name }}
                            </th>
                            <td class="px-6 py-4">
                                {{ category.slug }}
                            </td>
                            <td class="px-6 py-4">
                                {{ category.description }}
                            </td>
                            <td class="px-6 py-4 text-right relative">
                                <TableActionsDropdown>
                                    <TableAction @click="router.visit(route('admin.categories.edit', category.id))">
                                        Редактировать
                                    </TableAction>
                                    <TableAction @click="confirmDeleteCategory(category)">
                                        Удалить
                                    </TableAction>
                                </TableActionsDropdown>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </MainTableSection>

        <ConfirmModal/>
    </div>
</template>
