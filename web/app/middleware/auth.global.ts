import { useAuth } from '~/stores/auth'

// Reachable in every auth state: signed out, unverified, or signed in.
const OPEN = ['/privacy']
const PUBLIC = ['/login', '/register', '/forgot-password', '/reset-password']

export default defineNuxtRouteMiddleware(async (to) => {
    const auth = useAuth()

    if (!auth.loaded) {
        await auth.load()
    }

    if (OPEN.includes(to.path)) {
        return
    }

    const is_public = PUBLIC.includes(to.path)
    const is_verify = to.path === '/verify-email'

    if (!auth.user) {
        return is_public ? undefined : navigateTo('/login')
    }

    if (!auth.user.email_verified) {
        return is_verify ? undefined : navigateTo('/verify-email')
    }

    if (is_public || is_verify) {
        return navigateTo('/')
    }
})