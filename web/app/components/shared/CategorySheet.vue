<script setup lang="ts">
import Check from '@primeicons/vue/check';
import type { Category } from '#shared/types/Category';

const is_open = defineModel<boolean>('open', { required: true })
const selected_id = defineModel<number | null>('selected', { required: true })

const props = withDefaults(defineProps<{
    categories: Category[]
    title?: string
    /** Set to prepend an unfiltered row. Omit when a choice is mandatory. */
    allLabel?: string
}>(), {
    title: 'Choose a category'
})


/** Functions */
function pick(id: number | null) {
    selected_id.value = id
    is_open.value = false
}
</script>

<template>
    <SharedSheet v-model:open="is_open" :title="props.title">
        <div class="list">
            <button
                v-if="props.allLabel"
                class="option"
                :class="{ selected: selected_id === null }"
                @click="pick(null)"
            >
                <span class="dot all" />
                <span class="name">{{ props.allLabel }}</span>
                <Check class="check" />
            </button>
            <button
                v-for="category in props.categories"
                :key="category.id"
                class="option"
                :class="{ selected: selected_id === category.id }"
                @click="pick(category.id)"
            >
                <span class="dot" :style="{ backgroundColor: category.color }" />
                <span class="name">{{ category.name }}</span>
                <span class="emoji">{{ category.emoji }}</span>
                <Check class="check" />
            </button>
        </div>
    </SharedSheet>
</template>

<style scoped>
.list {
    max-height: 300px;
    overflow-y: auto;
}

.option {
    width: 100%;
    display: flex;
    align-items: center;
    gap: var(--space-m);
    padding: var(--space-m) var(--space-xs);
    border: 0;
    border-bottom: 1px dashed var(--perforation);
    background: transparent;
    color: var(--ink);
    font-size: var(--text-body);
    font-family: var(--sans);
    text-align: left;
    cursor: pointer;

    .dot {
        width: 10px;
        height: 10px;
        border-radius: 3px;
        flex: none;
    }

    .dot.all {
        background-color: var(--faded);
    }

    .name {
        flex: 1;
    }

    .check {
        color: var(--stamp);
        visibility: hidden;
    }
}

.option:last-child {
    border-bottom: 0;
}

.option.selected {
    .name {
        font-weight: 600;
    }

    .check {
        visibility: visible;
    }
}

.option :deep(.p-icon) {
    width: 16px;
    height: 16px;
}
</style>