<script setup lang="ts">
/** Types */
type BarcodeStyle = {
    height: string,
    width: string,
    background: string,
}


/** States */
const clip_path = createReceiptClip(2.5, 3)
const widths = [1, 1, 3, 3, 1, 1, 1, 3, 2, 2, 1, 2, 2, 1, 1, 1, 1, 3, 2, 1, 3, 1]
const barcode_styles = Array.from(
    { length: 22 }, (_, i) => barcodeStyle({index: i})
)


/** Functions */
function barcodeStyle ( { index }: { index: number }): BarcodeStyle {
  const width = widths[index] ?? 0

  const background = `linear-gradient(
    90deg,
    var(--ink) 0 ${width}px
  )`

  return {
    height: 'inherit',
    width: `${width}px`,
    background: background
  }
}

function createReceiptClip(step = 5, depth = 5): string {
    const points = [
        '0% 0%',
        '100% 0%',

        `100% ${100 - depth}%`,
    ]

    let x = 100 - step

    while(x >= 0) {
        points.push(`${x}% 100%`)
        x -= step

        if (x >= 0) {
            points.push(`${x}% ${100 - depth}%`)
            x -= step
        }
    }

    points.push('0% 100%')

    return `polygon(${points.join(', ')})`
}
</script>

<template>
    <div class="receipt" :style="{ clipPath: clip_path }">
        <div class="merchant">PESONAL</div>
        <div class="tsub">OFFICIAL RECEIPT — JUNE 2026</div>
        <div class="perf"></div>
        <div class="tlabel">TOTAL DAMAGE</div>
        <div class="total">₱43,210</div>
        <div style="text-align: center; margin-top: var(--space-m);">
            <span class="stamp"> 
                <i class="pi pi-arrow-down-right" style="font-size: var(--text-micro);"></i>
                 12% VS MAY
            </span>
        </div>
        <div class="note">Petsa de peligro? Hindi today.</div>
        <div class="perf"></div>
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
        <div class="barcode">
            <div 
                v-for="(style, index) in barcode_styles"
                :key="index" 
                :style="style"
            ></div>
        </div>
        <div class="ono">NO. 0617-2026</div>
        <div class="thanks">*** salamat — come again ***</div>
    </div>
</template>

<style scoped>

.receipt {
    font-family: var(--mono);
    background-color: var(--paper);
    margin: var(--space-l) 0 var(--space-2xl);
    padding: var(--space-xl) var(--space-xl) var(--space-2xl);
}

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

.perf {
    border-top: 1.5px dashed var(--perforation);
    margin: var(--space-m) 0;
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

.barcode {
    margin-top: var(--space-l);
    height: 30px;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 1.5px;
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