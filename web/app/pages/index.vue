<script setup lang="ts">
import ExclamationTriangle from '@primeicons/vue/exclamation-triangle';
import Receipt from '@primeicons/vue/receipt';
import { provideMonthlyStats } from '~/composables/dashboard/context/MonthlyStatsContext';

// One stats call for the whole screen; every region below reads slices of it.
const {
    status,
    error,
    transaction_count,
    refresh
} = provideMonthlyStats()


/** Computed */
const has_nothing = computed(() =>
    !error.value && status.value !== 'pending' && transaction_count.value === 0
)
</script>

<template>
    <DashboardHeader />
    <DashboardMonthSwitcher />

    <SharedNotice v-if="error" title="Printer jammed" tone="alert">
        <template #mark>
            <ExclamationTriangle />
        </template>
        Couldn't reach the server.<br>
        Your entries are safe — this screen just came up blank.
        <template #action>
            <button class="act" @click="refresh()">Try again</button>
        </template>
    </SharedNotice>

    <SharedNotice v-else-if="has_nothing" title="Nothing to total">
        <template #mark>
            <Receipt />
        </template>
        No entries this month.<br>
        A rare and beautiful thing.
        <template #action>
            <NuxtLink to="/add-expense" class="act">Record one</NuxtLink>
        </template>
    </SharedNotice>

    <template v-else>
        <DashboardTotalReceipt />
        <DashboardCategoryBreakdown />
        <DashboardDailySpending />
        <DashboardRecentItems />
    </template>
</template>

<style scoped>
.act {
    display: inline-block;
    background-color: var(--stamp);
    color: var(--paper);
    border: 0;
    border-radius: var(--radius-pill);
    padding: var(--space-s) var(--space-l);
    font-family: var(--mono);
    font-size: var(--text-label);
    font-weight: 600;
    letter-spacing: .12em;
    text-transform: uppercase;
    text-decoration: none;
    cursor: pointer;
}
</style>