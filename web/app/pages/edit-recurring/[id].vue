<script setup lang="ts">
import Times from '@primeicons/vue/times';
import Trash from '@primeicons/vue/trash';
import { getRecurring } from '~/repositories/recurring';
import { useEditRecurring } from '~/composables/recurring/useEditRecurring';
import { useToaster } from '~/stores/toaster';

definePageMeta({ layout: false })

const { public: config } = useRuntimeConfig()
const route = useRoute()
const id = Number(route.params.id)

const { data: template } = await useAsyncData(`recurring-${id}`, () => getRecurring(id))

if (!template.value) {
    throw createError({ statusCode: 404, statusMessage: 'That standing order is gone.' })
}

const {
    can_save,
    is_saving,
    failure,
    save,
    remove,
    restore,
    entry
} = useEditRecurring(template.value)

const {
    formatStamp,
    formatMonthAndYear,
    formatFieldDate
} = useDateFormatter()

const toaster = useToaster()


/** States */
const is_void_slip_open = ref(false)


/** Computed */
const void_detail = computed(() => `Every ${ordinal(entry.value.day_of_month)} · ${entry.value.category.name}`)

const next_charge = computed(() => {
    if (!entry.value.active) {
        return null
    }

    return nextChargeDate(entry.value.day_of_month, todayIn(config.timezone), entry.value.start_date, entry.value.end_date)
})


/** Functions */
async function record() {
    if (await save()) {
        toaster.show({ message: 'Standing order saved' })
    }
}

async function deleteOrder() {
    // Capture before we leave — the undo re-creates from this snapshot on the list.
    const removed = entry.value

    if (await remove()) {
        toaster.show({
            message: `Deleted · ${removed.note || removed.category.name}`,
            action: { label: 'Undo', run: () => restore(removed) },
        })
        navigateTo('/recurring')
    } else {
        // Reveal the failure line, which the scrim was covering.
        is_void_slip_open.value = false
    }
}
</script>

<template>
    <NuxtLayout name="modal">
        <div class="modal-bar">
            <span class="title">Edit standing order</span>
            <NuxtLink to="/recurring" class="close" aria-label="Close">
                <Times />
            </NuxtLink>
        </div>

        <RecurringForm subtitle="· standing order ·">
            <button class="void-row" @click="is_void_slip_open = true">
                <Trash />
                Delete this order
            </button>
            <div class="audit">
                Created {{ formatStamp(entry.created_at) }}<br>
                <template v-if="entry.active && next_charge">Next charge {{ formatFieldDate(next_charge) }}</template>
                <template v-else-if="entry.active">No more charges</template>
                <template v-else>Paused since {{ formatMonthAndYear(entry.updated_at.slice(0, 10)) }}</template>
            </div>
        </RecurringForm>

        <template #footer>
            <div class="commit-bar">
                <p v-if="failure" class="failure">{{ failure }}</p>
                <div class="actions">
                    <NuxtLink to="/recurring" class="cancel">Cancel</NuxtLink>
                    <button class="record" :disabled="!can_save || is_saving" @click="record">
                        {{ is_saving ? 'Saving…' : 'Save order' }}
                    </button>
                </div>
            </div>
        </template>
    </NuxtLayout>

    <SharedVoidSlip
        v-model:open="is_void_slip_open"
        title="Delete this order?"
        :subject="entry.note || entry.category.name"
        :amount="entry.amount"
        :detail="void_detail"
        warning="Already recorded charges stay put."
        confirm-label="Delete it"
        @confirm="deleteOrder"
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