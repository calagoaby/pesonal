<script setup lang="ts">
import { resetPassword } from '~/repositories/auth'
import { useAuthForm } from '~/composables/auth/useAuthForm'
import { useToaster } from '~/stores/toaster'

definePageMeta({ layout: 'auth' })

const route = useRoute()
const toaster = useToaster()
const { is_submitting, failure, errors, submit, fieldError } = useAuthForm()


/** States */
const password = ref('')
const password_confirmation = ref('')


/** Computed */
// The emailed link carries both; neither has a field here, so their errors surface as a banner.
const token = computed(() => String(route.query.token ?? ''))
const email = computed(() => String(route.query.email ?? ''))
const is_link_broken = computed(() => !token.value || !email.value)
const link_error = computed(() => errors.value.email?.[0] ?? errors.value.token?.[0] ?? '')


/** Functions */
async function handleSubmit() {
    const changed = await submit(() => resetPassword({
        token: token.value,
        email: email.value,
        password: password.value,
        password_confirmation: password_confirmation.value
    }))

    if (changed) {
        toaster.show({ message: 'Password changed. Sign in with the new one.' })
        await navigateTo('/login')
    }
}
</script>

<template>
    <SharedReceipt torn-position="top" sub-text="· reissue ·">
        <SharedPerf />

        <AuthHeading title="New password">Pick one you'll remember this time.</AuthHeading>

        <AuthBanner v-if="is_link_broken">
            That reset link is broken. Ask for a new one.
            <NuxtLink to="/forgot-password" class="new-link">Get a new link</NuxtLink>
        </AuthBanner>
        <AuthBanner v-if="link_error">{{ link_error }}</AuthBanner>
        <AuthBanner v-if="failure">{{ failure }}</AuthBanner>

        <form v-if="!is_link_broken" novalidate @submit.prevent="handleSubmit">
            <AuthField
                v-model="password"
                label="Password"
                type="password"
                autocomplete="new-password"
                placeholder="••••••••"
                hint="At least 8 characters."
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

            <AuthButton type="submit" :disabled="is_submitting">Save password</AuthButton>
        </form>

        <AuthSwitch><NuxtLink to="/login">Back to sign in</NuxtLink></AuthSwitch>

        <AuthFoot />
    </SharedReceipt>
</template>

<style scoped>
.new-link {
    font-weight: 600;
    color: inherit;
    text-decoration: underline;
    text-underline-offset: 3px;
}
</style>