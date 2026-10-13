<script setup lang="ts">
import { useAuth } from '~/stores/auth'
import { useAuthForm } from '~/composables/auth/useAuthForm'

definePageMeta({ layout: 'auth' })

const auth = useAuth()
const route = useRoute()
const { is_submitting, failure, submit, fieldError } = useAuthForm()

// Where the social callback sends a failed round trip: /login?error=<reason>.
const OAUTH_MESSAGES: Record<string, string> = {
    cancelled: 'Sign-in cancelled. No harm done.',
    no_email: 'Facebook didn\'t share an email, so there\'s no ledger to open. Try Google or email instead.',
    failed: 'Sign-in didn\'t go through. Try again.'
}


/** States */
const email = ref('')
const password = ref('')


/** Computed */
// Own keys only: a plain object would answer `?error=constructor` with a function.
const oauth_error = computed(() => {
    const code = String(route.query.error)

    return Object.hasOwn(OAUTH_MESSAGES, code) ? OAUTH_MESSAGES[code] : ''
})


/** Functions */
async function handleSubmit() {
    const signed_in = await submit(() => auth.signIn({ email: email.value, password: password.value }))

    if (signed_in) {
        await navigateTo(auth.user?.email_verified ? '/' : '/verify-email')
    }
}
</script>

<template>
    <SharedReceipt torn-position="top" sub-text="· back to the counter ·">
        <SharedPerf />

        <AuthHeading title="Sign in">
            <template v-if="!oauth_error">Your receipts are waiting. Judgement is not.</template>
        </AuthHeading>

        <AuthBanner v-if="oauth_error">{{ oauth_error }}</AuthBanner>
        <AuthBanner v-if="failure">{{ failure }}</AuthBanner>

        <AuthSocialButtons />

        <form novalidate @submit.prevent="handleSubmit">
            <AuthField
                v-model="email"
                label="Email"
                type="email"
                autocomplete="email"
                placeholder="you@example.com"
                :error="fieldError('email')"
                required
            />
            <AuthField
                v-model="password"
                label="Password"
                type="password"
                autocomplete="current-password"
                placeholder="••••••••"
                :error="fieldError('password')"
                required
            />

            <div class="forgot">
                <NuxtLink to="/forgot-password">Forgot it?</NuxtLink>
            </div>

            <AuthButton type="submit" :disabled="is_submitting">Sign in</AuthButton>
        </form>

        <AuthSwitch>New here? <NuxtLink to="/register">Open a ledger</NuxtLink></AuthSwitch>

        <AuthFoot />
    </SharedReceipt>
</template>

<style scoped>
.forgot {
    margin-top: var(--space-s);
    text-align: right;

    a {
        font-family: var(--sans);
        font-size: var(--text-label);
        color: var(--faded);
        text-decoration: underline;
        text-underline-offset: 3px;
    }
}
</style>