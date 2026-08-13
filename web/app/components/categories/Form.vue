<script setup lang="ts">
import type { Category, CategoryPayload } from '#shared/types/Category';
import Trash from '@primeicons/vue/trash';
import { createCategory, updateCategory } from '~/repositories/categories';

const is_open = defineModel<boolean>('open', { required: true })

const props = defineProps<{
    /** Null in create mode; the row being edited otherwise. */
    category: Category | null
}>()

const emit = defineEmits<{
    saved: []
    delete: []
}>()

// Fixed picker sets. The first eight colours are the category palette; the rest
// give new categories somewhere else to go.
const palette = [
    '#534F8C', '#5E7A3E', '#C9692E', '#3F6F97', '#B0577B', '#7A6A3E', '#2F7D52', '#8A857B',
    '#C8472E', '#E8B23A', '#8A6D3B', '#4A6E8A', '#6E4A8A', '#8A4A5E', '#3E7A6E', '#1C1A17',
]
const emojis = [
    '🍔', '🛒', '🚗', '🏠', '🎉', '💡', '💊', '🧾',
    '☕', '✈️', '🎁', '🐶', '📚', '💰', '🎬', '⚽',
]


/** States */
const name = ref('')
const color = ref(palette[0]!)
const emoji = ref(emojis[0]!)
const is_saving = ref(false)
const errors = ref<Record<string, string[]>>({})
const failure = ref('')


/** Computed */
const is_edit = computed(() => props.category !== null)
const can_save = computed(() => name.value.trim().length > 0)


/** Functions */
async function save() {
    if (!can_save.value) {
        return
    }

    const payload: CategoryPayload = {
        name: name.value.trim(),
        color: color.value,
        emoji: emoji.value,
    }

    const saved = await submitRequest(
        () => props.category ? updateCategory(props.category.id, payload) : createCategory(payload),
        { is_saving, errors, failure },
    )

    if (saved) {
        emit('saved')
        is_open.value = false
    }
}


// Seed from the row each time the sheet opens, so browsing to a different one
// (or reopening after a cancel) starts clean.
watch(is_open, (open) => {
    if (open) {
        name.value = props.category?.name ?? ''
        color.value = props.category?.color ?? palette[0]!
        emoji.value = props.category?.emoji ?? emojis[0]!
        errors.value = {}
        failure.value = ''
    }
})
</script>

<template>
    <SharedSheet v-model:open="is_open" :title="is_edit ? 'Edit category' : 'New category'">
        <div class="label">Name</div>
        <div class="name-field">
            <span class="dot" :style="{ backgroundColor: color }"></span>
            <span class="emoji">{{ emoji }}</span>
            <input v-model="name" placeholder="Name it" maxlength="24">
        </div>

        <div class="label">Color</div>
        <div class="swatches">
            <button
                v-for="swatch in palette"
                :key="swatch"
                class="swatch"
                :class="{ on: swatch === color }"
                :style="{ backgroundColor: swatch }"
                :aria-label="swatch"
                @click="color = swatch"
            ></button>
        </div>

        <div class="label">Emoji</div>
        <div class="emojis">
            <button
                v-for="option in emojis"
                :key="option"
                class="emoji-option"
                :class="{ on: option === emoji }"
                @click="emoji = option"
            >{{ option }}</button>
        </div>

        <p v-for="(messages, field) in errors" :key="field" class="error">{{ messages[0] }}</p>
        <p v-if="failure" class="error">{{ failure }}</p>

        <div class="actions">
            <button v-if="is_edit" class="delete" type="button" aria-label="Delete category" @click="emit('delete')">
                <Trash />
            </button>
            <button class="save" :disabled="!can_save || is_saving" @click="save">
                {{ is_saving ? 'Saving…' : 'Save' }}
            </button>
        </div>
    </SharedSheet>
</template>

<style scoped>
.label {
    font-family: var(--mono);
    font-size: var(--text-micro);
    letter-spacing: .1em;
    text-transform: uppercase;
    color: var(--faded);
    margin: var(--space-l) 0 var(--space-s);
}

.name-field {
    display: flex;
    align-items: center;
    gap: var(--space-m);
    border-bottom: 1.5px dashed var(--perforation);
    padding: var(--space-s) 2px;
}

.name-field .dot {
    width: 12px;
    height: 12px;
    border-radius: 3px;
    flex: none;
}

.name-field .emoji {
    font-size: var(--text-title);
}

.name-field input {
    flex: 1;
    border: 0;
    background: transparent;
    font-family: var(--sans);
    font-size: var(--text-body);
    color: var(--ink);
    outline: none;
}

.swatches {
    display: grid;
    grid-template-columns: repeat(8, 1fr);
    gap: var(--space-s);
}

.swatch {
    aspect-ratio: 1;
    border: 0;
    border-radius: var(--radius-card);
    position: relative;
    cursor: pointer;
}

.swatch.on::after {
    content: "";
    position: absolute;
    inset: -3px;
    border: 1.5px solid var(--ink);
    border-radius: var(--radius-card);
}

.emojis {
    display: grid;
    grid-template-columns: repeat(8, 1fr);
    gap: var(--space-s);
}

.emoji-option {
    aspect-ratio: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: var(--text-body);
    border: 1px solid var(--perforation);
    border-radius: var(--radius-card);
    background: transparent;
    cursor: pointer;
}

.emoji-option.on {
    border-color: var(--ink);
    background-color: var(--desk);
}

.error {
    font-family: var(--sans);
    font-size: var(--text-label);
    color: var(--stamp);
    margin-top: var(--space-m);
}

.actions {
    display: flex;
    gap: var(--space-s);
    margin-top: var(--space-l);
}

.delete {
    flex: none;
    display: flex;
    align-items: center;
    background: transparent;
    color: var(--stamp);
    border: 1px solid var(--perforation);
    border-radius: var(--radius-pill);
    padding: var(--space-m) var(--space-l);
    cursor: pointer;
}

.delete :deep(.p-icon) {
    width: 14px;
    height: 14px;
}

.save {
    flex: 1;
    background-color: var(--stamp);
    color: var(--paper);
    border: 0;
    border-radius: var(--radius-pill);
    padding: var(--space-m);
    font-family: var(--mono);
    font-size: var(--text-label);
    font-weight: 600;
    letter-spacing: .12em;
    text-transform: uppercase;
    cursor: pointer;
}

.save:disabled {
    background-color: var(--perforation);
    color: var(--faded);
    cursor: not-allowed;
}
</style>