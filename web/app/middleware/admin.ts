import { useAuth } from '~/stores/auth'

// Runs after auth.global, so the user is already loaded. The API is the real gate (a
// non-admin gets 404); this only keeps everyone else off a screen they can't use.
export default defineNuxtRouteMiddleware(() => {
    if (!useAuth().user?.is_admin) {
        return navigateTo('/')
    }
})