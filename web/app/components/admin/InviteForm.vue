<script setup lang="ts">
import type { Invitation } from '#shared/types/Invitation';
import { inviteEmail } from '~/repositories/admin';
import { useToaster } from '~/stores/toaster';

const emit = defineEmits<{
    invited: [result: { invitation: Invitation, created: boolean }]
}>()

const toaster = useToaster()


/** States */
const email = ref('')
const is_saving = ref(false)
const errors = ref<Record<string, string[]>>({})
const failure = ref('')

/** Computed */
// The only rules are "required" and "is an email", so every 422 reads as the design's one line
// rather than Laravel's stock wording.
const field_error = computed(() => errors.value.email ? "That isn't an email address." : '')


/** Functions */
async function handleSubmit() {
    let result: { invitation: Invitation, created: boolean } | undefined
    const is_sent = await submitRequest(async () => {
        result = await inviteEmail(email.value)
    }, { is_saving, errors, failure })

    if (is_sent && result) {
        email.value = ''
        emit('invited', result)
    } else if (failure.value) {
        toaster.show({ message: "Couldn't send the invite. Nobody was added." })
    }
}
</script>

<template>
    <form class="invite-form" novalidate @submit.prevent="handleSubmit">
        <AuthField
            v-model="email"
            class="field"
            label="Email"
            type="email"
            autocomplete="off"
            placeholder="name@example.com"
            :error="field_error"
        />
        <button type="submit" class="invite" :disabled="is_saving">Invite</button>
    </form>
</template>

<style scoped>
.invite-form {
    display: flex;
    align-items: flex-start;
    gap: var(--space-s);
}

.invite-form .field {
    flex: 1;
    margin-top: 0;
}

.invite {
    flex: none;
    margin-top: var(--space-m);
    padding: var(--space-m) var(--space-l);
    border: 1.5px solid var(--stamp);
    border-radius: var(--radius-pill);
    background-color: var(--stamp);
    color: #fff;
    font-family: var(--mono);
    font-size: var(--text-label);
    font-weight: 600;
    letter-spacing: .08em;
    text-transform: uppercase;
    cursor: pointer;

    &:disabled {
        opacity: .6;
        cursor: default;
    }
}
</style>