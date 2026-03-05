import {defineStore} from 'pinia'
import {usePage} from "@inertiajs/vue3";

export const useUserStore = defineStore('user', {
    getters: {
        isAdmin: (state) => usePage().props.auth.is_admin,
    },
})
