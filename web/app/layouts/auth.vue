<template>
    <div class="shell">
        <div class="content">
            <slot />
        </div>
        <SharedToaster />
    </div>
</template>

<style scoped>
/* Signed-out screens: no nav, no FAB — just the receipt. Like the drill layout, the
   page scrolls and the toast sits near the bottom edge. */
.shell {
    display: flex;
    flex-direction: column;
    min-height: 100dvh;
    --toast-offset: var(--space-l);

    .content {
        padding: var(--space-m) var(--space-l) var(--space-2xl);
        flex: 1;
    }
}
</style>