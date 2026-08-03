<script setup lang="ts">
import ChevronLeft from '@primeicons/vue/chevron-left';
import ChevronRight from '@primeicons/vue/chevron-right';
import ChevronDown from '@primeicons/vue/chevron-down';
import { useMonthExpense } from '~/composables/expenses/useMonthExpense';

const {
    month_expenses
} = useMonthExpense()

const {
    formatDailyDate
} = useDateFormatter()
</script>

<template>
    <SharedReceipt torn-position="top" sub-text="· expense receipt ·" style="margin-bottom: var(--space-l);">

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
        
        <div class="daily-expenses" v-for="(daily_expense, index) in month_expenses.days" :key="index">
            <div class="date">
                <SharedPerf style="flex: 1;" />
                {{ formatDailyDate(daily_expense.date) }}
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
        </div>

        <SharedPerf />

        <div class="more">✂ &nbsp; pull for more &nbsp; ✂</div>

        <div class="foot">
            <SharedBarcode gap="5px" height="36px" style="margin: var(--space-s) 0;" />
            <div class="count">138 items · June 2026</div>
            <div class="quip">* thank you, come again *</div>
        </div>
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

.more {
    text-align: center;
    text-transform: uppercase;
    color: var(--stamp);
    font-size: var(--text-label);
    letter-spacing: .14em;
}

.foot {
    text-align: center;
    text-transform: uppercase;
}

.count {
    color: var(--faded);
    letter-spacing: .14em;
    font-size: var(--text-micro);
}

.quip {
    color: var(--perforation);
    letter-spacing: .18em;
    font-size: 9px;
    margin-top: var(--space-s);
}
</style>