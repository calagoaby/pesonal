<script setup lang="ts">
import ChevronLeft from '@primeicons/vue/chevron-left';
import ExclamationTriangle from '@primeicons/vue/exclamation-triangle';
import { listCategories } from '~/repositories/categories';

definePageMeta({ layout: 'drill' })

// Awaited so the roll server-renders, and keyed 'categories' so it shares the
// cache the picker and filter already read.
const { data: categories, error, refresh } = await useAsyncData('categories', () => listCategories())

// No per-row usage count: the category resource carries no all-time total, and
// the only source (this month's stats) is a second call the design says to skip.
</script>

<template>
    <NuxtLink to="/more" class="back-bar">
        <ChevronLeft />
        More
    </NuxtLink>

    <SharedReceipt class="category-roll" torn-position="top" sub-text="· category list ·">
        <SharedPerf />

        <SharedNotice v-if="error" title="Printer jammed" tone="alert">
            <template #mark>
                <ExclamationTriangle />
            </template>
            Couldn't reach the server.<br>
            Your categories are safe — this screen just came up blank.
            <template #action>
                <button class="act" @click="refresh()">Try again</button>
            </template>
        </SharedNotice>

        <template v-else>
            <div class="category" v-for="category in categories" :key="category.id">
                <span class="dot" :style="{ backgroundColor: category.color }"></span>
                <span class="emoji">{{ category.emoji }}</span>
                <span class="name">{{ category.name }}</span>
            </div>
        </template>

        <div class="foot">
            <SharedBarcode class="roll-barcode" gap="5px" height="36px" />
            <div class="count">{{ categories?.length ?? 0 }} categories</div>
            <div class="quip">* every peso needs a home *</div>
        </div>
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

.category-roll {
    margin-bottom: var(--space-l);
}

.category {
    display: flex;
    align-items: center;
    gap: var(--space-m);
    padding: var(--space-m) 2px;
    border-top: 1.5px dashed var(--perforation);
}

.dot {
    width: 10px;
    height: 10px;
    border-radius: 3px;
    flex: none;
}

.emoji {
    font-size: var(--text-body);
}

.name {
    flex: 1;
    font-family: var(--sans);
    font-size: var(--text-body);
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
