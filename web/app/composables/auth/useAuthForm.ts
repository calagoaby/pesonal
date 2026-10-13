import { submitRequest } from '~/utils/submitRequest'

// The auth screens all submit the same way: lock the button, clear the last attempt,
// run the call, sort the failure into per-field messages (422) or one line (anything else).
export function useAuthForm() {
    /** States */
    const is_submitting = ref(false)
    const errors = ref<Record<string, string[]>>({})
    const failure = ref('')


    /** Functions */
    function submit(write: () => Promise<unknown>) {
        return submitRequest(write, { is_saving: is_submitting, errors, failure })
    }

    function fieldError(name: string) {
        return errors.value[name]?.[0]
    }


    return {
        is_submitting,
        errors,
        failure,

        submit,
        fieldError
    }
}