<script setup lang="ts">
import Times from '@primeicons/vue/times';
import { getExpense } from '~/repositories/expenses';
import { useEditExpense } from '~/composables/expenses/useEditExpense';

// layout: false so the page can fill the modal layout's named footer slot —
// the commit bar has to sit outside the scroll region, beside it, not in it.
definePageMeta({ layout: false })

const route = useRoute()
const id = Number(route.params.id)

const { data: expense } = await useAsyncData(`expense-${id}`, () => getExpense(id))

// A row only ever links to an id that exists, so a miss means a hand-typed or
// stale URL — the full-page 404 is the right dead end, not an empty form.
if (!expense.value) {
    throw createError({ statusCode: 404, statusMessage: 'That entry is not on the roll.' })
}

const {
    can_save,
    is_saving,
    failure,
    save,
    entry
} = useEditExpense(expense.value)

const {
    formatStamp
} = useDateFormatter()


/** States */
const is_toast_open = ref(false)


/** Functions */
async function record() {
    if (await save()) {
        is_toast_open.value = true
    }
}
</script>

<template>
    <NuxtLayout name="modal">
        <div class="modal-bar">
            <span class="title">Edit entry</span>
            <NuxtLink to="/expenses" class="close" aria-label="Close">
                <Times />
            </NuxtLink>
        </div>

        <ExpensesExpenseForm :subtitle="`· entry no. ${entry.id} ·`">
            <div class="audit">
                Recorded {{ formatStamp(entry.created_at) }}<br>
                Last changed {{ formatStamp(entry.updated_at) }}
            </div>
        </ExpensesExpenseForm>

        <template #footer>
            <div class="commit-bar">
                <p v-if="failure" class="failure">{{ failure }}</p>
                <div class="actions">
                    <NuxtLink to="/expenses" class="cancel">Cancel</NuxtLink>
                    <button class="record" :disabled="!can_save || is_saving" @click="record">
                        {{ is_saving ? 'Saving…' : 'Save changes' }}
                    </button>
                </div>
            </div>
        </template>
    </NuxtLayout>

    <SharedToast v-model:open="is_toast_open" message="Saved changes" />
</template>

<style scoped>
.modal-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 var(--space-xs) var(--space-s);
}

.title {
    font-family: var(--mono);
    font-size: var(--text-label);
    letter-spacing: .14em;
    text-transform: uppercase;
    color: var(--faded);
}

.close {
    display: flex;
    color: var(--ink);
}

.close :deep(.p-icon) {
    width: 18px;
    height: 18px;
}

.audit {
    text-align: center;
    font-size: 9px;
    letter-spacing: .14em;
    text-transform: uppercase;
    color: var(--perforation);
    margin-top: var(--space-l);
    line-height: 1.7;
}

.commit-bar {
    flex: none;
    background-color: var(--paper);
    border-top: 1.5px dashed var(--perforation);
    padding: var(--space-m) var(--space-l) var(--space-l);
}

.failure {
    font-size: var(--text-label);
    color: var(--stamp);
    text-align: center;
    margin-bottom: var(--space-s);
}

.actions {
    display: flex;
    align-items: center;
    gap: var(--space-m);
}

.cancel {
    flex: none;
    font-family: var(--mono);
    font-size: var(--text-label);
    letter-spacing: .1em;
    text-transform: uppercase;
    color: var(--faded);
    padding: var(--space-m);
    text-decoration: none;
}

.record {
    flex: 1;
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
    box-shadow: 0 8px 18px rgb(200 71 46 / 32%);
}

.record:disabled {
    background-color: var(--perforation);
    color: var(--faded);
    box-shadow: none;
    cursor: not-allowed;
}
</style>
