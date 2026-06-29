<template>
    <div class="screen">
        <DashboardHeader />
        <DashboardMonthSwitcher />
    </div>
</template>

<style scoped>
.screen {
    /* 16px gutter via the design token, not a body margin. */
    padding: var(--space-m) var(--space-l) 0;
}
</style>