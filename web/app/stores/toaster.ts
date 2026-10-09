export interface ToastAction {
    label: string
    run: () => void
}

// The app's single transient toast. It's a store, not per-page state, because it
// outlives navigation: a void on the edit screen shows its undo back on the roll.
export const useToaster = defineStore('toaster', () => {
    /** States */
    const visible = ref(false)
    const message = ref('')
    const action = ref<ToastAction | null>(null)

    // Per-store instance, so it can't leak across requests on the server. show()
    // only ever runs from a client handler, never during SSR.
    let timer: ReturnType<typeof setTimeout> | undefined


    /** Functions */
    function show(next: { message: string, action?: ToastAction, duration?: number }) {
        message.value = next.message
        action.value = next.action ?? null
        visible.value = true

        clearTimeout(timer)
        timer = setTimeout(dismiss, next.duration ?? 10000)
    }

    function runAction() {
        action.value?.run()
        dismiss()
    }

    function dismiss() {
        clearTimeout(timer)
        visible.value = false
    }


    return {
        visible,
        message,
        action,

        show,
        runAction,
        dismiss
    }
})