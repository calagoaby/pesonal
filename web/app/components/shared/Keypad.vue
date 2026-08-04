<script setup lang="ts">
import DeleteLeft from '@primeicons/vue/delete-left';

const is_open = defineModel<boolean>('open', { required: true })
/**
 * Centavos. Digits shift in from the right the way an ATM pad works, so the value
 * is always a whole number of centavos and can never be a half-typed decimal.
 */
const amount = defineModel<number>('amount', { required: true })

/** ₱999,999.99 — beyond this the hero number wraps, and it's a personal tracker. */
const MAX_CENTAVOS = 99_999_999

const keys = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '00', '0']

/**
 * Typing runs off a local ref, not off the model. Writes to a `defineModel` round
 * trip through the parent, so two taps in the same tick would both read the old
 * value and the first digit would be lost.
 */
const entry = ref(amount.value)


/** Functions */
function press(key: string) {
    const next = Number(`${entry.value}${key}`)

    if (next > MAX_CENTAVOS) {
        return
    }

    commit(next)
}

function backspace() {
    commit(Math.floor(entry.value / 10))
}

function commit(next: number) {
    entry.value = next
    amount.value = next
}

function done() {
    is_open.value = false
}

function handleKeydown(event: KeyboardEvent) {
    if (!is_open.value) {
        return
    }

    // Numpad and number-row digits both arrive as '0'–'9'.
    if (/^\d$/.test(event.key)) {
        event.preventDefault()
        press(event.key)
        return
    }

    if (event.key === 'Backspace') {
        event.preventDefault()
        backspace()
        return
    }

    if (event.key === 'Enter') {
        event.preventDefault()
        done()
    }
}


watch(is_open, (open) => {
    if (open) {
        entry.value = amount.value
    }
})

// Escape is handled by SharedSheet — every sheet closes the same way.
onMounted(() => window.addEventListener('keydown', handleKeydown))
onBeforeUnmount(() => window.removeEventListener('keydown', handleKeydown))
</script>

<template>
    <SharedSheet v-model:open="is_open" title="Amount">
        <div class="amount">
            <span class="peso">₱</span>
            <span>{{ formatAmount(entry) }}</span>
            <span class="caret"></span>
        </div>
        <div class="keys">
            <button
                v-for="key in keys"
                :key="key"
                class="key"
                @click="press(key)"
            >
                {{ key }}
            </button>
            <button class="key" aria-label="Backspace" @click="backspace">
                <DeleteLeft />
            </button>
        </div>
        <button class="done" @click="done">Done</button>
    </SharedSheet>
</template>

<style scoped>
.amount {
    display: flex;
    align-items: baseline;
    justify-content: center;
    gap: 2px;
    font-family: var(--mono);
    font-size: var(--text-hero);
    font-weight: 600;
    font-variant-numeric: tabular-nums;
    padding: var(--space-m) 0 var(--space-s);

    .peso {
        color: var(--faded);
    }

    .caret {
        width: 2px;
        height: 28px;
        background-color: var(--stamp);
        transform: translateY(3px);
    }
}

.keys {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: var(--space-s);
    margin-top: var(--space-s);
}

.key {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: var(--space-m) 0;
    border: 1px solid var(--perforation);
    border-radius: var(--radius-card);
    background-color: var(--paper);
    color: var(--ink);
    font-family: var(--mono);
    font-size: var(--text-title);
    cursor: pointer;
}

.key:active {
    background-color: var(--desk);
}

.key :deep(.p-icon) {
    width: 18px;
    height: 18px;
    color: var(--faded);
}

.done {
    width: 100%;
    margin-top: var(--space-m);
    background-color: var(--stamp);
    color: var(--paper);
    border: 0;
    border-radius: var(--radius-pill);
    padding: var(--space-m);
    font-family: var(--mono);
    font-size: var(--text-label);
    font-weight: 600;
    letter-spacing: .14em;
    text-transform: uppercase;
    cursor: pointer;
}
</style>