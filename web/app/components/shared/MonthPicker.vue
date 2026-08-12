<script setup lang="ts">
import ChevronLeft from '@primeicons/vue/chevron-left';
import ChevronRight from '@primeicons/vue/chevron-right';

const is_open = defineModel<boolean>('open', { required: true })
/** YYYY-MM */
const month = defineModel<string>('month', { required: true })

const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']


/** States — the year on screen, which browses independently of the selection. */
const view_year = ref(Number(month.value.slice(0, 4)))


/** Computed — the highlighted chip, or -1 when the selection sits in another year. */
const selected_index = computed(() => {
    const [year = 0, index = 1] = month.value.split('-').map(Number)

    return year === view_year.value ? index - 1 : -1
})


/** Functions */
function pick(index: number) {
    month.value = `${view_year.value}-${String(index + 1).padStart(2, '0')}`
    is_open.value = false
}

function goToPreviousYear() {
    view_year.value -= 1
}

function goToNextYear() {
    view_year.value += 1
}


// Reopening should land on the selected month's year, not wherever browsing stopped.
watch(is_open, (open) => {
    if (open) {
        view_year.value = Number(month.value.slice(0, 4))
    }
})
</script>

<template>
    <SharedSheet v-model:open="is_open" title="Jump to a month">
        <div class="year-row">
            <button aria-label="Previous year" @click="goToPreviousYear">
                <ChevronLeft />
            </button>
            <span>{{ view_year }}</span>
            <button aria-label="Next year" @click="goToNextYear">
                <ChevronRight />
            </button>
        </div>

        <div class="months">
            <button
                v-for="(label, index) in months"
                :key="label"
                class="month"
                :class="{ selected: index === selected_index }"
                @click="pick(index)"
            >
                {{ label }}
            </button>
        </div>
    </SharedSheet>
</template>

<style scoped>
.year-row {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--space-xl);
    font-family: var(--mono);
    font-size: var(--text-body);
    font-weight: 600;
    padding: var(--space-m) 0;

    button {
        display: flex;
        background: transparent;
        border: 0;
        color: var(--faded);
        cursor: pointer;
    }
}

.months {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: var(--space-s);
}

.month {
    text-align: center;
    font-family: var(--mono);
    font-size: var(--text-label);
    padding: var(--space-m) 0;
    border: 1px solid var(--perforation);
    border-radius: var(--radius-card);
    background: transparent;
    color: var(--ink);
    cursor: pointer;
}

.month.selected {
    background-color: var(--ink);
    border-color: var(--ink);
    color: var(--paper);
    font-weight: 600;
}

.year-row :deep(.p-icon) {
    width: 14px;
    height: 14px;
}
</style>
