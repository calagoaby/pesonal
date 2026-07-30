<script setup lang="ts">
const props = defineProps<{
    tornPosition: 'top' | 'bottom'
}>()


/** States */
const clip_path = ref<string>('')


/** Computed */
const is_bottom = computed(() => props.tornPosition === 'bottom')


/** Functions */
function createReceiptClip(
  step = 5,
  depth = 5,
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


onMounted(() => {
    clip_path.value = createReceiptClip(3.5, 55)
})
</script>

<template>
    <div class="receipt-container">
        <div class="torn-top" v-if="!is_bottom" :style="{ clipPath: clip_path }"></div>
        <div class="receipt">
            <slot />
        </div>
        <div class="torn-bottom" v-if="is_bottom" :style="{ clipPath: clip_path }"></div>
    </div>
</template>

<style scoped>
.receipt-container {
    margin: var(--space-l) 0 var(--space-2xl)
}

.receipt {
    font-family: var(--mono);
    background-color: var(--paper);
    padding: var(--space-xl) var(--space-xl) var(--space-m);
    border-top: 1px solid var(--desk);
    border-bottom: 1px solid var(--desk);
}

.torn-top, .torn-bottom {
    height: 12px;
    background-color: var(--paper);
}
</style>