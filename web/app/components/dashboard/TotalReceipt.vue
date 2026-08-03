<script setup lang="ts">
import ArrowDownRight from '@primeicons/vue/arrow-down-right';
</script>

<template>
    <SharedReceipt class="total-receipt" torn-position="bottom" sub-text="Official Receipt — JUNE 2026">
        <SharedPerf />

        <div class="tlabel">TOTAL DAMAGE</div>
        <div class="total">₱43,210</div>
        <div class="stamp-row">
            <span class="stamp">
                <ArrowDownRight />
                 12% VS MAY
            </span>
        </div>
        <div class="note">Petsa de peligro? Hindi today.</div>
        <SharedPerf />
        <div class="lrow">
            <div class="label">DAILY AVG</div>
            <div class="fill"></div>
            <div class="val">₱2,540</div>
        </div>
        <div class="lrow">
            <div class="label">TRANSACTIONS</div>
            <div class="fill"></div>
            <div class="val">38</div>
        </div>
        
        <SharedBarcode class="receipt-barcode" :length="22" />

        <div class="ono">NO. 0617-2026</div>
        <div class="thanks">*** salamat — come again ***</div>
    </SharedReceipt>
</template>

<style scoped>
/* The shell has no margin of its own — spacing is the consumer's call. */
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