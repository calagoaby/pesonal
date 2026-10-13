<template>
    <div class="foot">
        <SharedBarcode class="roll-barcode" gap="5px" height="36px" />
        <div class="quip">* keep this receipt for your records *</div>
    </div>
</template>

<style scoped>
.foot {
    margin-top: var(--space-xl);
    text-align: center;
}

.roll-barcode {
    margin: var(--space-s) auto;
}

.quip {
    margin-top: var(--space-s);
    font-family: var(--mono);
    font-size: 9px;
    letter-spacing: .18em;
    text-transform: uppercase;
    color: var(--perforation);
}
</style>