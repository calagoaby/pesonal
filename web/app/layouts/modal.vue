<template>
    <div class="shell">
        <div class="content">
            <slot />
        </div>
        <slot name="footer" />
        <SharedToaster />
    </div>
</template>

<style scoped>
.shell {
    display: flex;
    flex-direction: column;
    height: var(--app-height, 100dvh); /* the phone frame's screen on a desktop */

    .content {
        padding: var(--space-m) var(--space-l) 0;
        flex: 1;
        min-height: 0;
        overflow-y: auto;
    }
}
</style>