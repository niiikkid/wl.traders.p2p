<script setup>
import {Head, router, useForm} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import SaveButton from '@/Components/Form/SaveButton.vue';
import SecondaryPageSection from "@/Wrappers/SecondaryPageSection.vue";
import TextInputBlock from "@/Components/Form/TextInputBlock.vue";

const form = useForm({
    name: '',
    description: '',
});

const submit = () => {
    form.post(route('admin.categories.store'), {
        preserveScroll: true,
    });
};

defineOptions({ layout: AuthenticatedLayout })
</script>

<template>
    <div>
        <Head title="Создание категории"/>

        <SecondaryPageSection
            :back-link="route('admin.categories.index')"
            title="Создание категории"
            description="Здесь вы можете создать новую категорию для мерчантов."
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
