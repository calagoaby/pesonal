<script setup lang="ts">
import ChevronLeft from '@primeicons/vue/chevron-left';
import ChartLine from '@primeicons/vue/chart-line';
import ExclamationTriangle from '@primeicons/vue/exclamation-triangle';
import { useTrend } from '~/composables/trend/useTrend';

definePageMeta({ layout: 'drill' })

const {
    points,
    rows,
    average,
    total,
    peak_index,
    current_index,
    error,
    refresh
} = await useTrend()

const {
    formatMonthAndYear,
    formatMonthAbbrev
} = useDateFormatter()


/** Computed */
const is_empty = computed(() => !error.value && points.value.length === 0)

const range_caption = computed(() => {
    const oldest = points.value[0]
    const newest = points.value[points.value.length - 1]

    if (!oldest || !newest) {
        return ''
    }

    const to = formatMonthAndYear(`${newest.month}-01`)

    // "Mar – Aug 2026" inside one year; a window that crosses New Year keeps both.
    return oldest.month.slice(0, 4) === newest.month.slice(0, 4)
        ? `${formatMonthAbbrev(oldest.month)} – ${to}`
        : `${formatMonthAndYear(`${oldest.month}-01`)} – ${to}`
})
</script>

<template>
    <NuxtLink to="/" class="back-bar">
        <ChevronLeft />
        Back
    </NuxtLink>

    <SharedReceipt class="trend-roll" torn-position="top" sub-text="· six month trend ·">
        <SharedPerf />

        <SharedNotice v-if="error" title="Printer jammed" tone="alert">
            <template #mark>
                <ExclamationTriangle />
            </template>
            Couldn't reach the server.<br>
            Your history is safe — this screen just came up blank.
            <template #action>
                <button class="act" @click="refresh()">Try again</button>
            </template>
        </SharedNotice>

        <SharedNotice v-else-if="is_empty" title="Nothing to plot">
            <template #mark>
                <ChartLine />
            </template>
            No months came back.<br>
            A line needs at least one point to lean on.
        </SharedNotice>

        <template v-else>
            <div class="average">
                <div class="average-label">Monthly average</div>
                <div class="average-value">{{ formatAmountWithCurrency(average) }}</div>
                <div class="average-caption">{{ range_caption }}</div>
            </div>

            <SharedPerf />

            <TrendChart :points="points" :peak-index="peak_index" :current-index="current_index" />

            <SharedPerf />

            <div class="section-header">Month by month</div>
            <TrendMonthRow v-for="row in rows" :key="row.month" :row="row" />

            <div class="foot">
                <SharedBarcode class="roll-barcode" gap="5px" height="36px" />
                <div class="count">{{ points.length }} months · {{ formatAmountWithCurrency(total) }} total</div>
                <div class="quip">* the line only goes one way *</div>
            </div>
        </template>
    </SharedReceipt>
</template>

<style scoped>
.back-bar {
    display: flex;
    align-items: center;
    gap: var(--space-s);
    padding: 0 var(--space-xs) var(--space-s);
    font-family: var(--mono);
    font-size: var(--text-label);
    letter-spacing: .14em;
    text-transform: uppercase;
    color: var(--faded);
    text-decoration: none;
}

.back-bar :deep(.p-icon) {
    width: 12px;
    height: 12px;
    color: var(--ink);
}

.trend-roll {
    margin-bottom: var(--space-l);
}

.average {
    text-align: center;
}

.average-label {
    font-size: var(--text-micro);
    letter-spacing: 1.5px;
    text-transform: uppercase;
    color: var(--faded);
}

.average-value {
    font-size: var(--text-hero);
    font-weight: 600;
    font-variant-numeric: tabular-nums;
    margin-top: var(--space-xs);
}

.average-caption {
    font-family: var(--sans);
    font-size: var(--text-label);
    color: var(--faded);
    margin-top: var(--space-s);
}

.section-header {
    font-size: var(--text-micro);
    letter-spacing: .1em;
    text-transform: uppercase;
    color: var(--faded);
    margin-bottom: var(--space-s);
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
    cursor: pointer;
}

.foot {
    text-align: center;
    margin-top: var(--space-l);
}

.roll-barcode {
    margin: var(--space-s) auto;
}

.count {
    font-size: var(--text-micro);
    letter-spacing: .14em;
    text-transform: uppercase;
    color: var(--faded);
}

.quip {
    font-size: 9px;
    letter-spacing: .18em;
    text-transform: uppercase;
    color: var(--perforation);
    margin-top: var(--space-s);
}
</style>