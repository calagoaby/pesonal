<script setup lang="ts">
import ChevronDown from '@primeicons/vue/chevron-down';
import Calendar from '@primeicons/vue/calendar';
import Pencil from '@primeicons/vue/pencil';
import { useAddExpense } from '~/composables/expenses/useAddExpense';

const {
    amount,
    category,
    date,
    note
} = useAddExpense()

const {
    formatFieldDate
} = useDateFormatter()
</script>

<template>
    <SharedReceipt class="add-receipt" torn-position="top" sub-text="· unrecorded damage ·">
        <SharedPerf />

        <div class="amount-label">Amount</div>
        <div class="amount">
            <span class="peso">₱</span>
            <span>{{ formatAmount(amount) }}</span>
            <span class="caret"></span>
        </div>
        <div class="amount-hint">Tap to open the keypad</div>

        <SharedPerf />

        <button class="field">
            <span class="label">Category</span>
            <span class="value">
                <template v-if="category">
                    <span class="dot" :style="{ backgroundColor: category.color }"></span>
                    {{ category.name }}
                </template>
                <span v-else class="placeholder">Choose one</span>
                <ChevronDown />
            </span>
        </button>

        <button class="field">
            <span class="label">Date</span>
            <span class="value">
                {{ formatFieldDate(date) }}
                <Calendar />
            </span>
        </button>

        <button class="field">
            <span class="label">Note</span>
            <span class="value">
                <span :class="{ placeholder: !note }">{{ note || 'Lunch with team' }}</span>
                <Pencil />
            </span>
        </button>

        <div class="quip">* no take-backs, only edits *</div>
    </SharedReceipt>
</template>

<style scoped>
.add-receipt {
    margin-bottom: var(--space-l);
}

.amount-label {
    text-align: center;
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

.quip {
    text-align: center;
    font-size: 9px;
    letter-spacing: .18em;
    text-transform: uppercase;
    color: var(--perforation);
    margin-top: var(--space-l);
}
</style>