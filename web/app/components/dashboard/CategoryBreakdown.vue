<script setup lang="ts">
import { Doughnut } from 'vue-chartjs';
import { Chart as ChartJS, ArcElement } from 'chart.js';
import { useMonthlyStatsContext } from '~/composables/dashboard/context/MonthlyStatsContext';

// vue-chartjs draws nothing unless the elements are registered — the old PrimeVue
// wrapper auto-registered all of chart.js; here we opt in to just the arc.
ChartJS.register(ArcElement)

const {
    total,
    category_rows
} = useMonthlyStatsContext()


/** Computed */
const chart_data = computed(() => ({
    datasets: [
        {
            data: category_rows.value.map(row => row.percentage),
            backgroundColor: category_rows.value.map(row => row.color),
        }
    ]
}))

// The legend is custom HTML beside the ring, so chart.js draws only the doughnut;
// maintainAspectRatio:false lets it fill the fixed 116×116 box.
const chart_options = {
    cutout: '65%',
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false }, tooltip: { enabled: false } },
}

const total_label = computed(() => `₱${(total.value / 100000).toFixed(1)}k`)
</script>

<template>
    <DashboardCard
        header='Where it went'
        hint="tap a row to filter"
        style="margin-top: var(--space-2xl);"
    >
        <div class="body-row">
            <div class="doughnut">
                <Doughnut :data="chart_data" :options="chart_options" />
                <div class="inside-text">
                    <div class="total">{{ total_label }}</div>
                    <div class="text">total</div>
                </div>
            </div>
            <div class="legend">
                <div v-for="(category, index) in category_rows" :key="index">
                    <div class="leg-row">
                        <div class="leg-name">
                            <span class="dot" :style="{ backgroundColor: category.color }"/>
                            <span>{{ category.name }}</span>
                        </div>
                        <div class="numbers">
                            <span class="amount">{{ formatAmountWithCurrency(category.amount) }}&nbsp;</span>
                            <span class="percent">{{ category.percentage }}%</span>
                        </div>
                    </div>
                    <div class="seg">
                        <div
class="fill" :style="{ 
                            backgroundColor: category.color, 
                            width: category.percentage + '%' 
                        }"/>
                    </div>
                </div>
            </div>
        </div>
    </DashboardCard>
</template>

<style scoped>
.body-row {
    display: flex;
    align-items: center;
    gap: var(--space-l);
}

.doughnut {
    width: 116px;
    height: 116px;
    position: relative;
}

.inside-text {
    position: absolute;
    top: 50%;
    left: 50%;
    transform:translate(-50%,-50%);
    font-family: var(--mono);

    .total {
        font-size: 16px;
        font-weight: 600;
    }

    .text {
        font-size: var(--text-label);
        text-align: center;
        color: var(--faded);
    }
}

.legend {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: var(--space-m);
}

.leg-row {
    display: flex;
    justify-content: space-between;
}

.leg-name {
    display: flex;
    font-size: var(--text-body);
    gap: var(--space-s);
    align-items: center;

    .dot {
        width: 10px;
        height: 10px;
        border-radius: 3px;
    }
}

.numbers {
    font-family: var(--mono);

    .amount {
        font-size: var(--text-body);
        font-variant-numeric: tabular-nums;
        font-weight: 500;
    }

    .percent {
        font-size: var(--text-micro);
        color: var(--faded);
    }
}

.seg {
    height: 5px;
    border-radius: var(--radius-pill);
    background-color: var(--desk);

    .fill {
        height: 100%;
        border-radius: var(--radius-pill);
    }
}
</style>