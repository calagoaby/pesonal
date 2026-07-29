<template>
    <div class="screen">
        <DashboardHeader />
        <DashboardMonthSwitcher />
        <DashboardTotalReceipt />
        <DashboardCategoryBreakdown />
        <DashboardDailySpending />
        <DashboardRecentItems />
    </div>
</template>

<style scoped>
.screen {
    padding: var(--space-m) var(--space-l) 0;
}
</style>