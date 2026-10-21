<script setup lang="ts">
import Tags from '@primeicons/vue/tags';
import Sync from '@primeicons/vue/sync';
import ChartLine from '@primeicons/vue/chart-line';
import Download from '@primeicons/vue/download';
import Upload from '@primeicons/vue/upload';
import InfoCircle from '@primeicons/vue/info-circle';
import SignOut from '@primeicons/vue/sign-out';
import Trash from '@primeicons/vue/trash';
import UserPlus from '@primeicons/vue/user-plus';
import ChevronRight from '@primeicons/vue/chevron-right';
import { listCategories } from '~/repositories/categories';
import { listRecurring } from '~/repositories/recurring';
import { useAuth } from '~/stores/auth';
import { useToaster } from '~/stores/toaster';

const auth = useAuth()
const toaster = useToaster()

// Both reads share the keys their own screens own, so landing here warms those
// caches instead of paying for a second fetch. A failed count must not break the
// hub — it is a signpost, not a data screen — so neither error is surfaced.
const { data: categories } = await useAsyncData('categories', () => listCategories())
const { data: recurring } = await useAsyncData('recurring', () => listRecurring())


/** States */
const is_delete_slip_open = ref(false)
const is_deleting = ref(false)

/** Computed */
const category_count = computed(() => categories.value?.length ?? 0)
const template_count = computed(() => recurring.value?.length ?? 0)
const active_count = computed(() => (recurring.value ?? []).filter(item => item.active).length)


/** Functions */
async function signOut() {
    await auth.signOut()
    await navigateTo('/login')
}

async function confirmDelete() {
    if (is_deleting.value) {
        return
    }

    is_deleting.value = true

    try {
        await auth.deleteAccount()
        is_delete_slip_open.value = false
        toaster.show({ message: 'Account deleted. The receipt has been shredded.' })
        await navigateTo('/login')
    } catch (error) {
        is_delete_slip_open.value = false

        // A 401 was already cleared and sent to /login by the transport.
        if (!(error instanceof ApiRequestError && error.status === 401)) {
            toaster.show({ message: "Couldn't delete the account. Nothing was removed." })
        }
    } finally {
        is_deleting.value = false
    }
}
</script>

<template>
    <SharedReceipt class="more-roll" torn-position="top" sub-text="· back of the receipt ·">
        <SharedPerf />

        <div class="section">Manage</div>

        <NuxtLink to="/categories" class="destination">
            <Tags class="lead-icon" />
            <span class="name">
                Categories
                <span class="sub">{{ category_count }} in use · colors and emoji</span>
            </span>
            <ChevronRight class="chevron" />
        </NuxtLink>

        <NuxtLink to="/recurring" class="destination">
            <Sync class="lead-icon" />
            <span class="name">
                Recurring
                <span class="sub">{{ template_count }} standing orders · {{ active_count }} active</span>
            </span>
            <ChevronRight class="chevron" />
        </NuxtLink>

        <div class="section">Insights</div>

        <NuxtLink to="/trend" class="destination">
            <ChartLine class="lead-icon" />
            <span class="name">
                Spending trend
                <span class="sub">Last 6 months, in one line</span>
            </span>
            <ChevronRight class="chevron" />
        </NuxtLink>

        <div class="section">Data</div>

        <div class="destination">
            <Download class="lead-icon" />
            <span class="name">
                Export to CSV
                <span class="sub">Take your damage elsewhere</span>
            </span>
            <span class="soon">Soon</span>
        </div>

        <div class="destination">
            <Upload class="lead-icon" />
            <span class="name">
                Import
                <span class="sub">Bulk-add past regrets</span>
            </span>
            <span class="soon">Soon</span>
        </div>

        <div class="section">About</div>

        <NuxtLink to="/privacy" class="destination">
            <InfoCircle class="lead-icon" />
            <span class="name">
                About &amp; privacy
                <span class="sub">What we keep, who sees it</span>
            </span>
            <ChevronRight class="chevron" />
        </NuxtLink>

        <div class="section">Account</div>

        <button type="button" class="destination" @click="signOut">
            <SignOut class="lead-icon" />
            <span class="name">
                Sign out
                <span class="sub">Close the ledger for now</span>
            </span>
        </button>

        <button type="button" class="destination" @click="is_delete_slip_open = true">
            <Trash class="lead-icon danger" />
            <span class="name">
                Delete my account
                <span class="sub">Shred the whole roll</span>
            </span>
            <ChevronRight class="chevron" />
        </button>

        <template v-if="auth.user?.is_admin">
            <div class="section">Admin</div>

            <NuxtLink to="/admin/invites" class="destination">
                <UserPlus class="lead-icon" />
                <span class="name">
                    Invites
                    <span class="sub">Who gets a ledger</span>
                </span>
                <ChevronRight class="chevron" />
            </NuxtLink>
        </template>

        <div class="foot">
            <SharedBarcode class="roll-barcode" gap="5px" height="36px" />
            <div class="count">Pesonal · v1.0</div>
            <div class="quip">* keep this receipt for your records *</div>
        </div>
    </SharedReceipt>

    <SharedVoidSlip
        v-model:open="is_delete_slip_open"
        title="Delete account"
        subject="Your whole ledger"
        :detail="auth.user?.email"
        warning="Every expense, standing order and category goes with it. No undo, no reprint."
        confirm-label="Delete everything"
        @confirm="confirmDelete"
    />
</template>

<style scoped>
.more-roll {
    margin-bottom: var(--space-l);
}

.section {
    font-family: var(--mono);
    font-size: var(--text-micro);
    letter-spacing: .1em;
    text-transform: uppercase;
    color: var(--faded);
    margin: var(--space-l) 0 var(--space-xs);
}

.destination {
    display: flex;
    align-items: center;
    gap: var(--space-m);
    padding: var(--space-m) 2px;
    border-top: 1.5px dashed var(--perforation);
    color: var(--ink);
    text-decoration: none;
}

button.destination {
    width: 100%;
    border-right: 0;
    border-bottom: 0;
    border-left: 0;
    background: transparent;
    text-align: left;
    cursor: pointer;
}

.name {
    flex: 1;
    font-family: var(--sans);
    font-size: var(--text-body);
}

.sub {
    display: block;
    font-family: var(--sans);
    font-size: var(--text-label);
    color: var(--faded);
    margin-top: 2px;
}

.soon {
    font-family: var(--mono);
    font-size: 9px;
    letter-spacing: .1em;
    text-transform: uppercase;
    color: var(--faded);
    border: 1px solid var(--perforation);
    border-radius: var(--radius-pill);
    padding: 2px var(--space-s);
}

.destination :deep(.lead-icon) {
    width: 16px;
    height: 16px;
    color: var(--faded);
    flex: none;
}

.destination :deep(.lead-icon.danger) {
    color: var(--stamp);
}

.destination :deep(.chevron) {
    width: 12px;
    height: 12px;
    color: var(--perforation);
    flex: none;
}

.foot {
    text-align: center;
    margin-top: var(--space-xl);
}

.roll-barcode {
    margin: var(--space-s) auto;
}

.count {
    font-family: var(--mono);
    font-size: var(--text-micro);
    letter-spacing: .14em;
    text-transform: uppercase;
    color: var(--faded);
}

.quip {
    font-family: var(--mono);
    font-size: 9px;
    letter-spacing: .18em;
    text-transform: uppercase;
    color: var(--perforation);
    margin-top: var(--space-s);
}
</style>