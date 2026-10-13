<script setup lang="ts">
import { resendVerification } from '~/repositories/auth'
import { useAuth } from '~/stores/auth'
import { useAuthForm } from '~/composables/auth/useAuthForm'

definePageMeta({ layout: 'auth' })

const RESEND_COOLDOWN = 60

const auth = useAuth()
const { is_submitting, failure, submit } = useAuthForm()


/** States */
const cooldown = ref(0)
const has_resent = ref(false)
let timer: ReturnType<typeof setInterval> | undefined


/** Functions */
async function resend() {
    has_resent.value = await submit(() => resendVerification())

    if (has_resent.value) {
        startCooldown()
    }
}

function startCooldown() {
    cooldown.value = RESEND_COOLDOWN
    clearInterval(timer)
    timer = setInterval(() => {
        cooldown.value -= 1

        if (cooldown.value <= 0) {
            clearInterval(timer)
        }
    }, 1000)
}

async function signOut() {
    await auth.signOut()
    await navigateTo('/login')
}

onBeforeUnmount(() => clearInterval(timer))
</script>

<template>
    <SharedReceipt torn-position="top" sub-text="· one more step ·">
        <SharedPerf />

        <AuthHeading title="Check your inbox" />

        <AuthBanner v-if="has_resent" tone="ok">Sent again. Check spam, it's shy.</AuthBanner>
        <AuthBanner v-if="failure">{{ failure }}</AuthBanner>

        <p class="lede">
            We sent a link to <b>{{ auth.user?.email }}</b>. Click it and your ledger opens.
            <template v-if="!has_resent">Until then, it's just paper.</template>
        </p>

        <AuthButton :disabled="is_submitting || cooldown > 0" @click="resend">Resend the link</AuthButton>
        <div v-if="cooldown > 0" class="hint">Try again in {{ cooldown }} seconds.</div>

        <AuthButton variant="ghost" @click="signOut">Sign out</AuthButton>

        <AuthFoot />
    </SharedReceipt>
</template>

<style scoped>
.lede {
    margin-bottom: var(--space-l);
    font-family: var(--sans);
    font-size: var(--text-body);
    line-height: 1.5;
    color: var(--faded);

    b {
        color: var(--ink);
        font-weight: 600;
    }
}

.hint {
    margin-top: var(--space-xs);
    text-align: center;
    font-family: var(--sans);
    font-size: var(--text-label);
    color: var(--faded);
}
</style>