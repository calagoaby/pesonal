<script setup lang="ts">
interface Props {
    header: string;
    hint?: string;
}

defineProps<Props>()
</script>

<template>
    <div class="card">
        <div class="header-row">
            <div class="shdr">{{ header }}</div>
            <div class="hint">
                <slot name="hint">{{ hint }}</slot>
            </div>
        </div>
        <slot />
    </div>
</template>

<style scoped>
.card {
    background-color: var(--paper);
    border-radius: var(--radius-card);
    padding: var(--space-l);
    margin-bottom: var(--space-l);
}

.header-row {
    display: flex;
    justify-content: space-between;
    color: var(--faded);
    margin-bottom: var(--space-m);

    .shdr {
        font-family: var(--mono);
        font-size: var(--text-label);
        letter-spacing: .08em;
        text-transform: uppercase;
    }

    .hint {
        font-size: var(--text-micro);
    }
}
</style>