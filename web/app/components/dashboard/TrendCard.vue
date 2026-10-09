<script setup lang="ts">
import { useTrend } from '~/composables/trend/useTrend';

const {
    points,
    average,
    peak_index,
    current_index,
    error
} = await useTrend()

const {
    formatMonthAbbrev
} = useDateFormatter()

// SVG user units inside viewBox="0 0 320 76" — geometry, not spacing tokens.
const BASELINE_Y = 56
const PLOT_HEIGHT = 43.8
const FIRST_X = 12
const LAST_X = 308


/** Computed */
const has_trend = computed(() => !error.value && points.value.length > 0)

/** centavos — the tallest month, which the y-axis tops out at. */
const peak_total = computed(() => peak_index.value >= 0 ? points.value[peak_index.value]!.total : 0)

const marks = computed(() => {
    const count = points.value.length
    // A single point sits at the left edge rather than dividing by zero.
    const step = count > 1 ? (LAST_X - FIRST_X) / (count - 1) : 0

    return points.value.map((point, index) => ({
        month: point.month,
        label: formatMonthAbbrev(point.month),
        x: round(FIRST_X + index * step),
        /**
         * The axis starts at zero — a floating baseline turns a normal month into
         * a crisis. A window that spent nothing has no peak to scale against, so
         * every month lands flat on the baseline instead of dividing by zero.
         */
        y: round(peak_total.value ? BASELINE_Y - (point.total / peak_total.value) * PLOT_HEIGHT : BASELINE_Y),
        is_peak: index === peak_index.value,
        is_current: index === current_index.value
    }))
})

const line_points = computed(() => marks.value.map(mark => `${mark.x},${mark.y}`).join(' '))

/** average is centavos; the hint drops centavos, so round to whole pesos before formatting. */
const average_hint = computed(() =>
    `avg ${formatAmountWithCurrency(Math.round(average.value / 100) * 100).replace(/\.00$/, '')}`
)


/** Functions */
function round(value: number) {
    return Math.round(value * 10) / 10
}
</script>

<template>
    <!-- A secondary card: a failed or empty read renders nothing, so a broken sparkline never pushes a failure notice into the dashboard. -->
    <NuxtLink v-if="has_trend" to="/trend" class="trend-card">
        <DashboardCard
            header='Six month trend'
            :hint="average_hint"
        >
            <svg viewBox="0 0 320 76" width="100%" height="68">
                <line
                    x1="0" y1="56" x2="320" y2="56"
                    stroke="var(--perforation)"
                    stroke-width="1"
                    stroke-dasharray="3 3"
                />
                <polyline
                    :points="line_points"
                    fill="none"
                    stroke="var(--ink)"
                    stroke-width="2"
                    stroke-linejoin="round"
                    stroke-linecap="round"
                />
                <template v-for="mark in marks" :key="mark.month">
                    <!-- When the peak is also the current month the current marker wins: where you stand reads before where the ceiling was. -->
                    <circle
v-if="mark.is_current"
                        :cx="mark.x" :cy="mark.y" r="3.5"
                        fill="var(--stamp)"
                        stroke="var(--stamp)"
                        stroke-width="2"
                    />
                    <circle
v-else-if="mark.is_peak"
                        :cx="mark.x" :cy="mark.y" r="3.5"
                        fill="var(--hi)"
                        stroke="var(--ink)"
                        stroke-width="2"
                    />
                </template>
                <text
v-for="mark in marks"
                    :key="mark.month"
                    :x="mark.x" y="72"
                    text-anchor="middle"
                    :class="{ current: mark.is_current }"
                >{{ mark.label }}</text>
            </svg>
        </DashboardCard>
    </NuxtLink>
</template>

<style scoped>
.trend-card {
    display: block;
    color: inherit;
    text-decoration: none;
}

text {
    font-family: var(--mono);
    font-size: var(--text-micro);
    fill: var(--faded);
}

text.current {
    fill: var(--ink);
}
</style>