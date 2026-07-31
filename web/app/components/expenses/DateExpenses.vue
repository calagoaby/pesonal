<script setup lang="ts">
import type { DailyExpenses } from '~/types/Expenses';

defineProps<{
    daily_expense: DailyExpenses
}>()
</script>

<template>
    <div class="date">
        <SharedPerf style="flex: 1;" />
        {{ daily_expense.date }}
        <SharedPerf style="flex: 1;" />
    </div>
    <div class="expenses">
        <div class="row" v-for="(expense, index) in daily_expense.items" :key="index">
            <div class="desc">
                {{ expense.description }}
                <span class="cat">{{ expense.category }}</span>
            </div>
            <div class="lead"></div>
            <div class="amount">{{ formatAmountWithCurrency(expense.amount) }}</div>
        </div>
    </div>
</template>

<style scoped>
.date {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: var(--space-s);
    font-size: var(--text-micro);
    text-transform: uppercase;
    letter-spacing: .1em;
    color: var(--faded);
    margin: var(--space-l) 0 var(--space-xs);
}

.row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: var(--text-body);
    gap: var(--space-s);
    padding: var(--space-xs) 0;
}

.desc {
    font-family: var(--sans);
}

.cat {
    font-family: var(--mono);
    font-size: 9px;
    color: var(--faded);
    text-transform: uppercase;
    margin-left: var(--space-s);
    letter-spacing: .06em;
}

.lead {
    flex: 1;
    border-bottom: 1.5px dotted var(--perforation);
}
</style>