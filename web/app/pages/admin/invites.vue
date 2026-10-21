<script setup lang="ts">
import type { Invitation } from '#shared/types/Invitation';
import ChevronLeft from '@primeicons/vue/chevron-left';
import ExclamationTriangle from '@primeicons/vue/exclamation-triangle';
import { listInvitations, removeInvitation } from '~/repositories/admin';
import { useToaster } from '~/stores/toaster';

definePageMeta({ layout: 'drill', middleware: 'admin' })

// A 404 here means is_admin went stale (the owner dropped this email from ADMIN_EMAILS):
// it lands on the error notice below, never back in the guard.
const { data: invitations, error, refresh } = await useAsyncData('admin-invitations', () => listInvitations())

const toaster = useToaster()


/** States */
const is_remove_slip_open = ref(false)
const is_removing = ref(false)
const removing = ref<Invitation | null>(null)

/** Computed */
const total = computed(() => invitations.value?.length ?? 0)
const with_ledger = computed(() => (invitations.value ?? []).filter(invitation => invitation.has_account).length)
const count_label = computed(() => total.value
    ? `${total.value} invited · ${with_ledger.value} with a ledger`
    : '0 invited')


/** Functions */
async function onInvited({ invitation, created }: { invitation: Invitation, created: boolean }) {
    await refresh()
    toaster.show({ message: created ? `Invited ${invitation.email}.` : 'Already on the list.' })
}

function requestRemove(invitation: Invitation) {
    removing.value = invitation
    is_remove_slip_open.value = true
}

async function confirmRemove() {
    if (!removing.value || is_removing.value) {
        return
    }

    is_removing.value = true

    try {
        await removeInvitation(removing.value.id)
        is_remove_slip_open.value = false
        await refresh()
    } catch (error) {
        is_remove_slip_open.value = false

        // A 404 means it was already gone, so the refresh is the whole answer.
        if (error instanceof ApiRequestError && error.status === 404) {
            await refresh()
        } else if (!(error instanceof ApiRequestError && error.status === 401)) {
            toaster.show({ message: "Couldn't remove the invite. It's still on the list." })
        }
    } finally {
        is_removing.value = false
    }
}
</script>

<template>
    <NuxtLink to="/more" class="back-bar">
        <ChevronLeft />
        More
    </NuxtLink>

    <SharedReceipt class="invite-roll" torn-position="top" sub-text="· who gets a ledger ·">
        <SharedPerf />

        <h1 class="title">Invites</h1>
        <p class="lede">Only these emails can open a ledger.</p>

        <SharedNotice v-if="error" title="Printer jammed" tone="alert">
            <template #mark>
                <ExclamationTriangle />
            </template>
            Couldn't fetch the guest list.<br>
            Nobody was added or removed — this screen just came up blank.
            <template #action>
                <button class="act" @click="refresh()">Try again</button>
            </template>
        </SharedNotice>

        <template v-else>
            <AdminInviteForm @invited="onInvited" />

            <div v-if="total" class="invites">
                <AdminRow
                    v-for="invitation in invitations"
                    :key="invitation.id"
                    :invitation="invitation"
                    @remove="requestRemove(invitation)"
                />
            </div>
            <div v-else class="empty">Nobody is invited yet.</div>
        </template>

        <div class="foot">
            <SharedBarcode class="roll-barcode" gap="5px" height="36px" />
            <div v-if="!error" class="count">{{ count_label }}</div>
            <div class="quip">* the guest list is short on purpose *</div>
        </div>
    </SharedReceipt>

    <SharedVoidSlip
        v-model:open="is_remove_slip_open"
        title="Remove invite"
        :subject="removing?.email ?? ''"
        warning="An account they already opened keeps working."
        confirm-label="Remove"
        @confirm="confirmRemove"
    />
</template>

<style scoped>
.back-bar {
    display: flex;
    align-items: center;
    gap: var(--space-s);
    padding: 0 var(--space-xs) var(--space-s);
    font-family: var(--mono);
    font-size: var(--text-label);
    letter-spacing: .14em;
    text-transform: uppercase;
    color: var(--faded);
    text-decoration: none;
}

.back-bar :deep(.p-icon) {
    width: 12px;
    height: 12px;
    color: var(--ink);
}

.invite-roll {
    margin-bottom: var(--space-l);
}

.title {
    font-family: var(--mono);
    font-size: var(--text-title);
    font-weight: 600;
    letter-spacing: .04em;
    text-transform: uppercase;
    margin: var(--space-l) 0 var(--space-xs);
}

.lede {
    font-family: var(--sans);
    font-size: var(--text-body);
    color: var(--faded);
    line-height: 1.5;
    margin: 0 0 var(--space-l);
}

.invites {
    margin-top: var(--space-l);
}

.empty {
    text-align: center;
    padding: var(--space-2xl) 0 var(--space-l);
    border-top: 1.5px dashed var(--perforation);
    margin-top: var(--space-l);
    font-family: var(--sans);
    font-size: var(--text-body);
    color: var(--faded);
}

.act {
    display: inline-block;
    background-color: var(--stamp);
    color: var(--paper);
    border: 0;
    border-radius: var(--radius-pill);
    padding: var(--space-s) var(--space-l);
    font-family: var(--mono);
    font-size: var(--text-label);
    font-weight: 600;
    letter-spacing: .12em;
    text-transform: uppercase;
    cursor: pointer;
}

.foot {
    text-align: center;
    margin-top: var(--space-xl);
}

.roll-barcode {
    margin: var(--space-s) auto;
}

.count {
    font-family: var(--mono);
    font-size: var(--text-micro);
    letter-spacing: .14em;
    text-transform: uppercase;
    color: var(--faded);
}

.quip {
    font-family: var(--mono);
    font-size: 9px;
    letter-spacing: .18em;
    text-transform: uppercase;
    color: var(--perforation);
    margin-top: var(--space-s);
}
</style>