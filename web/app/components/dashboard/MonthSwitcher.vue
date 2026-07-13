<template>
    <div class="month-switcher">
        <div class="period">
            <i class="pi pi-chevron-left"></i>
            <span>June 2026</span>
            <i class="pi pi-chevron-right"></i>
        </div>
        <div class="text">
            printed just now
        </div>
    </div>
</template>

<style scoped>
.month-switcher {
    margin: 14px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.period {
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-family: var(--mono);
    font-weight: 500;
    gap: var(--space-m);
    background-color: var(--paper);
    padding: 6px 14px;
    border: 1px solid var(--perforation);
    border-radius: 999px;
}

.period i {
    font-size: var(--text-label);
    color: var(--faded);
}

.text {
    font-size: var(--text-label);
    color: var(--faded);
}
</style>