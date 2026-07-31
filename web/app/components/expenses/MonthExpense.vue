<script setup lang="ts">
import ChevronLeft from '@primeicons/vue/chevron-left';
import ChevronRight from '@primeicons/vue/chevron-right';
import ChevronDown from '@primeicons/vue/chevron-down';
import { useMonthExpense } from '~/composables/expenses/useMonthExpense';

const {
    month_expenses
} = useMonthExpense()
</script>

<template>
    <SharedReceipt torn-position="top" sub-text="· expense receipt ·">
        <div class="period-switcher">
            <ChevronLeft />
            PERIOD {{ month_expenses.month  }}
            <ChevronRight />
        </div>
        <SharedPerf />
        <div class="category-switcher">
            <span class="label">Category</span>
            <span class="value">
                All
                <ChevronDown color="var(--faded)" />
            </span>
        </div>
        <ExpensesDateExpenses 
            v-for="(daily_expense, index) in month_expenses.days" 
            :daily_expense="daily_expense" 
            :key="index"
        />
    </SharedReceipt>
</template>

<style scoped>
.period-switcher {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: var(--space-xl);
    font-size: var(--text-label);
}

.category-switcher {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: var(--text-label);
    text-transform: uppercase;
    letter-spacing: .08em;
    padding: var(--space-s) 2px;
    border-bottom: 1.5px dashed var(--perforation);
}

.label {
    color: var(--faded);
}

.value {
    display: flex;
    align-items: center;
    gap: var(--space-s);
}
</style>