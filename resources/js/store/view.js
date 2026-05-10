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
        isAnalystViewMode: (state) => state.viewMode === 'analyst',
        isProviderLiquidityViewMode: (state) => state.viewMode === 'provider-liquidity',
        isAgentViewMode: (state) => state.viewMode === 'agent',
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
        setAnalystViewMode() {
            this.viewMode = 'analyst';
        },
        setProviderLiquidityViewMode() {
            this.viewMode = 'provider-liquidity';
        },
        setAgentViewMode() {
            this.viewMode = 'agent';
        },
    },
})
