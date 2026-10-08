<script setup lang="ts">
import Times from '@primeicons/vue/times';
import Trash from '@primeicons/vue/trash';
import { getExpense } from '~/repositories/expenses';
import { useEditExpense } from '~/composables/expenses/useEditExpense';
import { useToaster } from '~/stores/toaster';

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
    remove,
    restore,
    entry
} = useEditExpense(expense.value)

const {
    formatStamp,
    formatDailyDate
} = useDateFormatter()

const toaster = useToaster()


/** States */
const is_void_slip_open = ref(false)


/** Computed — the muted line under the subject on the void slip. */
const void_detail = computed(() => `${formatDailyDate(entry.value.date)} · ${entry.value.category.name}`)


/** Functions */
async function record() {
    if (await save()) {
        toaster.show({ message: 'Saved changes' })
    }
}

async function voidEntry() {
    // Capture before we leave — the toast and its undo outlive this page's entry.
    const removed = entry.value

    if (await remove()) {
        toaster.show({
            message: `Voided · ${formatAmountWithCurrency(removed.amount)}`,
            action: {
                label: 'Undo',
                run: async () => {
                    if (!await restore(removed)) {
                        toaster.show({ message: 'Could not bring the entry back' })
                    }
                },
            },
        })
        navigateTo('/expenses')
    } else {
        // Reveal the failure line, which the scrim was covering.
        is_void_slip_open.value = false
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
            <button class="void-row" @click="is_void_slip_open = true">
                <Trash />
                Void this entry
            </button>
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

    <SharedVoidSlip
        v-model:open="is_void_slip_open"
        title="Void this entry?"
        :subject="entry.note ?? 'This entry'"
        :amount="entry.amount"
        :detail="void_detail"
        @confirm="voidEntry"
    />
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

.void-row {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--space-s);
    margin-top: var(--space-l);
    padding-top: var(--space-m);
    border: 0;
    border-top: 1.5px dashed var(--perforation);
    background: transparent;
    color: var(--stamp);
    font-family: var(--mono);
    font-size: var(--text-label);
    letter-spacing: .14em;
    text-transform: uppercase;
    cursor: pointer;
}

.void-row :deep(.p-icon) {
    width: 13px;
    height: 13px;
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