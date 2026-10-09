<script setup lang="ts">
import Times from '@primeicons/vue/times';

const is_open = defineModel<boolean>('open', { required: true })

defineProps<{
    title: string
}>()


/** Functions */
function close() {
    is_open.value = false
}

function handleKeydown(event: KeyboardEvent) {
    if (is_open.value && event.key === 'Escape') {
        close()
    }
}


// Listener lives on the window rather than the panel: a sheet is modal, so escape
// should reach it wherever focus happens to be. onMounted keeps it off the server.
onMounted(() => window.addEventListener('keydown', handleKeydown))
onBeforeUnmount(() => window.removeEventListener('keydown', handleKeydown))
</script>

<template>
    <div class="scrim" :class="{ shown: is_open }" @click="close"/>
    <div class="sheet" :class="{ shown: is_open }" role="dialog" :aria-label="title">
        <div class="grab"/>
        <div class="head">
            <span>{{ title }}</span>
            <button aria-label="Close" @click="close">
                <Times />
            </button>
        </div>
        <slot />
    </div>
</template>

<style scoped>
/* Lifts the overlay above page content, the nav, and the FAB. Nothing else in
   the app sets z-index, so these two values are the whole stacking order — they
   only need to beat the default 0. The scrim then covers the nav so it can't be
   tapped through. */
.scrim {
    position: fixed;
    inset: 0;
    z-index: 100;
    background-color: rgb(28 26 23 / 45%);
    opacity: 0;
    pointer-events: none;
    transition: opacity .25s;
}

.scrim.shown {
    opacity: 1;
    pointer-events: auto;
}

.sheet {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    /* As wide as the app's column on a wider screen (main.css), full width on a phone. */
    max-width: var(--column-max, none);
    margin-inline: auto;
    z-index: 101;
    background-color: var(--paper);
    border-top: 2px solid var(--ink);
    border-radius: 18px 18px 0 0;
    padding: 0 var(--space-l) var(--space-l);
    /* Tall content (the category sheet) scrolls inside rather than pushing its
       header off the top of a short viewport. */
    max-height: 90dvh;
    overflow-y: auto;
    transform: translateY(110%);
    transition: transform .28s ease;
}

.sheet.shown {
    transform: translateY(0);
}

.grab {
    width: 40px;
    height: 4px;
    border-radius: var(--radius-pill);
    background-color: var(--perforation);
    margin: var(--space-m) auto var(--space-s);
}

.head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-family: var(--mono);
    font-size: var(--text-micro);
    letter-spacing: .1em;
    text-transform: uppercase;
    color: var(--faded);
    padding: 2px 2px var(--space-s);
    border-bottom: 1.5px dashed var(--perforation);

    button {
        display: flex;
        background: transparent;
        border: 0;
        color: var(--ink);
        cursor: pointer;
    }
}

.head :deep(.p-icon) {
    width: 18px;
    height: 18px;
}
</style>