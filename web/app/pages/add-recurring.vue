<script setup lang="ts">
import Times from '@primeicons/vue/times';
import { useAddRecurring } from '~/composables/recurring/useAddRecurring';
import { useToaster } from '~/stores/toaster';

// layout: false so the page can fill the modal layout's named footer slot —
// the commit bar has to sit outside the scroll region, beside it, not in it.
definePageMeta({ layout: false })

const {
    can_save,
    is_saving,
    failure,
    save
} = useAddRecurring()

const toaster = useToaster()


/** Functions */
async function record() {
    if (await save()) {
        toaster.show({ message: 'Standing order saved' })
        navigateTo('/recurring')
    }
}
</script>

<template>
    <NuxtLayout name="modal">
        <div class="modal-bar">
            <span class="title">New standing order</span>
            <NuxtLink to="/recurring" class="close" aria-label="Close">
                <Times />
            </NuxtLink>
        </div>

        <RecurringForm subtitle="· standing order ·" />

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