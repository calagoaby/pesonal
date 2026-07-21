<script setup lang="ts">
import { useCategoryBreakdown } from '~/composables/dashboard/useCategoryBreakdown';

const {
    chart_data,
    chart_options,
    category_rows
} = useCategoryBreakdown()
</script>

<template>
    <div class="category-breakdown">
        <div class="header-row">
            <span class="where">WHERE IT WENT</span>
            <span class="hint">tap a row to filter</span>
        </div>
        <div class="body-row">
            <div class="doughnut">
                <Chart type="doughnut" :data="chart_data" :options="chart_options"/>
                <div class="inside-text">
                    <div class="total">₱43.2k</div>
                    <div class="text">total</div>
                </div>
            </div>
            <div class="legend">
                <div v-for="(category, index) in category_rows" :key="index">
                    <div class="leg-row">
                        <div class="leg-name">
                            <span class="dot" :style="{ backgroundColor: category.color }"></span>
                            <span>{{ category.name }}</span>
                        </div>
                        <div class="numbers">
                            <span class="amount">₱{{ formatAmount(category.amount) }}&nbsp;</span>
                            <span class="percent">{{ category.percentage }}%</span>
                        </div>
                    </div>
                    <div class="seg">
                        <div class="fill" :style="{ 
                            backgroundColor: category.color, 
                            width: category.percentage + '%' 
                        }"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.category-breakdown {
    background-color: var(--paper);
    border-radius: var(--radius-card);
    padding: var(--space-l);
}

.header-row {
    display: flex;
    justify-content: space-between;
    color: var(--faded);
    margin-bottom: var(--space-m);
}

.where {
    font-family: var(--mono);
    font-size: var(--text-label);
    letter-spacing: .08em;
}

.hint {
    font-size: var(--text-micro);
}

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