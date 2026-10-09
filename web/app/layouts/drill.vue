<template>
    <div class="shell">
        <div class="content">
            <slot />
        </div>
        <SharedToaster />
    </div>
</template>

<style scoped>
/* A drill-down: a back bar the page supplies itself, and no bottom nav — unlike the
   primary screens. The page scrolls, not .content (see layouts/default.vue). */
.shell {
    display: flex;
    flex-direction: column;
    min-height: 100dvh;
    /* No nav or commit bar here, so the toast sits near the bottom edge. */
    --toast-offset: var(--space-l);

    .content {
        padding: var(--space-m) var(--space-l) 0;
        flex: 1;
    }
}
</style>