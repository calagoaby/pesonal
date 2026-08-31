<script setup lang="ts">
import type { MonthTotal } from '#shared/types/Stats';

const props = defineProps<{
    points: MonthTotal[]
    peakIndex: number
    currentIndex: number
}>()

const {
    formatMonthAbbrev
} = useDateFormatter()


/** Geometry inside the 320x132 viewBox — SVG units, not spacing tokens. */
const FIRST_X = 26
const LAST_X = 294
const BASELINE_Y = 100
const PLOT_HEIGHT = 86


/** Computed */
const plot = computed(() => {
    // The axis is anchored at zero and scaled to the window's biggest month: a
    // floating baseline would turn ordinary variation into a crisis.
    const max = Math.max(...props.points.map(point => point.total), 0)
    const step = props.points.length > 1 ? (LAST_X - FIRST_X) / (props.points.length - 1) : 0

    return props.points.map((point, index) => ({
        month: point.month,
        label: formatMonthAbbrev(point.month),
        // A single point is both the first and the last, so it sits centred
        // rather than pinned to the left edge — and never divides by zero.
        x: round(props.points.length > 1 ? FIRST_X + index * step : (FIRST_X + LAST_X) / 2),
        // A window that spent nothing has max 0, which would divide by zero;
        // every month simply rests on the baseline instead.
        y: round(max ? BASELINE_Y - (point.total / max) * PLOT_HEIGHT : BASELINE_Y),
        is_current: index === props.currentIndex,
        // The current month wins when it is also the peak: two markers and two
        // tiny labels on one x would collide, and "now" is the more useful read.
        is_peak: index === props.peakIndex && index !== props.currentIndex,
    }))
})

const line = computed(() => plot.value.map(point => `${point.x},${point.y}`).join(' '))
const current = computed(() => plot.value.find(point => point.is_current))
const peak = computed(() => plot.value.find(point => point.is_peak))


/** Functions */
function round(value: number) {
    return Math.round(value * 10) / 10
}

function markerOf(point: { is_current: boolean, is_peak: boolean }) {
    if (point.is_current) {
        return { r: 4, fill: 'var(--stamp)', stroke: 'var(--stamp)' }
    }

    if (point.is_peak) {
        return { r: 4, fill: 'var(--hi)', stroke: 'var(--ink)' }
    }

    return { r: 3, fill: 'var(--paper)', stroke: 'var(--ink)' }
}
</script>

<template>
    <svg class="chart" viewBox="0 0 320 132" width="100%" height="118">
        <line x1="0" y1="100" x2="320" y2="100" stroke="var(--perforation)" stroke-width="1" stroke-dasharray="3 3" />
        <!-- A tint lighter than any token carries — an intentional one-off, as in the mockup. -->
        <line x1="0" y1="55" x2="320" y2="55" stroke="#ece7db" stroke-width="1" stroke-dasharray="3 3" />

        <polyline
            :points="line"
            fill="none"
            stroke="var(--ink)"
            stroke-width="2"
            stroke-linejoin="round"
            stroke-linecap="round"
        />

        <circle
            v-for="point in plot"
            :key="point.month"
            :cx="point.x"
            :cy="point.y"
            v-bind="markerOf(point)"
            stroke-width="2"
        />

        <text
            v-for="point in plot"
            :key="`axis-${point.month}`"
            :x="point.x"
            y="118"
            text-anchor="middle"
            font-size="11"
            :fill="point.is_current ? 'var(--ink)' : 'var(--faded)'"
        >{{ point.label }}</text>

        <text v-if="current" :x="current.x" y="130" text-anchor="middle" font-size="9" fill="var(--stamp)">now</text>
        <text v-if="peak" :x="peak.x" y="130" text-anchor="middle" font-size="9" fill="var(--faded)">peak</text>
    </svg>
</template>

<style scoped>
.chart {
    display: block;
    font-family: var(--mono);
    font-variant-numeric: tabular-nums;
}
</style>