<script setup lang="ts">
import ExclamationCircle from '@primeicons/vue/exclamation-circle';

const is_open = defineModel<boolean>('open', { required: true })
const day = defineModel<number>('day', { required: true })

const days = Array.from({ length: 31 }, (_, index) => index + 1)


/** Functions */
function pick(value: number) {
    day.value = value
    is_open.value = false
}
</script>

<template>
    <SharedSheet v-model:open="is_open" title="Charge on which day?">
        <div class="days">
            <button
                v-for="value in days"
                :key="value"
                class="day"
                :class="{ on: value === day, warn: value >= 29 }"
                @click="pick(value)"
            >{{ value }}</button>
        </div>
        <div class="clamp-note">
            <ExclamationCircle />
            <span>Short months don't have a 31st. On those it charges on the last day instead — Feb 28, Apr 30, and so on.</span>
        </div>
    </SharedSheet>
</template>

<style scoped>
.days {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: var(--space-s);
    margin-top: var(--space-m);
}

.day {
    aspect-ratio: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: var(--mono);
    font-size: var(--text-label);
    border: 1px solid var(--perforation);
    border-radius: var(--radius-card);
    background: transparent;
    color: var(--ink);
    cursor: pointer;
}

.day.warn {
    border-color: var(--hi);
    color: var(--hi);
}

.day.on {
    background-color: var(--ink);
    color: var(--paper);
    border-color: var(--ink);
    font-weight: 600;
}

.clamp-note {
    display: flex;
    gap: var(--space-s);
    align-items: flex-start;
    background-color: var(--desk);
    border-radius: var(--radius-card);
    padding: var(--space-m);
    margin-top: var(--space-l);
    font-family: var(--sans);
    font-size: var(--text-label);
    color: var(--faded);
    line-height: 1.5;
}

.clamp-note :deep(.p-icon) {
    width: 14px;
    height: 14px;
    color: var(--hi);
    margin-top: 2px;
    flex: none;
}
</style>