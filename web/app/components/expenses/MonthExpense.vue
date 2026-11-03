<script setup lang="ts">
import ChevronLeft from '@primeicons/vue/chevron-left';
import ChevronRight from '@primeicons/vue/chevron-right';
import ChevronDown from '@primeicons/vue/chevron-down';
import Receipt from '@primeicons/vue/receipt';
import FilterSlash from '@primeicons/vue/filter-slash';
import ExclamationTriangle from '@primeicons/vue/exclamation-triangle';
import { useMonthExpense } from '~/composables/expenses/useMonthExpense';

const {
    month,
    category_id,
    meta,
    status,
    error,
    daily_expenses,
    loaded_count,
    has_more,
    is_loading_more,
    load_more_error,
    goToPreviousMonth,
    goToNextMonth,
    loadMore,
    refresh
} = await useMonthExpense()

const {
    categories
} = useCategories()

const {
    formatDailyDate,
    formatMonthLabel,
    formatMonthName
} = useDateFormatter()


/** States */
const is_category_sheet_open = ref(false)
const is_month_picker_open = ref(false)


/** Computed */
const category_label = computed(() =>
    categories.value.find(item => item.id === category_id.value)?.name ?? 'All'
)

// Both screens render server-side, so pending only shows on a client refetch —
// switching month or category — never on first paint.
const is_loading = computed(() => status.value === 'pending')
const is_empty = computed(() => !is_loading.value && !error.value && daily_expenses.value.length === 0)
const is_filtered = computed(() => category_id.value !== null)


/** Functions */
function clearCategory() {
    category_id.value = null
}
</script>

<template>
    <SharedReceipt class="expense-roll" torn-position="top" sub-text="· expense receipt ·">
        <SharedPerf />

        <div class="period-switcher">
            <button class="chevron" type="button" aria-label="Previous month" @click="goToPreviousMonth">
                <ChevronLeft />
            </button>
            <button class="period" @click="is_month_picker_open = true">PERIOD {{ month }}</button>
            <button class="chevron" type="button" aria-label="Next month" @click="goToNextMonth">
                <ChevronRight />
            </button>
        </div>

        <SharedPerf />

        <button class="category-switcher" @click="is_category_sheet_open = true">
            <span class="label">Category</span>
            <span class="value">
                {{ category_label }}
                <ChevronDown color="var(--faded)" />
            </span>
        </button>

        <SharedNotice v-if="error" title="Printer jammed" tone="alert">
            <template #mark>
                <ExclamationTriangle />
            </template>
            Couldn't reach the server.<br>
            Your entries are safe — this screen just came up blank.
            <template #action>
                <button class="act" @click="refresh()">Try again</button>
            </template>
        </SharedNotice>

        <div v-else-if="is_loading" class="loading">
            <SharedSkeleton class="skeleton-day" width="38%" height="9px" />
            <div v-for="row in 6" :key="row" class="skeleton-row">
                <SharedSkeleton :width="`${38 + (row * 7) % 24}%`" />
                <SharedSkeleton width="64px" />
            </div>
        </div>

        <template v-else-if="is_empty">
            <SharedNotice v-if="is_filtered" :title="`No ${category_label.toLowerCase()} in ${formatMonthName(month)}`">
                <template #mark>
                    <FilterSlash />
                </template>
                Either a disciplined month,<br>
                or you went nowhere. Both count.
                <template #action>
                    <button class="act quiet" @click="clearCategory">Show all categories</button>
                </template>
            </SharedNotice>
            <SharedNotice v-else title="Wala pang gastos">
                <template #mark>
                    <Receipt />
                </template>
                A clean receipt. Enjoy it while it lasts —<br>
                it only goes one direction from here.
                <template #action>
                    <NuxtLink to="/add-expense" class="act">Record the first one</NuxtLink>
                </template>
            </SharedNotice>
        </template>

        <template v-else>
            <div v-for="daily_expense in daily_expenses" :key="daily_expense.date" class="daily-expenses">
                <div class="date">
                    {{ formatDailyDate(daily_expense.date) }}
                    <span class="rule" />
                    <span class="day-total">{{ formatAmountWithCurrency(daily_expense.total) }}</span>
                </div>
                <div class="expenses">
                    <NuxtLink
                        v-for="expense in daily_expense.items"
                        :key="expense.id"
                        :to="`/edit-expense/${expense.id}`"
                        class="row"
                    >
                        <div class="desc">
                            {{ expense.note }}
                            <span class="cat">{{ expense.category.name }}</span>
                        </div>
                        <div class="lead" />
                        <div class="amount">{{ formatAmountWithCurrency(expense.amount) }}</div>
                    </NuxtLink>
                </div>
            </div>
        </template>

        <SharedPerf />

        <button v-if="has_more" class="more" :disabled="is_loading_more" @click="loadMore">
            ✂ &nbsp; {{ is_loading_more ? 'printing…' : 'pull for more' }} &nbsp; ✂
        </button>
        <p v-if="load_more_error" class="more-error">{{ load_more_error }}</p>

        <div class="foot">
            <SharedBarcode class="roll-barcode" gap="5px" height="36px" />
            <div class="count">
                {{ has_more ? `${loaded_count} of ${meta?.total ?? 0}` : meta?.total ?? 0 }} items
                · {{ formatMonthLabel(month) }}
            </div>
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

    <SharedMonthPicker
        v-model:open="is_month_picker_open"
        v-model:month="month"
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

    /* Buttons don't inherit font or colour by default; the icon sizes off the font. */
    .chevron {
        display: flex;
        background: transparent;
        border: 0;
        padding: 0;
        font: inherit;
        color: inherit;
        cursor: pointer;
    }
}

.period {
    background: transparent;
    border: 0;
    padding: 0;
    font: inherit;
    letter-spacing: inherit;
    color: var(--ink);
    cursor: pointer;
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

.date::before {
    content: '';
    width: var(--space-m);
    border-top: 1.5px dashed var(--perforation);
}

.rule {
    flex: 1;
    border-top: 1.5px dashed var(--perforation);
}

.day-total {
    color: var(--ink);
    font-weight: 600;
    font-variant-numeric: tabular-nums;
}

.row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: var(--text-body);
    gap: var(--space-s);
    padding: var(--space-xs) 0;
    color: inherit;
    text-decoration: none;
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

.loading {
    padding-bottom: var(--space-s);
}

.skeleton-day {
    margin: var(--space-l) auto var(--space-m);
}

.skeleton-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-l);
    padding: var(--space-s) 0;
}

.act {
    display: inline-block;
    background-color: var(--stamp);
    color: var(--paper);
    border: 0;
    border-radius: var(--radius-pill);
    padding: var(--space-s) var(--space-l);
    font-family: var(--mono);
    font-size: var(--text-label);
    font-weight: 600;
    letter-spacing: .12em;
    text-transform: uppercase;
    text-decoration: none;
    cursor: pointer;
}

.act.quiet {
    background: transparent;
    color: var(--ink);
    border: 1px solid var(--perforation);
}

.more {
    width: 100%;
    text-align: center;
    text-transform: uppercase;
    color: var(--stamp);
    font-family: var(--mono);
    font-size: var(--text-label);
    letter-spacing: .14em;
    background: transparent;
    border: 0;
    padding: var(--space-xs) 0;
    cursor: pointer;
}

.more:disabled {
    color: var(--faded);
    cursor: progress;
}

.more-error {
    text-align: center;
    font-family: var(--sans);
    font-size: var(--text-label);
    color: var(--stamp);
    margin-top: var(--space-s);
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