<script setup lang="ts">
import ChevronLeft from '@primeicons/vue/chevron-left';
import ChevronRight from '@primeicons/vue/chevron-right';
import ChevronDown from '@primeicons/vue/chevron-down';
import { useMonthExpense } from '~/composables/expenses/useMonthExpense';

const {
    month,
    category_id,
    meta,
    daily_expenses
} = await useMonthExpense()

const {
    categories
} = useCategories()

const {
    formatDailyDate,
    formatMonthLabel
} = useDateFormatter()


/** States */
const is_category_sheet_open = ref(false)


/** Computed */
const category_label = computed(() =>
    categories.value.find(item => item.id === category_id.value)?.name ?? 'All'
)
</script>

<template>
    <SharedReceipt class="expense-roll" torn-position="top" sub-text="· expense receipt ·">
        <SharedPerf />

        <div class="period-switcher">
            <ChevronLeft />
            PERIOD {{ month }}
            <ChevronRight />
        </div>

        <SharedPerf />

        <button class="category-switcher" @click="is_category_sheet_open = true">
            <span class="label">Category</span>
            <span class="value">
                {{ category_label }}
                <ChevronDown color="var(--faded)" />
            </span>
        </button>

        <div class="daily-expenses" v-for="daily_expense in daily_expenses" :key="daily_expense.date">
            <div class="date">
                {{ formatDailyDate(daily_expense.date) }}
            </div>
            <div class="expenses">
                <div class="row" v-for="expense in daily_expense.items" :key="expense.id">
                    <div class="desc">
                        {{ expense.note }}
                        <span class="cat">{{ expense.category.name }}</span>
                    </div>
                    <div class="lead"></div>
                    <div class="amount">{{ formatAmountWithCurrency(expense.amount) }}</div>
                </div>
            </div>
        </div>

        <SharedPerf />

        <div class="more">✂ &nbsp; pull for more &nbsp; ✂</div>

        <div class="foot">
            <SharedBarcode class="roll-barcode" gap="5px" height="36px" />
            <div class="count">{{ meta?.total ?? 0 }} items · {{ formatMonthLabel(month) }}</div>
            <div class="quip">* thank you, come again *</div>
        </div>
    </SharedReceipt>

    <SharedCategorySheet
        v-model:open="is_category_sheet_open"
        v-model:selected="category_id"
        :categories="categories"
        title="Filter by category"
        all-label="All"
    />
</template>

<style scoped>
.expense-roll {
    margin-bottom: var(--space-l);
}

.period-switcher {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: var(--space-xl);
    font-size: var(--text-label);
}

.category-switcher {
    width: 100%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-family: var(--mono);
    font-size: var(--text-label);
    text-transform: uppercase;
    letter-spacing: .08em;
    padding: var(--space-s) 2px;
    border: 0;
    border-bottom: 1.5px dashed var(--perforation);
    background: transparent;
    color: var(--ink);
    cursor: pointer;
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
    align-items: center;
    gap: var(--space-s);
    font-size: var(--text-micro);
    text-transform: uppercase;
    letter-spacing: .1em;
    color: var(--faded);
    margin: var(--space-l) 0 var(--space-xs);
}

.date::after, .date::before {
    content: '';
    border-top: 1.5px dashed var(--perforation);
    flex: 1;
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

.roll-barcode {
    margin: var(--space-s) 0;
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