<script setup>
import {Head, router, useForm, usePage} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import SaveButton from '@/Components/Form/SaveButton.vue';
import SecondaryPageSection from "@/Wrappers/SecondaryPageSection.vue";
import TextInputBlock from "@/Components/Form/TextInputBlock.vue";

const props = defineProps({
    category: Object,
});

const form = useForm({
    name: props.category?.name || '',
    description: props.category?.description || '',
});

const submit = () => {
    form.patch(route('admin.categories.update', props.category.id), {
        preserveScroll: true,
    });
};

defineOptions({ layout: AuthenticatedLayout })
</script>

<template>
    <div>
        <Head title="Редактирование категории"/>

        <SecondaryPageSection
            :back-link="route('admin.categories.index')"
            title="Редактирование категории"
            description="Здесь вы можете изменить информацию о категории."
        >
            <form @submit.prevent="submit" class="mt-6 space-y-6">
                <TextInputBlock
                    v-model="form.name"
                    :form="form"
                    field="name"
                    label="Название"
                    placeholder="Введите название категории"
                />

                <TextInputBlock
                    v-model="form.description"
                    :form="form"
                    field="description"
                    label="Описание"
                    placeholder="Введите описание категории"
                />

                <SaveButton :disabled="form.processing" :saved="form.recentlySuccessful"/>
            </form>
        </SecondaryPageSection>
    </div>
</template>
