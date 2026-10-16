<script setup lang="ts">
import { useAuth } from '~/stores/auth'
import { useAuthForm } from '~/composables/auth/useAuthForm'

definePageMeta({ layout: 'auth' })

const auth = useAuth()
const { is_submitting, failure, submit, fieldError } = useAuthForm()


/** States */
const name = ref('')
const email = ref('')
const password = ref('')
const password_confirmation = ref('')


/** Functions */
async function handleSubmit() {
    const signed_up = await submit(() => auth.signUp({
        name: name.value,
        email: email.value,
        password: password.value,
        password_confirmation: password_confirmation.value
    }))

    if (signed_up) {
        await navigateTo(auth.user?.email_verified ? '/' : '/verify-email')
    }
}
</script>

<template>
    <SharedReceipt torn-position="top" sub-text="· new account ·">
        <SharedPerf />

        <AuthHeading title="Open a ledger">
            One ledger per person. Yours starts empty, like your wallet soon will.
        </AuthHeading>

        <AuthBanner v-if="failure">{{ failure }}</AuthBanner>

        <AuthSocialButtons />

        <form novalidate @submit.prevent="handleSubmit">
            <AuthField
                v-model="name"
                label="Name"
                autocomplete="name"
                placeholder="Juan dela Cruz"
                :error="fieldError('name')"
                required
            />
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
                autocomplete="new-password"
                placeholder="••••••••"
                hint="At least 8 characters. We won't judge the 8."
                :error="fieldError('password')"
                required
            />
            <AuthField
                v-model="password_confirmation"
                label="Confirm password"
                type="password"
                autocomplete="new-password"
                placeholder="••••••••"
                :error="fieldError('password_confirmation')"
                required
            />

            <AuthButton type="submit" :disabled="is_submitting">Open a ledger</AuthButton>
        </form>

        <AuthSwitch>Already have one? <NuxtLink to="/login">Sign in</NuxtLink></AuthSwitch>

        <AuthSwitch class="faded"><NuxtLink to="/privacy">Privacy</NuxtLink></AuthSwitch>

        <AuthFoot />
    </SharedReceipt>
</template>