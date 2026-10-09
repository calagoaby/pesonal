<template>
    <div class="shell">
        <div class="content">
            <slot />
        </div>
        <div class="dock">
            <slot name="footer" />
        </div>
        <SharedToaster />
    </div>
</template>

<style scoped>
/* The page scrolls, not .content (see layouts/default.vue); the dock keeps the
   page's commit bar pinned to the bottom. */
.shell {
    display: flex;
    flex-direction: column;
    min-height: 100dvh;

    .content {
        padding: var(--space-m) var(--space-l) 0;
        flex: 1;
    }

    .dock {
        position: sticky;
        bottom: 0;
        z-index: 10;
    }
}
</style>