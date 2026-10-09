<script setup lang="ts">
import ChevronDown from '@primeicons/vue/chevron-down';
import Calendar from '@primeicons/vue/calendar';
import { useRecurringFormContext } from '~/composables/recurring/context/RecurringFormContext';

defineProps<{
    subtitle: string
}>()

const {
    amount,
    category_id,
    note,
    day_of_month,
    start_date,
    end_date,
    active,
    errors
} = useRecurringFormContext()

const {
    categories
} = useCategories()

const {
    formatFieldDate
} = useDateFormatter()


/** States */
const is_keypad_open = ref(false)
const is_category_sheet_open = ref(false)
const is_day_picker_open = ref(false)
const is_start_picker_open = ref(false)
const is_end_picker_open = ref(false)


/** Computed */
const category = computed(() => categories.value.find(item => item.id === category_id.value) ?? null)
// 29+ can exceed a short month (February has 28); the picker warns on the same rule.
const clamps = computed(() => day_of_month.value >= 29)
</script>

<template>
    <SharedReceipt class="recurring-form" torn-position="top" :sub-text="subtitle">
        <SharedPerf />

        <button class="amount-field" @click="is_keypad_open = true">
            <span class="amount-label">Amount</span>
            <span class="amount">
                <span class="peso">₱</span>
                <span>{{ formatAmount(amount) }}</span>
                <span class="caret"/>
            </span>
            <span class="amount-hint">Charged every month, automatically</span>
        </button>

        <SharedPerf />

        <button class="field" @click="is_category_sheet_open = true">
            <span class="label">Category</span>
            <span class="value">
                <template v-if="category">
                    <span class="dot" :style="{ backgroundColor: category.color }"/>
                    {{ category.name }}
                </template>
                <span v-else class="placeholder">Choose one</span>
                <ChevronDown />
            </span>
        </button>

        <div class="field">
            <span class="label">Note</span>
            <input v-model="note" class="note-input" placeholder="Apartment rent" maxlength="40" aria-label="Note">
        </div>

        <button class="field" @click="is_day_picker_open = true">
            <span class="label">Every</span>
            <span class="value">
                {{ ordinal(day_of_month) }} of the month
                <ChevronDown />
            </span>
        </button>
        <p v-if="clamps" class="clamp">Short months charge on their last day instead.</p>

        <button class="field" @click="is_start_picker_open = true">
            <span class="label">Starts</span>
            <span class="value">
                {{ formatFieldDate(start_date) }}
                <Calendar />
            </span>
        </button>

        <button class="field" @click="is_end_picker_open = true">
            <span class="label">Ends</span>
            <span class="value">
                <span :class="{ placeholder: !end_date }">{{ end_date ? formatFieldDate(end_date) : 'No end' }}</span>
                <Calendar />
            </span>
        </button>

        <div class="switch-row">
            <span class="switch-copy">
                <span class="label">Active</span>
                <span class="meaning">Paused orders stop charging and leave the total</span>
            </span>
            <button
                class="switch"
                :class="{ off: !active }"
                role="switch"
                :aria-checked="active"
                aria-label="Active"
                @click="active = !active"
            />
        </div>

        <p v-for="(messages, field) in errors" :key="field" class="field-error">
            {{ messages[0] }}
        </p>

        <!-- Add shows the quip; edit fills this with its delete action and audit line. -->
        <slot>
            <div class="quip">* set it once, forget it forever *</div>
        </slot>
    </SharedReceipt>

    <SharedKeypad
        v-model:open="is_keypad_open"
        v-model:amount="amount"
    />

    <SharedCategorySheet
        v-model:open="is_category_sheet_open"
        v-model:selected="category_id"
        :categories="categories"
    />

    <RecurringDayPicker
        v-model:open="is_day_picker_open"
        v-model:day="day_of_month"
    />

    <SharedDatePicker
        v-model:open="is_start_picker_open"
        v-model:date="start_date"
    />

    <SharedDatePicker
        v-model:open="is_end_picker_open"
        :date="end_date ?? start_date"
        :unselected="!end_date"
        clear-label="No end"
        @update:date="end_date = $event"
        @clear="end_date = null"
    />
</template>

<style scoped>
.recurring-form {
    margin-bottom: var(--space-l);
}

.amount-field {
    display: block;
    width: 100%;
    border: 0;
    background: transparent;
    padding: 0;
    color: var(--ink);
    cursor: pointer;
}

.amount-label {
    display: block;
    text-align: center;
    font-family: var(--mono);
    font-size: var(--text-micro);
    letter-spacing: 1.5px;
    text-transform: uppercase;
    color: var(--faded);
}

.amount {
    display: flex;
    align-items: baseline;
    justify-content: center;
    gap: 2px;
    font-size: var(--text-hero);
    font-weight: 600;
    font-variant-numeric: tabular-nums;
    margin-top: var(--space-xs);

    .peso {
        color: var(--faded);
    }

    .caret {
        width: 2px;
        height: 30px;
        background-color: var(--stamp);
        transform: translateY(4px);
    }
}

.amount-hint {
    display: block;
    text-align: center;
    font-family: var(--sans);
    font-size: var(--text-label);
    color: var(--faded);
    margin-top: var(--space-s);
}

.field {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: transparent;
    border: 0;
    border-bottom: 1.5px dashed var(--perforation);
    padding: var(--space-s) 2px;
    font-family: var(--mono);
    font-size: var(--text-label);
    letter-spacing: .08em;
    text-transform: uppercase;
    color: var(--ink);
    text-align: left;
    cursor: pointer;

    .label {
        color: var(--faded);
    }

    .value {
        display: flex;
        align-items: center;
        gap: var(--space-s);
    }

    .dot {
        width: 9px;
        height: 9px;
        border-radius: 2px;
        flex: none;
    }

    .placeholder {
        color: var(--perforation);
        font-family: var(--sans);
        font-size: var(--text-body);
        text-transform: none;
        letter-spacing: 0;
    }
}

.field :deep(.p-icon) {
    width: 12px;
    height: 12px;
    color: var(--faded);
}

.note-input {
    flex: 1;
    border: 0;
    background: transparent;
    text-align: right;
    font-family: var(--sans);
    font-size: var(--text-body);
    color: var(--ink);
    outline: none;
}

.note-input::placeholder {
    color: var(--perforation);
}

.clamp {
    font-family: var(--sans);
    font-size: var(--text-micro);
    color: var(--faded);
    text-align: right;
    padding: var(--space-xs) 2px 0;
    line-height: 1.5;
}

.switch-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: var(--space-m) 2px;
    border-bottom: 1.5px dashed var(--perforation);
}

.switch-copy {
    display: block;

    .label {
        font-size: var(--text-label);
        letter-spacing: .08em;
        text-transform: uppercase;
        color: var(--faded);
    }

    .meaning {
        display: block;
        font-family: var(--sans);
        font-size: var(--text-micro);
        color: var(--faded);
        margin-top: 2px;
    }
}

.switch {
    width: 46px;
    height: 26px;
    border-radius: var(--radius-pill);
    background-color: var(--under);
    border: 0;
    position: relative;
    flex: none;
    cursor: pointer;
}

.switch::after {
    content: "";
    position: absolute;
    top: 3px;
    left: 23px;
    width: 20px;
    height: 20px;
    border-radius: var(--radius-pill);
    background-color: var(--paper);
    transition: left .15s ease;
}

.switch.off {
    background-color: var(--perforation);
}

.switch.off::after {
    left: 3px;
}

.field-error {
    font-family: var(--sans);
    font-size: var(--text-label);
    color: var(--stamp);
    margin-top: var(--space-m);
}

.quip {
    text-align: center;
    font-size: 9px;
    letter-spacing: .18em;
    text-transform: uppercase;
    color: var(--perforation);
    margin-top: var(--space-l);
}
</style>