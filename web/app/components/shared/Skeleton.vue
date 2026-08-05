<script setup lang="ts">
/**
 * One shimmering bar. The *shape* of a loading state is screen-specific — the
 * point is to occupy the same geometry the real content will, so nothing jumps
 * when data lands — so each screen composes its own layout from these.
 */
withDefaults(defineProps<{
    width?: string
    height?: string
}>(), {
    width: '100%',
    height: '11px'
})
</script>

<template>
    <div class="skeleton" :style="{ width, height }"></div>
</template>

<style scoped>
@keyframes feed {
    0%, 100% { opacity: .55 }
    50% { opacity: .95 }
}

.skeleton {
    background-color: var(--perforation);
    border-radius: 2px;
    animation: feed 1.4s ease-in-out infinite;
}
</style>