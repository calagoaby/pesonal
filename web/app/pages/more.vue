<script setup lang="ts">
import Tags from '@primeicons/vue/tags';
import Sync from '@primeicons/vue/sync';
import ChartLine from '@primeicons/vue/chart-line';
import Download from '@primeicons/vue/download';
import Upload from '@primeicons/vue/upload';
import InfoCircle from '@primeicons/vue/info-circle';
import SignOut from '@primeicons/vue/sign-out';
import ChevronRight from '@primeicons/vue/chevron-right';
import { listCategories } from '~/repositories/categories';
import { listRecurring } from '~/repositories/recurring';
import { useAuth } from '~/stores/auth';

const auth = useAuth()

// Both reads share the keys their own screens own, so landing here warms those
// caches instead of paying for a second fetch. A failed count must not break the
// hub — it is a signpost, not a data screen — so neither error is surfaced.
const { data: categories } = await useAsyncData('categories', () => listCategories())
const { data: recurring } = await useAsyncData('recurring', () => listRecurring())


/** Computed */
const category_count = computed(() => categories.value?.length ?? 0)
const template_count = computed(() => recurring.value?.length ?? 0)
const active_count = computed(() => (recurring.value ?? []).filter(item => item.active).length)


/** Functions */
async function signOut() {
    await auth.signOut()
    await navigateTo('/login')
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

        <div class="destination">
            <InfoCircle class="lead-icon" />
            <span class="name">
                About Pesonal
                <span class="sub">v1.0 · one user, no auth, no judgment</span>
            </span>
            <span class="soon">Soon</span>
        </div>

        <div class="section">Account</div>

        <button type="button" class="destination" @click="signOut">
            <SignOut class="lead-icon" />
            <span class="name">
                Sign out
                <span class="sub">Close the ledger for now</span>
            </span>
        </button>

        <div class="foot">
            <SharedBarcode class="roll-barcode" gap="5px" height="36px" />
            <div class="count">Pesonal · v1.0</div>
            <div class="quip">* keep this receipt for your records *</div>
        </div>
    </SharedReceipt>
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