import type { Ref } from 'vue'

export interface SubmitState {
    is_saving: Ref<boolean>
    errors: Ref<Record<string, string[]>>
    failure: Ref<string>
}

// The one write dance add, edit and the category form share: lock, clear the last
// attempt, run the write, then sort an ApiRequestError into field errors (422) or
// a single failure line (everything else). Returns whether the write went through.
export async function submitRequest(write: () => Promise<unknown>, state: SubmitState): Promise<boolean> {
    if (state.is_saving.value) {
        return false
    }

    state.is_saving.value = true
    state.errors.value = {}
    state.failure.value = ''

    try {
        await write()

        return true
    } catch (error) {
        const request_error = error as ApiRequestError

        state.errors.value = request_error.errors ?? {}
        state.failure.value = request_error.errors ? '' : request_error.message

        return false
    } finally {
        state.is_saving.value = false
    }
}