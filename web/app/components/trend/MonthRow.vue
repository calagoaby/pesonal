<script setup lang="ts">
import type { TrendRow } from '~/composables/trend/useTrend';

const props = defineProps<{
    row: TrendRow
}>()

const {
    formatMonthName
} = useDateFormatter()


/** Computed */
/** The year is already in the caption above, so the row shows the name alone. */
const month_name = computed(() => formatMonthName(props.row.month))

// Spending more is bad, spending less is good — these two are not interchangeable.
const delta_tone = computed(() => {
    if (!props.row.change_pct) {
        return 'flat'
    }

    return props.row.change_pct > 0 ? 'up' : 'down'
})

const delta_label = computed(() => {
    const change = props.row.change_pct

    if (change === null) {
        return '—'
    }

    const sign = change > 0 ? '+' : change < 0 ? '−' : ''

    return `${sign}${Math.abs(change)}%`
})
</script>

<template>
    <div class="month-row">
        <span class="month">
            {{ month_name }}
            <span v-if="row.is_current" class="now">this month</span>
        </span>
        <span class="lead" />
        <span class="amount">{{ formatAmountWithCurrency(row.total) }}</span>
        <span class="delta" :class="delta_tone">{{ delta_label }}</span>
    </div>
</template>

<style scoped>
.month-row {
    display: flex;
    align-items: baseline;
    padding: var(--space-s) 0;
    border-top: 1.5px dashed var(--perforation);
}

.month {
    font-family: var(--sans);
    font-size: var(--text-body);
}

.now {
    font-family: var(--mono);
    font-size: 9px;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: var(--stamp);
    margin-left: var(--space-s);
}

.lead {
    flex: 1;
    border-bottom: 1.5px dotted var(--perforation);
    margin: 0 var(--space-s);
    transform: translateY(-3px);
}

.amount {
    font-size: var(--text-body);
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}

.delta {
    font-size: var(--text-micro);
    width: 52px;
    text-align: right;
    font-variant-numeric: tabular-nums;
}

.up {
    color: var(--stamp);
}

.down {
    color: var(--under);
}

.flat {
    color: var(--faded);
}
</style>