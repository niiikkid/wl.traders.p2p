import { ref } from 'vue';

const categories = ref([]);
const merchantTrafficCategoriesEnabled = ref(false);
const loading = ref(false);
const loaded = ref(false);

export const useTraderTrafficCategories = () => {
    const fetchCategories = async (force = false) => {
        if (loaded.value && !force) {
            return {
                categories: categories.value,
                merchant_traffic_categories_enabled: merchantTrafficCategoriesEnabled.value,
            };
        }

        loading.value = true;

        try {
            const response = await axios.get(route('traffic-categories.index'), {
                headers: { Accept: 'application/json' },
            });
            const data = response.data?.data || {};

            categories.value = data.categories || [];
            merchantTrafficCategoriesEnabled.value = Boolean(data.merchant_traffic_categories_enabled);
            loaded.value = true;

            return {
                categories: categories.value,
                merchant_traffic_categories_enabled: merchantTrafficCategoriesEnabled.value,
            };
        } finally {
            loading.value = false;
        }
    };

    const setCategoryEnabled = async (categoryId, enabled) => {
        const response = await axios.patch(
            route('traffic-categories.enabled.update', categoryId),
            { enabled },
            { headers: { Accept: 'application/json' } },
        );

        const updatedCategory = response.data?.data?.category;

        if (updatedCategory) {
            const index = categories.value.findIndex(
                (category) => Number(category.id) === Number(updatedCategory.id),
            );

            if (index !== -1) {
                categories.value[index] = updatedCategory;
            }
        }

        return updatedCategory;
    };

    const invalidateCategories = () => {
        loaded.value = false;
    };

    return {
        categories,
        merchantTrafficCategoriesEnabled,
        loading,
        loaded,
        fetchCategories,
        setCategoryEnabled,
        invalidateCategories,
    };
};
