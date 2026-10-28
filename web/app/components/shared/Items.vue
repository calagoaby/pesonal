<script setup lang="ts">
import Plus from '@primeicons/vue/plus';
import Times from '@primeicons/vue/times';
import type { ItemRow } from '~/utils/items';
import { useItems } from '~/composables/shared/useItems';

const items = defineModel<ItemRow[]>('items', { required: true })

const props = defineProps<{
    /** centavos — the typed total the items can't exceed */
    total: number
    /** The form's 422 errors; this section reads `items` and `items.N.*` from them. */
    errors?: Record<string, string[]>
}>()

const {
    is_keypad_open,
    editing_amount,
    is_over,
    can_add,
    counter,
    over_message,
    past_total_keys,
    row_errors,
    list_error,
    show_half_item,

    handleNameInput,
    handleNameFocus,
    handleNameBlur,
    openPrice,
    addItem,
    removeItem,
    missingPart
} = useItems(items, () => props.total, () => props.errors ?? {})
</script>

<template>
    <section ref="root" class="items" aria-label="Items">
        <div class="head">
            <span>Items</span>
            <span v-if="is_over" class="counter">{{ counter }}</span>
            <span v-else>optional</span>
        </div>

        <!-- size 9 = the length of the "Item name" placeholder, so an empty field is as wide as
             its hint in browsers without field-sizing -->
        <template v-for="row in items" :key="row.key">
            <div class="item">
                <input
                    :value="row.name"
                    class="name"
                    :class="{ missing: missingPart(row) === 'name' }"
                    placeholder="Item name"
                    maxlength="40"
                    :size="Math.max(row.name.length, 9)"
                    aria-label="Item name"
                    :data-item-key="row.key"
                    @input="handleNameInput(row.key, $event)"
                    @focus="handleNameFocus(row.key)"
                    @blur="handleNameBlur(row.key)"
                >
                <span class="leader" />
                <button
                    class="price"
                    :class="{ past: past_total_keys.has(row.key), missing: missingPart(row) === 'price', empty: row.amount === 0 }"
                    :aria-label="`Price of ${row.name.trim() || 'this item'}`"
                    @click="openPrice(row.key)"
                >
                    {{ formatAmountWithCurrency(row.amount) }}
                </button>
                <button class="remove" :aria-label="`Remove ${row.name.trim() || 'this item'}`" @click="removeItem(row.key)">
                    <Times />
                </button>
            </div>
            <p v-if="row_errors.has(row.key)" class="error row-error">{{ row_errors.get(row.key) }}</p>
        </template>

        <button v-if="can_add" class="add" @click="addItem">
            <Plus />
            Add item
        </button>

        <p v-if="is_over" class="error">{{ over_message }}</p>
        <p v-else-if="list_error" class="error">{{ list_error }}</p>
        <p v-if="show_half_item" class="error">Each item needs a name and a price.</p>
    </section>

    <SharedKeypad
        v-model:open="is_keypad_open"
        v-model:amount="editing_amount"
        title="Item price"
    />
</template>

<style scoped>
.items {
    margin-top: var(--space-l);
    font-family: var(--mono);
}

.head {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    font-size: var(--text-label);
    letter-spacing: .08em;
    text-transform: uppercase;
    color: var(--faded);

    .counter {
        color: var(--stamp);
        font-variant-numeric: tabular-nums;
        letter-spacing: 0;
    }
}

.item {
    display: flex;
    align-items: baseline;
    padding: 6px 2px;
    font-size: var(--text-body);
}

.name {
    /* Hugs the typed name so the dotted leader fills the rest. `size` in the template is
       the fallback for browsers without field-sizing (Firefox, iOS Safari). */
    field-sizing: content;
    flex: 0 1 auto;
    min-width: 4ch;
    max-width: 60%;
    border: 0;
    background: transparent;
    padding: 0;
    font-family: var(--sans);
    font-size: var(--text-body);
    color: var(--ink);
    outline: none;
}

.name::placeholder {
    color: var(--perforation);
}

.name.missing::placeholder {
    color: var(--stamp);
}

.leader {
    flex: 1;
    min-width: var(--space-l);
    border-bottom: 1.5px dotted var(--perforation);
    margin: 0 var(--space-s);
    transform: translateY(-3px);
}

.price {
    border: 0;
    background: transparent;
    padding: 0;
    font-family: var(--mono);
    font-size: var(--text-body);
    font-variant-numeric: tabular-nums;
    color: var(--ink);
    cursor: pointer;

    &.empty {
        color: var(--perforation);
    }

    &.past,
    &.missing {
        color: var(--stamp);
    }
}

.remove {
    display: flex;
    align-self: center;
    border: 0;
    background: transparent;
    padding: 0;
    margin-left: var(--space-s);
    color: var(--faded);
    cursor: pointer;

    :deep(.p-icon) {
        width: 10px;
        height: 10px;
    }
}

.add {
    display: flex;
    align-items: center;
    gap: 6px;
    border: 0;
    background: transparent;
    padding: var(--space-s) 2px;
    font-family: var(--mono);
    font-size: var(--text-label);
    letter-spacing: .1em;
    text-transform: uppercase;
    color: var(--stamp);
    cursor: pointer;

    :deep(.p-icon) {
        width: 10px;
        height: 10px;
    }
}

.error {
    font-family: var(--sans);
    font-size: var(--text-label);
    line-height: 1.5;
    color: var(--stamp);
    margin-top: var(--space-s);
}

.row-error {
    margin-top: 0;
    padding: 0 2px;
}
</style>