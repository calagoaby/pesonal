<script setup lang="ts">
import type { Category } from '#shared/types/Category';
import ChevronLeft from '@primeicons/vue/chevron-left';
import ChevronRight from '@primeicons/vue/chevron-right';
import Plus from '@primeicons/vue/plus';
import ExclamationTriangle from '@primeicons/vue/exclamation-triangle';
import { listCategories } from '~/repositories/categories';
import { useToaster } from '~/stores/toaster';

definePageMeta({ layout: 'drill' })

// Awaited so the roll server-renders, and keyed 'categories' so it shares the
// cache the picker and filter already read.
const { data: categories, error, refresh } = await useAsyncData('categories', () => listCategories())

// No per-row usage count: the category resource carries no all-time total, and
// the only source (this month's stats) is a second call the design says to skip.

const toaster = useToaster()


/** States */
const is_sheet_open = ref(false)
/** Null opens the sheet in create mode; a row opens it prefilled for editing. */
const editing = ref<Category | null>(null)


/** Functions */
function openNew() {
    editing.value = null
    is_sheet_open.value = true
}

function openEdit(category: Category) {
    editing.value = category
    is_sheet_open.value = true
}

function onSaved() {
    refresh()
    toaster.show({ message: editing.value ? 'Category updated' : 'Category added' })
}
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
            <button class="category" v-for="category in categories" :key="category.id" @click="openEdit(category)">
                <span class="dot" :style="{ backgroundColor: category.color }"></span>
                <span class="emoji">{{ category.emoji }}</span>
                <span class="name">{{ category.name }}</span>
                <ChevronRight class="chevron" />
            </button>

            <button class="add-new" @click="openNew">
                <Plus />
                new category
            </button>
        </template>

        <div class="foot">
            <SharedBarcode class="roll-barcode" gap="5px" height="36px" />
            <div class="count">{{ categories?.length ?? 0 }} categories</div>
            <div class="quip">* every peso needs a home *</div>
        </div>
    </SharedReceipt>

    <CategoriesForm v-model:open="is_sheet_open" :category="editing" @saved="onSaved" />
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
    width: 100%;
    display: flex;
    align-items: center;
    gap: var(--space-m);
    padding: var(--space-m) 2px;
    border: 0;
    border-top: 1.5px dashed var(--perforation);
    background: transparent;
    color: var(--ink);
    text-align: left;
    cursor: pointer;
}

.chevron {
    width: 12px;
    height: 12px;
    color: var(--perforation);
}

.add-new {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--space-s);
    margin-top: var(--space-l);
    padding-top: var(--space-m);
    border: 0;
    border-top: 1.5px dashed var(--perforation);
    background: transparent;
    color: var(--stamp);
    font-family: var(--mono);
    font-size: var(--text-label);
    letter-spacing: .14em;
    text-transform: uppercase;
    cursor: pointer;
}

.add-new :deep(.p-icon) {
    width: 12px;
    height: 12px;
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