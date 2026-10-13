<script setup lang="ts">
defineProps<{
    title: string
}>()
</script>

<template>
    <div class="title">{{ title }}</div>
    <p v-if="$slots.default" class="lede">
        <slot />
    </p>
</template>

<style scoped>
.title {
    margin: var(--space-l) 0 var(--space-xs);
    font-size: var(--text-title);
    font-weight: 600;
    letter-spacing: .04em;
    text-transform: uppercase;
}

.lede {
    margin-bottom: var(--space-l);
    font-family: var(--sans);
    font-size: var(--text-body);
    line-height: 1.5;
    color: var(--faded);

    :deep(b) {
        color: var(--ink);
        font-weight: 600;
    }
}
</style>