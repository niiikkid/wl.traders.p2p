import { ref } from 'vue';

const categories = ref([]);
const merchantTrafficCategoriesEnabled = ref(false);
const loading = ref(false);
const loaded = ref(false);

export const useMerchantTrafficCategories = () => {
    const fetchCategories = async (force = false) => {
        if (loaded.value && !force) {
            return {
                categories: categories.value,
                merchant_traffic_categories_enabled: merchantTrafficCategoriesEnabled.value,
            };
        }

        loading.value = true;

        try {
            const response = await axios.get(route('admin.traffic-categories.index'), {
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

    const invalidateCategories = () => {
        loaded.value = false;
    };

    const categoryOptions = () => {
        return (categories.value || []).map((category) => ({
            id: category.id,
            name: category.name,
        }));
    };

    return {
        categories,
        merchantTrafficCategoriesEnabled,
        loading,
        loaded,
        fetchCategories,
        invalidateCategories,
        categoryOptions,
    };
};
