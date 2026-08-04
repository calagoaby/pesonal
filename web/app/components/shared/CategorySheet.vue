<script setup lang="ts">
import Times from '@primeicons/vue/times';
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

function close() {
    is_open.value = false
}
</script>

<template>
    <div class="scrim" :class="{ shown: is_open }" @click="close"></div>
    <div class="sheet" :class="{ shown: is_open }" role="dialog" :aria-label="props.title">
        <div class="grab"></div>
        <div class="head">
            <span>{{ props.title }}</span>
            <button aria-label="Close" @click="close">
                <Times />
            </button>
        </div>
        <div class="list">
            <button
                v-if="props.allLabel"
                class="option"
                :class="{ selected: selected_id === null }"
                @click="pick(null)"
            >
                <span class="dot all"></span>
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
                <span class="dot" :style="{ backgroundColor: category.color }"></span>
                <span class="name">{{ category.name }}</span>
                <span class="emoji">{{ category.emoji }}</span>
                <Check class="check" />
            </button>
        </div>
    </div>
</template>

<style scoped>
.scrim {
    position: fixed;
    inset: 0;
    background-color: rgb(28 26 23 / 45%);
    opacity: 0;
    pointer-events: none;
    transition: opacity .25s;
}

.scrim.shown {
    opacity: 1;
    pointer-events: auto;
}

.sheet {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: var(--paper);
    border-top: 2px solid var(--ink);
    border-radius: 18px 18px 0 0;
    padding: 0 var(--space-l) var(--space-l);
    transform: translateY(110%);
    transition: transform .28s ease;
}

.sheet.shown {
    transform: translateY(0);
}

.grab {
    width: 40px;
    height: 4px;
    border-radius: var(--radius-pill);
    background-color: var(--perforation);
    margin: var(--space-m) auto var(--space-s);
}

.head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-family: var(--mono);
    font-size: var(--text-micro);
    letter-spacing: .1em;
    text-transform: uppercase;
    color: var(--faded);
    padding: 2px 2px var(--space-s);
    border-bottom: 1.5px dashed var(--perforation);

    button {
        display: flex;
        background: transparent;
        border: 0;
        color: var(--ink);
        cursor: pointer;
    }
}

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

.head :deep(.p-icon) {
    width: 18px;
    height: 18px;
}

.option :deep(.p-icon) {
    width: 16px;
    height: 16px;
}
</style>