<script setup lang="ts">
const is_open = defineModel<boolean>('open', { required: true })

const props = withDefaults(defineProps<{
    title: string
    subject: string
    /** 'confirm' asks to delete; 'blocked' explains why a delete was refused. */
    variant?: 'confirm' | 'blocked'
    /** centavos — shown on the subject line in confirm mode when given. */
    amount?: number
    /** confirm: the muted line under the subject, e.g. "Wed Jun 17 · Food". */
    detail?: string
    /** blocked: why the delete was refused (the 409 message). */
    message?: string
    /** confirm: the consequence line under the detail. */
    warning?: string
    confirmLabel?: string
}>(), {
    variant: 'confirm',
    warning: 'This one does not come back.',
    confirmLabel: 'Void it',
})

const emit = defineEmits<{
    confirm: []
}>()


/** Computed */
const is_confirm = computed(() => props.variant === 'confirm')


/** Functions */
function close() {
    is_open.value = false
}

function handleKeydown(event: KeyboardEvent) {
    if (is_open.value && event.key === 'Escape') {
        close()
    }
}


// Modal, so escape should reach it wherever focus is. onMounted keeps it client-only.
onMounted(() => window.addEventListener('keydown', handleKeydown))
onBeforeUnmount(() => window.removeEventListener('keydown', handleKeydown))
</script>

<template>
    <div class="scrim" :class="{ shown: is_open }" @click="close" />
    <div class="slip-wrap" :class="{ shown: is_open }">
        <div class="slip" role="alertdialog" :aria-label="title">
            <div v-if="is_confirm" class="stamp">Void</div>
            <div class="slip-title">{{ title }}</div>
            <div class="rule" />
            <div class="subject">
                <span class="desc">{{ subject }}</span>
                <span class="lead" />
                <span v-if="amount !== undefined">{{ formatAmountWithCurrency(amount) }}</span>
            </div>
            <div class="warn">
                <template v-if="is_confirm">
                    <template v-if="detail">{{ detail }}<br></template>
                    {{ warning }}
                </template>
                <template v-else>{{ message }}</template>
            </div>
            <div class="actions">
                <template v-if="is_confirm">
                    <button class="btn keep" @click="close">Keep</button>
                    <button class="btn danger" @click="emit('confirm')">{{ confirmLabel }}</button>
                </template>
                <button v-else class="btn calm" @click="close">Got it</button>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Above the sheet (z 101), so a delete confirm can layer over the open editor. */
.scrim {
    position: fixed;
    inset: 0;
    z-index: 110;
    background-color: rgb(28 26 23 / 45%);
    opacity: 0;
    pointer-events: none;
    transition: opacity .25s;
}

.scrim.shown {
    opacity: 1;
    pointer-events: auto;
}

/* Centered, not a bottom sheet: the slip interrupts rather than offers a menu. */
.slip-wrap {
    position: fixed;
    inset: 0;
    /* Centred on the app's column on a wider screen (main.css), not the whole window. */
    max-width: var(--column-max, none);
    margin-inline: auto;
    z-index: 111;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: var(--space-xl);
    pointer-events: none;
}

.slip {
    position: relative;
    width: 100%;
    background-color: var(--paper);
    border-radius: var(--radius-card);
    padding: var(--space-l);
    opacity: 0;
    transform: scale(.96);
    pointer-events: none;
    transition: opacity .2s, transform .2s;
}

.slip-wrap.shown .slip {
    opacity: 1;
    transform: scale(1);
    pointer-events: auto;
}

.stamp {
    position: absolute;
    top: var(--space-l);
    right: var(--space-l);
    border: 2px solid var(--stamp);
    border-radius: var(--radius-card);
    color: var(--stamp);
    font-family: var(--mono);
    font-size: var(--text-micro);
    font-weight: 600;
    letter-spacing: 2px;
    text-transform: uppercase;
    padding: 2px var(--space-s);
    transform: rotate(-12deg);
    opacity: .9;
}

.slip-title {
    font-family: var(--mono);
    font-size: var(--text-label);
    font-weight: 600;
    letter-spacing: .14em;
    text-transform: uppercase;
    text-align: center;
}

.rule {
    border-top: 1.5px dashed var(--perforation);
    margin: var(--space-m) 0;
}

.subject {
    display: flex;
    align-items: baseline;
    font-family: var(--mono);
    font-size: var(--text-body);
}

.desc {
    font-family: var(--sans);
}

.lead {
    flex: 1;
    border-bottom: 1.5px dotted var(--perforation);
    margin: 0 var(--space-s);
    transform: translateY(-3px);
}

.warn {
    font-size: var(--text-label);
    color: var(--faded);
    text-align: center;
    line-height: 1.5;
    margin-top: var(--space-m);
}

.actions {
    display: flex;
    gap: var(--space-s);
    margin-top: var(--space-l);
}

.btn {
    flex: 1;
    border: 0;
    border-radius: var(--radius-pill);
    padding: var(--space-m);
    font-family: var(--mono);
    font-size: var(--text-label);
    font-weight: 600;
    letter-spacing: .12em;
    text-transform: uppercase;
    cursor: pointer;
}

.btn.keep {
    background: transparent;
    color: var(--faded);
    border: 1px solid var(--perforation);
}

.btn.danger {
    background-color: var(--stamp);
    color: #fff;
}

.btn.calm {
    background-color: var(--ink);
    color: var(--paper);
}
</style>