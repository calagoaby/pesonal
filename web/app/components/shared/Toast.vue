<script setup lang="ts">
import Check from '@primeicons/vue/check';

const is_open = defineModel<boolean>('open', { required: true })

const props = withDefaults(defineProps<{
    message: string
    duration?: number
}>(), {
    duration: 10000
})

let timer: ReturnType<typeof setTimeout> | undefined


watch(is_open, (open) => {
    clearTimeout(timer)

    if (open) {
        timer = setTimeout(() => is_open.value = false, props.duration)
    }
})

onBeforeUnmount(() => clearTimeout(timer))
</script>

<template>
    <div class="toast" :class="{ shown: is_open }" role="status" aria-live="polite">
        <span class="ok">
            <Check />
            {{ props.message }}
        </span>
    </div>
</template>

<style scoped>
/* 88px clears the commit bar — an intentional one-off, not a spacing step. */
.toast {
    position: fixed;
    left: var(--space-l);
    right: var(--space-l);
    bottom: 88px;
    display: flex;
    align-items: center;
    justify-content: center;
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

.toast.shown {
    opacity: 1;
    transform: translateY(0);
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
</style>