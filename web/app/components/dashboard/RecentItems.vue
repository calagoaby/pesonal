<script setup lang="ts">
import ChevronRight from '@primeicons/vue/chevron-right';
import { useMonthlyStatsContext } from '~/composables/dashboard/context/MonthlyStatsContext';
import { useRecentItems } from '~/composables/dashboard/useRecentItems';

const {
    month
} = useMonthlyStatsContext()

const {
    items
} = useRecentItems(month)

const {
    formatListDate
} = useDateFormatter()
</script>

<template>
    <DashboardCard
        header='Recent Items'
    >
        <template #hint>
            <NuxtLink to="/expenses" class="seeall">
                See all
                <ChevronRight />
            </NuxtLink>
        </template>
        <div v-for="item in items" :key="item.id" class="item">
            <div class="iname">
                <div class="dot" :style="{ backgroundColor: item.category.color }"/>
                <div class="itext">
                    <span>{{ item.note }}</span>
                    <span class="isub">{{ item.category.name }} · {{ formatListDate(item.date) }}</span>
                </div>
            </div>
            <span class="iamt">{{ formatAmountWithCurrency(item.amount) }}</span>
        </div>
    </DashboardCard>
</template>

<style scoped>
.seeall {
    font-size: var(--text-label);
    color: var(--stamp);
    display: flex;
    align-items: center;
    gap: var(--space-xs);
    text-decoration: none;
}

.item {
    border-top: 1.5px dashed var(--perforation);
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: var(--text-body);
    font-weight: 500;
    padding: var(--space-m) 0;

    .dot {
        width: 10px;
        height: 10px;
        border-radius: 3px;
    }
}

.iname {
    display: flex;
    align-items: center;
    gap: var(--space-s);
}

.itext {
    display: flex;
    flex-direction: column;
}

.isub {
    font-size: var(--text-label);
    color: var(--faded);
}

.iamt {
    font-family: var(--mono);
    font-variant-numeric: tabular-nums;
}
</style>