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
</script>

<template>
    <div class="month-switcher">
        <div class="period">
            <ChevronLeft class="chevron" @click="goToPreviousMonth" />
            <span>{{ formatMonthLabel(month) }}</span>
            <ChevronRight class="chevron" @click="goToNextMonth" />
        </div>
        <div class="text">
            printed just now
        </div>
    </div>
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

.chevron {
    font-size: var(--text-label);
    color: var(--faded);
    cursor: pointer;
}

.text {
    font-size: var(--text-label);
    color: var(--faded);
}
</style>