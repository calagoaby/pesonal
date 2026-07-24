<script setup lang="ts">
import { useDailySpending } from '~/composables/dashboard/useDailySpending';

const {
    spending_percentages,
    is_peak_index,
} = useDailySpending()
</script>

<template>
    <DashboardSharedDashboardCard
        header='DAILY DAMAGE'
        hint="biggest: Jun 1"
    >
        <div class="bars">
            <div v-for="(daily, index) in spending_percentages" 
                :key="index" 
                :style="{ height: `${daily}%`}"
                :class="{ peak: is_peak_index === index }"
            >
            </div>
        </div>
        <div class="number-labels">
            <div v-for="index in spending_percentages.length" :key="index">
                {{ (index === 1 || index % 5 === 0) ? index : '' }}
            </div>
        </div>
    </DashboardSharedDashboardCard>
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