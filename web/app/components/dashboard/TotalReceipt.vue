<script setup lang="ts">
import ArrowDownRight from '@primeicons/vue/arrow-down-right';
</script>

<template>
    <SharedReceipt torn-position="bottom" sub-text="Offical Receipt — JUNE 2026">
        <div class="tlabel">TOTAL DAMAGE</div>
        <div class="total">₱43,210</div>
        <div style="text-align: center; margin-top: var(--space-m);">
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
        
        <SharedBarcode :length="22" style="margin-top: var(--space-l);" />

        <div class="ono">NO. 0617-2026</div>
        <div class="thanks">*** salamat — come again ***</div>
    </SharedReceipt>
</template>

<style scoped>
.merchant {
    font-size: var(--text-body);
    text-align: center;
    font-weight: 600;
    letter-spacing: 3px;
}

.tsub {
    font-family: var(--mono);
    font-size: var(--text-micro);
    text-align: center;
    color: var(--faded);
    margin-top: var(--space-xs);
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