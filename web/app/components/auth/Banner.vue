<script setup lang="ts">
import ExclamationCircle from '@primeicons/vue/exclamation-circle';
import CheckCircle from '@primeicons/vue/check-circle';

withDefaults(defineProps<{
    tone?: 'alert' | 'ok'
}>(), {
    tone: 'alert'
})
</script>

<template>
    <div class="banner" :class="tone" :role="tone === 'alert' ? 'alert' : 'status'">
        <component :is="tone === 'alert' ? ExclamationCircle : CheckCircle" class="icon" />
        <span><slot /></span>
    </div>
</template>

<style scoped>
.banner {
    display: flex;
    gap: var(--space-s);
    align-items: flex-start;
    margin-bottom: var(--space-m);
    padding: var(--space-m);
    border: 1.5px dashed var(--stamp);
    border-radius: var(--radius-card);
    font-family: var(--sans);
    font-size: var(--text-body);
    line-height: 1.45;
    color: var(--stamp);

    &.ok {
        border-color: var(--under);
        color: var(--under);
    }
}

.banner :deep(.icon) {
    width: 14px;
    height: 14px;
    margin-top: 3px;
    flex: none;
}
</style>