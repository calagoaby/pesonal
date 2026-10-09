<template>
    <div class="shell">
        <div class="content">
            <slot />
        </div>
        <div class="dock">
            <SharedNavBar />
        </div>
        <SharedToaster />
    </div>
</template>

<style scoped>
/* The page scrolls, not .content: the scrollbar sits at the window edge and the
   wheel works over the column's margins. The dock keeps the nav pinned. */
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