<script setup lang="ts">
import type { Invitation } from '#shared/types/Invitation';

defineProps<{
    invitation: Invitation
}>()

const emit = defineEmits<{
    remove: []
}>()

const { formatStampDay } = useDateFormatter()
</script>

<template>
    <div class="invite">
        <span class="who">
            {{ invitation.email }}
            <span class="when">invited {{ formatStampDay(invitation.invited_at) }}</span>
        </span>
        <span class="side">
            <span class="stamp" :class="{ has: invitation.has_account }">
                {{ invitation.has_account ? 'has a ledger' : 'not yet' }}
            </span>
            <button type="button" class="remove" @click="emit('remove')">Remove</button>
        </span>
    </div>
</template>

<style scoped>
.invite {
    display: flex;
    align-items: center;
    gap: var(--space-m);
    padding: var(--space-m) 2px;
    border-top: 1.5px dashed var(--perforation);
}

/* min-width 0 lets a long address wrap instead of squeezing the stamp column. */
.who {
    flex: 1;
    min-width: 0;
    font-family: var(--sans);
    font-size: var(--text-body);
    word-break: break-all;
}

.when {
    display: block;
    font-size: var(--text-label);
    color: var(--faded);
    margin-top: 2px;
}

.side {
    flex: none;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: var(--space-xs);
}

.stamp {
    font-family: var(--mono);
    font-size: 9px;
    letter-spacing: .1em;
    text-transform: uppercase;
    white-space: nowrap;
    border: 1px solid var(--perforation);
    border-radius: var(--radius-pill);
    padding: 2px var(--space-s);
    color: var(--faded);
}

.stamp.has {
    border-color: var(--under);
    color: var(--under);
}

.remove {
    padding: 0;
    border: 0;
    background: transparent;
    font-family: var(--sans);
    font-size: var(--text-label);
    color: var(--stamp);
    text-decoration: underline;
    text-underline-offset: 3px;
    white-space: nowrap;
    cursor: pointer;
}
</style>