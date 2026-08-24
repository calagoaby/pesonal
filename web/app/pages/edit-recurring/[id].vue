<script setup lang="ts">
import Times from '@primeicons/vue/times';
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
    entry
} = useEditRecurring(template.value)

const {
    formatStamp,
    formatMonthAndYear,
    formatFieldDate
} = useDateFormatter()

const toaster = useToaster()


/** Computed */
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