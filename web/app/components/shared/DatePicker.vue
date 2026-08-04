<script setup lang="ts">
import ChevronLeft from '@primeicons/vue/chevron-left';
import ChevronRight from '@primeicons/vue/chevron-right';

const is_open = defineModel<boolean>('open', { required: true })
/** YYYY-MM-DD */
const date = defineModel<string>('date', { required: true })

const {
    formatMonthLabel
} = useDateFormatter()

const { public: config } = useRuntimeConfig()

const weekdays = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa']


/** States — the month on screen, which browses independently of the selection. */
const view_month = ref(date.value.slice(0, 7))


/** Computed */
const today = computed(() => todayIn(config.timezone))
const yesterday = computed(() => shiftDay(today.value, -1))
const grid = computed(() => buildCalendarGrid(view_month.value))


/** Functions */
function pick(next: string) {
    date.value = next
    is_open.value = false
}

function goToPreviousMonth() {
    view_month.value = shiftMonth(view_month.value, -1)
}

function goToNextMonth() {
    view_month.value = shiftMonth(view_month.value, 1)
}


// Reopening should land on the selected date's month, not wherever browsing stopped.
watch(is_open, (open) => {
    if (open) {
        view_month.value = date.value.slice(0, 7)
    }
})
</script>

<template>
    <SharedSheet v-model:open="is_open" title="Pick a date">
        <div class="quick">
            <button class="chip" :class="{ selected: date === today }" @click="pick(today)">Today</button>
            <button class="chip" :class="{ selected: date === yesterday }" @click="pick(yesterday)">Yesterday</button>
        </div>

        <div class="month-row">
            <button aria-label="Previous month" @click="goToPreviousMonth">
                <ChevronLeft />
            </button>
            <span>{{ formatMonthLabel(view_month) }}</span>
            <button aria-label="Next month" @click="goToNextMonth">
                <ChevronRight />
            </button>
        </div>

        <div class="calendar">
            <div v-for="weekday in weekdays" :key="weekday" class="weekday">{{ weekday }}</div>
            <button
                v-for="cell in grid"
                :key="cell.date"
                class="day"
                :class="{
                    muted: !cell.in_month,
                    today: cell.date === today,
                    selected: cell.date === date,
                }"
                @click="pick(cell.date)"
            >
                {{ cell.day }}
            </button>
        </div>
    </SharedSheet>
</template>

<style scoped>
.quick {
    display: flex;
    gap: var(--space-s);
    padding: var(--space-m) 0;
}

.chip {
    font-family: var(--mono);
    font-size: var(--text-label);
    text-transform: uppercase;
    letter-spacing: .06em;
    padding: var(--space-xs) var(--space-m);
    border: 1px solid var(--perforation);
    border-radius: var(--radius-pill);
    background: transparent;
    color: var(--ink);
    cursor: pointer;
}

.chip.selected {
    background-color: var(--ink);
    color: var(--paper);
    border-color: var(--ink);
}

.month-row {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--space-xl);
    font-family: var(--mono);
    font-size: var(--text-body);
    font-weight: 600;
    padding: var(--space-s) 0;

    button {
        display: flex;
        background: transparent;
        border: 0;
        color: var(--faded);
        cursor: pointer;
    }
}

.calendar {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 2px;
    text-align: center;
    font-family: var(--mono);
    font-size: var(--text-label);
}

.weekday {
    font-size: 9px;
    letter-spacing: .06em;
    color: var(--faded);
    text-transform: uppercase;
    padding-bottom: var(--space-xs);
}

.day {
    padding: var(--space-s) 0;
    border: 1px solid transparent;
    border-radius: var(--radius-card);
    background: transparent;
    color: var(--ink);
    font-family: var(--mono);
    font-size: var(--text-label);
    cursor: pointer;
}

.day.muted {
    color: var(--perforation);
}

.day.today {
    border-style: dashed;
    border-color: var(--stamp);
    color: var(--stamp);
}

.day.selected {
    background-color: var(--ink);
    border-color: var(--ink);
    color: var(--paper);
    font-weight: 600;
}

.month-row :deep(.p-icon) {
    width: 14px;
    height: 14px;
}
</style>