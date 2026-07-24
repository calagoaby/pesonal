<script setup lang="ts">
import ChevronRight from '@primeicons/vue/chevron-right';
import { useRecentItems } from '~/composables/dashboard/useRecentItems';

const {
    items
} = useRecentItems()
</script>

<template>
    <DashboardCard
        header='Recent Items'
    >
        <template #hint> 
            <span class="seeall">
                See all 
                <ChevronRight />
            </span> 
        </template>
        <div v-for="(item, index) in items" class="item" :key="index">
            <div class="iname">
                <div class="dot" :style="{ backgroundColor: item.category_color }"></div>
                <div class="itext">
                    <span>{{ item.text }}</span>
                    <span class="isub">{{ item.category }} · {{ item.date_created }}</span>
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