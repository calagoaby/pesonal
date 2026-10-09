<script setup lang="ts">
import ChevronLeft from '@primeicons/vue/chevron-left';
import ChevronRight from '@primeicons/vue/chevron-right';
import { useMonthlyStatsContext } from '~/composables/dashboard/context/MonthlyStatsContext';

const {
    month,
    goToPreviousMonth,
    goToNextMonth
} = useMonthlyStatsContext()

const {
    formatMonthLabel
} = useDateFormatter()

const is_month_picker_open = ref(false)
</script>

<template>
    <div class="month-switcher">
        <div class="period">
            <button class="chevron" type="button" aria-label="Previous month" @click="goToPreviousMonth">
                <ChevronLeft />
            </button>
            <button class="label" @click="is_month_picker_open = true">{{ formatMonthLabel(month) }}</button>
            <button class="chevron" type="button" aria-label="Next month" @click="goToNextMonth">
                <ChevronRight />
            </button>
        </div>
        <div class="text">
            printed just now
        </div>
    </div>

    <SharedMonthPicker v-model:open="is_month_picker_open" v-model:month="month" />
</template>

<style scoped>
.month-switcher {
    margin: var(--space-l) 0 var(--space-l);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.period {
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: var(--text-body);
    font-family: var(--mono);
    font-weight: 500;
    gap: var(--space-m);
    background-color: var(--paper);
    padding: var(--space-s) var(--space-l);
    border: 1px solid var(--perforation);
    border-radius: var(--radius-pill);
}

.label {
    background: transparent;
    border: 0;
    padding: 0;
    font: inherit;
    color: var(--ink);
    cursor: pointer;
}

.chevron {
    display: flex;
    background: transparent;
    border: 0;
    padding: 0;
    font: inherit;
    font-size: var(--text-label);
    color: var(--faded);
    cursor: pointer;
}

.text {
    font-size: var(--text-label);
    color: var(--faded);
}
</style>