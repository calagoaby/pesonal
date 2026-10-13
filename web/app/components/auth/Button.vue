<script setup lang="ts">
withDefaults(defineProps<{
    variant?: 'cta' | 'ghost'
    type?: 'button' | 'submit'
    disabled?: boolean
}>(), {
    variant: 'cta',
    type: 'button'
})
</script>

<template>
    <button class="btn" :class="variant" :type="type" :disabled="disabled">
        <slot />
    </button>
</template>

<style scoped>
.btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    margin-top: var(--space-s);
    padding: var(--space-m) var(--space-l);
    border-radius: var(--radius-pill);
    font-family: var(--mono);
    font-size: var(--text-label);
    font-weight: 600;
    letter-spacing: .08em;
    text-transform: uppercase;
    cursor: pointer;

    &.cta {
        margin-top: var(--space-l);
        border: 1.5px solid var(--stamp);
        background: var(--stamp);
        color: #fff;
    }

    &.ghost {
        border: 1.5px solid var(--ink);
        background: transparent;
        color: var(--ink);
    }

    &:disabled {
        opacity: .5;
        cursor: default;
    }
}
</style>