import type { LoginPayload, RegisterPayload, User } from "#shared/types/User";
import * as authRepository from "~/repositories/auth";

// Global, not tree-scoped: the signed-in user is read by the route guard and every screen,
// and survives navigation.
export const useAuth = defineStore('auth', () => {
    /** States */
    const user = ref<User | null>(null)
    const loaded = ref(false)


    /** Functions */
    async function load() {
        user.value = await authRepository.fetchUser()
        loaded.value = true
    }

    async function signIn(payload: LoginPayload) {
        clearNuxtData()
        user.value = await authRepository.login(payload)
        loaded.value = true
    }

    async function signUp(payload: RegisterPayload) {
        clearNuxtData()
        user.value = await authRepository.register(payload)
        loaded.value = true
    }

    async function signOut() {
        try {
            await authRepository.logout()
        } catch {
            // A 401 just means already signed out; any other failure is swallowed too,
            // because the local sign-out below has to happen regardless.
        } finally {
            clearNuxtData()
            user.value = null
        }
    }

    function clear() {
        user.value = null
        loaded.value = true
    }


    return {
        user,
        loaded,

        load,
        signIn,
        signUp,
        signOut,
        clear
    }
})