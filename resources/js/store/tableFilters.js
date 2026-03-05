import {defineStore} from 'pinia'
import { toRaw } from 'vue'

export const useTableFiltersStore = defineStore('tableFilters', {
    state: () => {
        return {
            page: 1,
            per_page: 10,
            total: 10,
            tab: '',
            filters: {},
            filtersVariants: {},
        }
    },
    getters: {
        getCurrentPage: (state) => state.page,
        getPerPage: (state) => state.per_page,
        getTotal: (state) => state.total,
        getTab: (state) => state.tab,
        getFilters: (state) => state.filters,
        getFiltersVariants: (state) => state.filtersVariants,
        getQueryData: (state) => {
            return toRaw({
                page: state.page,
                per_page: state.per_page,
                tab: state.tab,
                filters: toRaw(state.filters)
            })
        }
    },
    actions: {
        setCurrentPage(current_page) {
            this.page = current_page;
        },
        setPerPage(per_page) {
            this.per_page = per_page;
        },
        setTotal(total) {
            this.total = total;
        },
        setTab(tab) {
            this.tab = tab;
        },
        setMeta(meta = null) {
            this.page = meta?.current_page ?? 1;
            this.per_page = meta?.per_page ?? 10;
            this.total = meta?.total ?? 10;
        },
        setFilters(filters) {
            this.filters = filters;
        },
        setFiltersVariants(filtersVariants = null) {
            this.filtersVariants = filtersVariants ?? {};
        }
    }
})
