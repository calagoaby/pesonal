<script setup lang="ts">
import { forgotPassword } from '~/repositories/auth'
import { useAuthForm } from '~/composables/auth/useAuthForm'

definePageMeta({ layout: 'auth' })

const { is_submitting, failure, submit, fieldError } = useAuthForm()


/** States */
const email = ref('')
const is_sent = ref(false)


/** Functions */
async function handleSubmit() {
    is_sent.value = await submit(() => forgotPassword(email.value))
}
</script>

<template>
    <SharedReceipt torn-position="top" sub-text="· lost and found ·">
        <SharedPerf />

        <AuthHeading title="Forgot it?">
            <template v-if="!is_sent">Give us your email and we'll send a reset slip.</template>
        </AuthHeading>

        <!-- Same message whether or not the email exists; the API doesn't say either. -->
        <AuthBanner v-if="is_sent" tone="ok">If it's on file, a reset slip is on its way.</AuthBanner>
        <AuthBanner v-if="failure">{{ failure }}</AuthBanner>

        <form novalidate @submit.prevent="handleSubmit">
            <AuthField
                v-model="email"
                label="Email"
                type="email"
                autocomplete="email"
                placeholder="you@example.com"
                :error="fieldError('email')"
                :disabled="is_sent"
                required
            />

            <AuthButton type="submit" :disabled="is_submitting || is_sent">Send reset slip</AuthButton>
        </form>

        <AuthSwitch><NuxtLink to="/login">Back to sign in</NuxtLink></AuthSwitch>

        <AuthFoot />
    </SharedReceipt>
</template>