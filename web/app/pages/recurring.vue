<script setup lang="ts">
import type { RecurringExpense } from '#shared/types/Recurring';
import ExclamationTriangle from '@primeicons/vue/exclamation-triangle';
import Sync from '@primeicons/vue/sync';
import Plus from '@primeicons/vue/plus';
import { useRecurring } from '~/composables/recurring/useRecurring';

const {
    templates,
    groups,
    monthly_total,
    active_count,
    error,
    refresh
} = await useRecurring()

const {
    formatMonthAndYear
} = useDateFormatter()


/** Computed */
const is_empty = computed(() => !error.value && templates.value.length === 0)


/** Functions */
function sinceLabel(template: RecurringExpense) {
    // Paused rows date from the pause (updated_at); active ones from their start,
    // noting an end only when one is set.
    if (!template.active) {
        return `paused since ${formatMonthAndYear(template.updated_at.slice(0, 10))}`
    }

    const bound = template.end_date ? `ends ${formatMonthAndYear(template.end_date)}` : 'no end'

    return `since ${formatMonthAndYear(template.start_date)} · ${bound}`
}
</script>

<template>
    <SharedReceipt class="recurring-roll" torn-position="top" sub-text="· standing orders ·">
        <SharedPerf />

        <SharedNotice v-if="error" title="Printer jammed" tone="alert">
            <template #mark>
                <ExclamationTriangle />
            </template>
            Couldn't reach the server.<br>
            Your standing orders are safe — this screen just came up blank.
            <template #action>
                <button class="act" @click="refresh()">Try again</button>
            </template>
        </SharedNotice>

        <SharedNotice v-else-if="is_empty" title="Nothing on repeat">
            <template #mark>
                <Sync />
            </template>
            No standing orders yet.<br>
            The rent, the subscriptions — the money that goes out on schedule.
            <template #action>
                <NuxtLink to="/add-recurring" class="act">Add the first one</NuxtLink>
            </template>
        </SharedNotice>

        <template v-else>
            <div class="commit">
                <div class="commit-label">Every month, automatically</div>
                <div class="commit-value">{{ formatAmountWithCurrency(monthly_total) }}</div>
                <div class="commit-cap">Gone before you wake up.</div>
            </div>

            <div v-for="group in groups" :key="group.day">
                <div class="day-divider">Every {{ ordinal(group.day) }}</div>
                <NuxtLink
                    v-for="template in group.items"
                    :key="template.id"
                    :to="`/edit-recurring/${template.id}`"
                    class="template"
                    :class="{ paused: !template.active }"
                >
                    <div class="line">
                        <span class="desc">
                            {{ template.note }}
                            <span class="tag">{{ template.category.name }}</span>
                        </span>
                        <span class="lead"></span>
                        <span class="amount">{{ formatAmountWithCurrency(template.amount) }}</span>
                    </div>
                    <div class="meta">
                        <span class="since">{{ sinceLabel(template) }}</span>
                        <span class="pill" :class="{ off: !template.active }">
                            {{ template.active ? 'Active' : 'Paused' }}
                        </span>
                    </div>
                </NuxtLink>
            </div>

            <NuxtLink to="/add-recurring" class="add-new">
                <Plus />
                new standing order
            </NuxtLink>

            <SharedPerf />

            <div class="foot">
                <SharedBarcode class="roll-barcode" gap="5px" height="36px" />
                <div class="count">{{ templates.length }} templates · {{ active_count }} active</div>
                <div class="quip">* the money leaves without asking *</div>
            </div>
        </template>
    </SharedReceipt>
</template>

<style scoped>
.recurring-roll {
    margin-bottom: var(--space-l);
}

.commit {
    text-align: center;
}

.commit-label {
    font-size: var(--text-micro);
    letter-spacing: 1.5px;
    text-transform: uppercase;
    color: var(--faded);
}

.commit-value {
    font-size: var(--text-hero);
    font-weight: 600;
    font-variant-numeric: tabular-nums;
    margin-top: var(--space-xs);
}

.commit-cap {
    font-family: var(--sans);
    font-size: var(--text-label);
    color: var(--faded);
    margin-top: var(--space-s);
}

.day-divider {
    display: flex;
    align-items: center;
    gap: var(--space-s);
    font-size: var(--text-micro);
    letter-spacing: .1em;
    text-transform: uppercase;
    color: var(--faded);
    margin: var(--space-l) 0 var(--space-xs);
}

.day-divider::before, .day-divider::after {
    content: "";
    flex: 1;
    border-top: 1.5px dashed var(--perforation);
}

.template {
    display: block;
    padding: var(--space-s) 0;
    color: var(--ink);
    text-decoration: none;
}

.line {
    display: flex;
    align-items: baseline;
}

.desc {
    font-family: var(--sans);
    font-size: var(--text-body);
}

.tag {
    font-family: var(--mono);
    font-size: 9px;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: var(--faded);
    margin-left: var(--space-s);
}

.lead {
    flex: 1;
    border-bottom: 1.5px dotted var(--perforation);
    margin: 0 var(--space-s);
    transform: translateY(-3px);
}

.amount {
    font-family: var(--mono);
    font-size: var(--text-body);
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}

.meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: var(--space-xs);
}

.since {
    font-family: var(--mono);
    font-size: var(--text-micro);
    color: var(--faded);
    letter-spacing: .04em;
}

.pill {
    font-family: var(--mono);
    font-size: 9px;
    letter-spacing: .1em;
    text-transform: uppercase;
    padding: 2px var(--space-s);
    border-radius: var(--radius-pill);
    border: 1px solid var(--under);
    color: var(--under);
}

.pill.off {
    border-color: var(--perforation);
    color: var(--faded);
}

.template.paused .desc, .template.paused .amount {
    color: var(--faded);
}

.template.paused .amount {
    text-decoration: line-through;
}

.add-new {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--space-s);
    margin-top: var(--space-l);
    padding-top: var(--space-m);
    border-top: 1.5px dashed var(--perforation);
    color: var(--stamp);
    font-family: var(--mono);
    font-size: var(--text-label);
    letter-spacing: .14em;
    text-transform: uppercase;
    text-decoration: none;
}

.add-new :deep(.p-icon) {
    width: 12px;
    height: 12px;
}

.act {
    display: inline-block;
    background-color: var(--stamp);
    color: var(--paper);
    border: 0;
    border-radius: var(--radius-pill);
    padding: var(--space-s) var(--space-l);
    font-family: var(--mono);
    font-size: var(--text-label);
    font-weight: 600;
    letter-spacing: .12em;
    text-transform: uppercase;
    text-decoration: none;
    cursor: pointer;
}

.foot {
    text-align: center;
}

.roll-barcode {
    margin: var(--space-s) auto;
}

.count {
    font-size: var(--text-micro);
    letter-spacing: .14em;
    text-transform: uppercase;
    color: var(--faded);
}

.quip {
    font-size: 9px;
    letter-spacing: .18em;
    text-transform: uppercase;
    color: var(--perforation);
    margin-top: var(--space-s);
}
</style>