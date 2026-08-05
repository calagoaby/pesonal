<script setup lang="ts">
/**
 * The blank-slip state: nothing to show, nothing found, or nothing reachable.
 * Empty and error are the same block in design/states.html — only the mark's
 * colour and the copy change — so they're one component, not two.
 */
withDefaults(defineProps<{
    title: string
    tone?: 'quiet' | 'alert'
}>(), {
    tone: 'quiet'
})
</script>

<template>
    <div class="notice" :class="tone">
        <div class="mark">
            <slot name="mark" />
        </div>
        <div class="title">{{ title }}</div>
        <p class="message">
            <slot />
        </p>
        <div v-if="$slots.action" class="action">
            <slot name="action" />
        </div>
    </div>
</template>

<style scoped>
.notice {
    border: 1.5px dashed var(--perforation);
    border-radius: var(--radius-card);
    padding: var(--space-2xl) var(--space-l);
    text-align: center;
    margin-top: var(--space-l);
}

.mark {
    display: flex;
    justify-content: center;
    color: var(--perforation);
}

.notice.alert .mark {
    color: var(--stamp);
}

.mark :deep(.p-icon) {
    width: 26px;
    height: 26px;
}

.title {
    font-family: var(--mono);
    font-size: var(--text-label);
    font-weight: 600;
    letter-spacing: .14em;
    text-transform: uppercase;
    margin-top: var(--space-m);
}

.message {
    font-family: var(--sans);
    font-size: var(--text-label);
    color: var(--faded);
    margin-top: var(--space-s);
    line-height: 1.6;
}

.action {
    margin-top: var(--space-l);
}
</style>