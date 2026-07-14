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
    margin: var(--space-l) 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.period {
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: var(--text-body);
    font-family: var(--mono);
    font-weight: 500;
    gap: var(--space-m);
    background-color: var(--paper);
    padding: var(--space-s) var(--space-l);
    border: 1px solid var(--perforation);
    border-radius: var(--radius-pill);
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