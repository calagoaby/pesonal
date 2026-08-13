<template>
    <div class="shell">
        <div class="content">
            <slot />
        </div>
        <SharedNavBar />
        <SharedToaster />
    </div>
</template>

<style scoped>
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