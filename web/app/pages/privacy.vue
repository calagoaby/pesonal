<script setup lang="ts">
import ArrowLeft from '@primeicons/vue/arrow-left'
import { useAuth } from '~/stores/auth'

definePageMeta({ layout: 'auth' })

const auth = useAuth()
const router = useRouter()
const { public: { contactEmail } } = useRuntimeConfig()


/** Functions */
function goBack() {
    // history.state.back is null on a direct visit or a fresh tab; there is nothing to go back to.
    if (import.meta.client && history.state?.back) {
        router.back()
        return
    }

    navigateTo(auth.user ? '/' : '/login')
}
</script>

<template>
    <SharedReceipt torn-position="top" sub-text="· the fine print ·">
        <SharedPerf />

        <a href="/" class="back" @click.prevent="goBack">
            <ArrowLeft class="back-icon" />
            Back
        </a>

        <AuthHeading title="Privacy">The short version, in plain words.</AuthHeading>

        <section class="psec">
            <div class="shdr">What we keep</div>
            <ul>
                <li>Your name and email</li>
                <li>Your profile picture, if you sign in with Google or Facebook</li>
                <li>Your expenses, standing orders and categories</li>
            </ul>
        </section>

        <section class="psec">
            <div class="shdr">Who sees it</div>
            <p>Only you. Each ledger is private. Nothing is sold or shared.</p>
        </section>

        <section class="psec">
            <div class="shdr">Sign-in providers</div>
            <p>Google and Facebook only confirm who you are. They never see your ledger.</p>
        </section>

        <section class="psec">
            <div class="shdr">Deleting it</div>
            <p>More → Delete my account removes everything at once. Or email us and we'll do it for you.</p>
        </section>

        <section class="psec">
            <div class="shdr">Contact</div>
            <a v-if="contactEmail" class="addr" :href="`mailto:${contactEmail}`">{{ contactEmail }}</a>
            <p v-else>Reply to the invite that got you here. Same inbox, same deadpan.</p>
        </section>

        <AuthFoot />
    </SharedReceipt>
</template>

<style scoped>
.back {
    display: inline-flex;
    align-items: center;
    gap: var(--space-xs);
    margin-top: var(--space-s);
    font-family: var(--sans);
    font-size: var(--text-label);
    color: var(--faded);
    text-decoration: none;
}

.back :deep(.back-icon) {
    width: 12px;
    height: 12px;
}

.psec {
    margin-top: var(--space-l);

    p,
    li {
        font-family: var(--sans);
        font-size: var(--text-body);
        line-height: 1.5;
    }

    ul {
        padding: 0;
        list-style: none;
        border-top: 1.5px dashed var(--perforation);
    }
}

.shdr {
    margin-bottom: var(--space-xs);
    font-family: var(--mono);
    font-size: var(--text-micro);
    letter-spacing: .1em;
    text-transform: uppercase;
    color: var(--faded);
}

.addr {
    display: inline-block;
    margin-top: var(--space-xs);
    padding: var(--space-s) var(--space-m);
    font-family: var(--mono);
    font-size: var(--text-body);
    color: var(--stamp);
    border: 1.5px dashed var(--stamp);
    border-radius: var(--radius-card);
    text-decoration: none;
}
</style>