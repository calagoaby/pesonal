<script setup lang="ts">
import ArrowDownRight from '@primeicons/vue/arrow-down-right';
import ArrowUpRight from '@primeicons/vue/arrow-up-right';
import { useMonthlyStatsContext } from '~/composables/dashboard/context/MonthlyStatsContext';

const {
    month,
    total,
    change_pct,
    daily_average,
    transaction_count
} = useMonthlyStatsContext()

const {
    formatMonthLabel,
    formatMonthName
} = useDateFormatter()


/** Computed */
const spent_less = computed(() => (change_pct.value ?? 0) < 0)
const receipt_label = computed(() => `Official Receipt — ${formatMonthLabel(month.value).toUpperCase()}`)
const previous_label = computed(() => formatMonthName(shiftMonth(month.value, -1)).toUpperCase())
</script>

<template>
    <SharedReceipt class="total-receipt" torn-position="bottom" :sub-text="receipt_label">
        <SharedPerf />

        <div class="tlabel">TOTAL DAMAGE</div>
        <div class="total">{{ formatAmountWithCurrency(total) }}</div>
        <div v-if="change_pct !== null" class="stamp-row">
            <span class="stamp" :class="{ over: !spent_less }">
                <ArrowDownRight v-if="spent_less" />
                <ArrowUpRight v-else />
                {{ Math.abs(change_pct) }}% VS {{ previous_label }}
            </span>
        </div>
        <div class="note">Petsa de peligro? Hindi today.</div>
        <SharedPerf />
        <div class="lrow">
            <div class="label">DAILY AVG</div>
            <div class="fill"/>
            <div class="val">{{ formatAmountWithCurrency(daily_average) }}</div>
        </div>
        <div class="lrow">
            <div class="label">TRANSACTIONS</div>
            <div class="fill"/>
            <div class="val">{{ transaction_count }}</div>
        </div>

        <SharedBarcode class="receipt-barcode" :length="22" />

        <div class="ono">NO. {{ month }}</div>
        <div class="thanks">*** salamat — come again ***</div>
    </SharedReceipt>
</template>

<style scoped>
.total-receipt {
    margin: var(--space-l) 0 var(--space-2xl);
}

.tlabel {
    text-align: center;
    letter-spacing: 1.5px;
    color: var(--faded);
    font-size: var(--text-micro);
}

.total {
    text-align: center;
    font-weight: 600;
    font-size: var(--text-hero);
    margin-top: var(--space-xs);
    font-variant-numeric: tabular-nums;
}

.stamp-row {
    text-align: center;
    margin-top: var(--space-m);
}

.stamp {
    display: inline-flex;
    align-items: center;
    gap: var(--space-xs);
    border: 2px solid var(--under);
    color: var(--under);
    font-size: var(--text-micro);
    font-weight: 600;
    letter-spacing: 1px;
    padding: var(--space-xs) var(--space-s);
    border-radius: var(--radius-card);
    transform: rotate(-7deg);
    opacity: 0.92;
}

.stamp.over {
    border-color: var(--stamp);
    color: var(--stamp);
}

.note {
    text-align: center;
    font-family: var(--sans);
    font-size: var(--text-label);
    color: var(--faded);
    margin-top: var(--space-m);
}

.lrow {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: var(--space-s);
    font-size: var(--text-label);
    margin-top: var(--space-s);
    
    .label {
        color: var(--faded);
    }

    .fill {
        flex: 1;
        border-bottom: 1px dotted var(--perforation);
    }
}

.receipt-barcode {
    margin-top: var(--space-l);
}

.ono {
    text-align: center;
    font-size: var(--text-micro);
    margin-top: var(--space-xs);
    color: var(--faded);
}

.thanks {
    text-align: center;
    font-size: var(--text-micro);
    color: var(--faded);
    margin-top: var(--space-s);
}
</style>