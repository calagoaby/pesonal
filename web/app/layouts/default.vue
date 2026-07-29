<template>
    <div class="shell">
        <div class="content">
            <slot />
        </div>
        <SharedNavBar />
    </div>
</template>

<style scoped>
.shell {
    display: flex;
    flex-direction: column;
    height: 100dvh;

    .content {
        flex: 1;
        min-height: 0;
        overflow-y: auto;
    }
}
</style>