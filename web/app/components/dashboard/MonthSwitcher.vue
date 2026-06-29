<template>
    <div class="content">
        <div class="period">
           <div><</div>
            <span>June 2026</span>
            <div>></div>
        </div>
        <div class="text">
            printed just now
        </div>
    </div>
</template>

<style scoped>
.content {
    margin: 14px 0 0;
    display: flex;
    justify-content: space-between;
}

.period {
    display: flex;
    font-size: 13px;
    font-family: var(--mono);
    font-weight: 500;
    gap: var(--space-m);
    background-color: var(--paper);
    padding: 6px 14px;
    border-radius: 999px;
}

.text {
    font-size: var(--text-label);
    color: var(--faded)
}
</style>