<script setup lang="ts">
import Check from '@primeicons/vue/check';

defineProps<{
    open: boolean
    message: string
    actionLabel?: string
}>()

const emit = defineEmits<{
    action: []
}>()
</script>

<template>
    <div class="toast" :class="{ shown: open, 'has-action': actionLabel }" role="status" aria-live="polite">
        <span class="ok">
            <Check />
            {{ message }}
        </span>
        <button v-if="actionLabel" class="action" @click="emit('action')">{{ actionLabel }}</button>
    </div>
</template>

<style scoped>
/* 88px clears the commit bar on the modal screens and the nav on the roll; the
   drill layout has neither, so it overrides --toast-offset to sit lower. */
.toast {
    position: fixed;
    left: var(--space-l);
    right: var(--space-l);
    bottom: var(--toast-offset, 88px);
    z-index: 90;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--space-m);
    background-color: var(--paper);
    border: 1.5px dashed var(--perforation);
    border-radius: var(--radius-card);
    padding: var(--space-m) var(--space-l);
    font-family: var(--mono);
    font-size: var(--text-label);
    box-shadow: 0 10px 24px rgb(33 30 25 / 18%);
    opacity: 0;
    transform: translateY(var(--space-s));
    pointer-events: none;
    transition: opacity .2s, transform .2s;
}

.toast.has-action {
    justify-content: space-between;
}

/* pointer-events: auto so the undo button is tappable while the toast is up. */
.toast.shown {
    opacity: 1;
    transform: translateY(0);
    pointer-events: auto;
}

.ok {
    display: flex;
    align-items: center;
    gap: var(--space-s);
    letter-spacing: .1em;
    text-transform: uppercase;
}

.ok :deep(.p-icon) {
    width: 16px;
    height: 16px;
    color: var(--under);
}

.action {
    flex: none;
    background: transparent;
    border: 0;
    color: var(--stamp);
    font-family: var(--mono);
    font-size: var(--text-label);
    font-weight: 600;
    letter-spacing: .12em;
    text-transform: uppercase;
    cursor: pointer;
}
</style>