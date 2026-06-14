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
        isProviderLiquidityViewMode: (state) => state.viewMode === 'provider-liquidity',
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
        setProviderLiquidityViewMode() {
            this.viewMode = 'provider-liquidity';
        },
    },
})
