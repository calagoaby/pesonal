<template>
    <div class="switch">
        <slot />
    </div>
</template>

<style scoped>
.switch {
    margin-top: var(--space-l);
    text-align: center;
    font-family: var(--sans);
    font-size: var(--text-body);
    color: var(--faded);

    :deep(a) {
        font-weight: 600;
        color: var(--ink);
        text-decoration: underline;
        text-underline-offset: 3px;
    }
}
</style>