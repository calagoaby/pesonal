<script setup lang="ts">
import ExclamationCircle from '@primeicons/vue/exclamation-circle';
withDefaults(defineProps<{
    label: string
    type?: 'text' | 'email' | 'password'
    autocomplete?: string
    placeholder?: string
    hint?: string
    error?: string
    required?: boolean
    disabled?: boolean
}>(), {
    type: 'text'
})

const model = defineModel<string>({ required: true })
const id = useId()
</script>

<template>
    <div class="fld" :class="{ bad: error }">
        <label class="lbl" :for="id">{{ label }}</label>
        <input
            :id="id"
            v-model="model"
            class="in"
            :type="type"
            :autocomplete="autocomplete"
            :placeholder="placeholder"
            :required="required"
            :disabled="disabled"
            :aria-invalid="error ? true : undefined"
        >
        <div v-if="error" class="err">
            <ExclamationCircle class="err-icon" />
            {{ error }}
        </div>
        <div v-else-if="hint" class="hint">{{ hint }}</div>
    </div>
</template>

<style scoped>
.fld {
    margin-top: var(--space-m);
}

.lbl {
    display: block;
    font-size: var(--text-micro);
    letter-spacing: .1em;
    text-transform: uppercase;
    color: var(--faded);
}

.in {
    width: 100%;
    min-height: 36px;
    padding: var(--space-s) 2px;
    border: 0;
    border-bottom: 1.5px dashed var(--perforation);
    border-radius: 0;
    background: transparent;
    font-family: var(--sans);
    font-size: var(--text-body);
    color: var(--ink);

    &::placeholder {
        color: var(--faded);
        opacity: .6;
    }

    &:focus {
        outline: none;
        border-bottom-color: var(--ink);
    }

    &:disabled {
        opacity: .5;
    }
}

.bad .in {
    border-bottom-color: var(--stamp);
}

.err {
    display: flex;
    gap: var(--space-xs);
    align-items: center;
    margin-top: var(--space-xs);
    font-family: var(--sans);
    font-size: var(--text-label);
    color: var(--stamp);
}

.err :deep(.err-icon) {
    width: 12px;
    height: 12px;
    flex: none;
}

.hint {
    margin-top: var(--space-xs);
    font-family: var(--sans);
    font-size: var(--text-label);
    color: var(--faded);
}
</style>