<template>
    <div class="shell">
        <div class="content">
            <slot />
        </div>
        <SharedToaster />
    </div>
</template>

<style scoped>
/* A drill-down: scrollable content, a back bar the page supplies itself, and no
   bottom nav — unlike the primary screens. */
.shell {
    display: flex;
    flex-direction: column;
    height: 100dvh;

    .content {
        padding: var(--space-m) var(--space-l) 0;
        flex: 1;
        min-height: 0;
        overflow-y: auto;
    }
}
</style>
