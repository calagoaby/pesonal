<script setup lang="ts">
import Times from '@primeicons/vue/times';

// layout: false so the page can fill the modal layout's named footer slot —
// the commit bar has to sit outside the scroll region, beside it, not in it.
definePageMeta({ layout: false })
</script>

<template>
    <NuxtLayout name="modal">
        <div class="modal-bar">
            <span class="title">New entry</span>
            <NuxtLink to="/" class="close" aria-label="Close">
                <Times />
            </NuxtLink>
        </div>

        <ExpensesAddExpense />

        <template #footer>
            <div class="commit-bar">
                <NuxtLink to="/" class="cancel">Cancel</NuxtLink>
                <button class="record">Record it</button>
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
    display: flex;
    align-items: center;
    gap: var(--space-m);
    flex: none;
    background-color: var(--paper);
    border-top: 1.5px dashed var(--perforation);
    padding: var(--space-m) var(--space-l) var(--space-l);
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
    box-shadow: 0 8px 18px rgba(200, 71, 46, .32);
}
</style>