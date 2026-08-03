<script setup lang="ts">
const props = withDefaults(defineProps<{
    tornPosition: 'top' | 'bottom'
    merchant?: string
    subText: string
}>(), {
    merchant: 'Pesonal'
})


/** Computed */
const is_bottom = computed(() => props.tornPosition === 'bottom')
const clip_path = computed(() => createReceiptClip())


/** Functions */
function createReceiptClip(
  step = 3.5,
  depth = 55,
): string {
  const y = is_bottom.value ? 100 : 0
  const notch = is_bottom.value ? y - depth : y + depth

  const points = is_bottom.value
    ? ['0% 0%', '100% 0%', `100% ${notch}%`]
    : ['0% 0%', '0% 100%', '100% 100%', `100% ${notch}%`]

  for (let x = 100 - step; x >= 0; x -= step * 2) {
    points.push(`${x}% ${y}%`)

    if (x - step >= 0) {
      points.push(`${x - step}% ${notch}%`)
    }
  }

  points.push(`0% ${y}%`)

  return `polygon(${points.join(', ')})`
}
</script>

<template>
    <div class="receipt-container">
        <div class="torn-top" v-if="!is_bottom" :style="{ clipPath: clip_path }"></div>
        <div class="roll">
            <div class="header">
                <div class="merchant">{{ merchant }}</div>
                <div class="tsub">{{ subText }}</div>
            </div>
            <slot />
        </div>
        <div class="torn-bottom" v-if="is_bottom" :style="{ clipPath: clip_path }"></div>
    </div>
</template>

<style scoped>
.header {
    text-transform: uppercase;
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
    letter-spacing: .16em;
}

.roll {
    font-family: var(--mono);
    background-color: var(--paper);
    padding: var(--space-xl) var(--space-xl) var(--space-m);
}

.torn-top, .torn-bottom {
    height: 12px;
    background-color: var(--paper);
}

.torn-top {
    border-bottom: 1px solid var(--perforation);
}

.torn-bottom {
    border-top: 1px solid var(--perforation);
}
</style>