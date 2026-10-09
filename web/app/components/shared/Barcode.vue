<script setup lang="ts">
const props = withDefaults(defineProps<{
    length?: number
    gap?: string
    height?: string
}>(), {
    gap: '1.5px',
    height: '30px',
})


/** Types */
type BarcodeStyle = {
    height: string,
    width: string,
    background: string,
}


/** States */
const widths = [1, 1, 3, 3, 1, 1, 1, 3, 2, 2, 1, 2, 2, 1, 1, 1, 1, 3, 2, 1, 3, 1, 3, 2, 1, 2, 1, 3, 2, 2, 1, 1, 3, 1, 2, 1, 2, 3, 2]
const barcode_styles = Array.from(
    { length: props.length ?? widths.length }, (_, i) => barcodeStyle({index: i})
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
</script>

<template>
    <div class="barcode" :style="{ gap: gap, height: height }">
        <div 
            v-for="(style, index) in barcode_styles"
            :key="index" 
            :style="style"
        />
    </div>
</template>

<style scoped>
.barcode {
    display: flex;
    justify-content: center;
    align-items: center;
}
</style>