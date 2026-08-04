<script setup lang="ts">
import { provideMonthlyStats } from '~/composables/dashboard/context/MonthlyStatsContext';

// One stats call for the whole screen; every region below reads slices of it.
provideMonthlyStats()
</script>

<template>
    <DashboardHeader />
    <DashboardMonthSwitcher />
    <DashboardTotalReceipt />
    <DashboardCategoryBreakdown />
    <DashboardDailySpending />
    <DashboardRecentItems />
</template>