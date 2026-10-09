<script setup lang="ts">
import { useMonthlyStatsContext } from '~/composables/dashboard/context/MonthlyStatsContext';

const {
    daily_percentages,
    peak_index,
    peak_date
} = useMonthlyStatsContext()

const {
    formatListDate
} = useDateFormatter()


/** Computed */
const peak_hint = computed(() => peak_date.value ? `biggest: ${formatListDate(peak_date.value)}` : '')
</script>

<template>
    <DashboardCard
        header='Daily Damage'
        :hint="peak_hint"
    >
        <div class="bars">
            <div
v-for="(daily, index) in daily_percentages"
                :key="index"
                :style="{ height: `${daily}%`}"
                :class="{ peak: peak_index === index }"
            />
        </div>
        <div class="number-labels">
            <div v-for="index in daily_percentages.length" :key="index">
                {{ (index === 1 || index % 5 === 0) ? index : '' }}
            </div>
        </div>
    </DashboardCard>
</template>

<style scoped>
.bars {
    display: flex;
    justify-content: center;
    gap: 5px;
    align-items: flex-end;
    height: 100px;
    padding: 0 var(--space-xs);
    border-bottom: 1.5px dashed var(--perforation);

    div {
        border-radius: 2px;
        background-color: var(--perforation);
    }

    .peak {
        background-color: var(--stamp);
    }
}

.number-labels {
    display: flex;
    justify-content: center;
    gap: 5px;
    padding: 0 var(--space-xs);
    font-family: var(--mono);
    font-size: var(--text-micro);
    color: var(--faded);

    div {
        text-align: center;
        font-variant-numeric: tabular-nums;
    }
}

.bars, .number-labels {
    div {
        flex: 1;
        max-width: 20px;
    }
}
</style>