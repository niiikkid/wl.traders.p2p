import {defineStore} from 'pinia'

export const useViewStore = defineStore('view', {
    state: () => {
        return {
            viewMode: null,
        }
    },
    getters: {
        isAdminViewMode: (state) => state.viewMode === 'admin',
        isTraderViewMode: (state) => state.viewMode === 'trader',
        isMerchantViewMode: (state) => state.viewMode === 'merchant',
        isTeamLeaderViewMode: (state) => state.viewMode === 'leader',
        isSupportViewMode: (state) => state.viewMode === 'support',
        isMerchantSupportViewMode: (state) => state.viewMode === 'merchant-support',
        adminPrefix: (state) => state.viewMode === 'admin' ? 'admin.' : '',
    },
    actions: {
        setAdminViewMode() {
            this.viewMode = 'admin';
        },
        setTraderViewMode() {
            this.viewMode = 'trader';
        },
        setMerchantViewMode() {
            this.viewMode = 'merchant';
        },
        setTeamLeaderViewMode() {
            this.viewMode = 'leader';
        },
        setSupportViewMode() {
            this.viewMode = 'support';
        },
        setMerchantSupportViewMode() {
            this.viewMode = 'merchant-support';
        },
    },
})
