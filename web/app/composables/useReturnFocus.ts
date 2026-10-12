import type { Ref, ShallowRef } from 'vue'

// A closed overlay is inert, so focus left inside it strands a keyboard user: the next Tab
// jumps to whatever follows the overlay in the DOM. Opening moves focus in; closing hands it
// back to whatever opened it. flush 'post' so inert is already applied when this runs.
export function useReturnFocus(is_open: Ref<boolean>, panel: Readonly<ShallowRef<HTMLElement | null>>) {
    /** States */
    let opener: HTMLElement | null = null


    /** Functions */
    function isStranded() {
        const active = document.activeElement
        return !active || active === document.body || !!panel.value?.contains(active)
    }

    function canTakeFocus(element: HTMLElement | null): element is HTMLElement {
        return !!element?.isConnected && !element.closest('[inert]')
    }


    watch(is_open, (open) => {
        if (open) {
            opener = document.activeElement as HTMLElement | null
            panel.value?.focus({ preventScroll: true })
            return
        }

        if (isStranded()) {
            if (canTakeFocus(opener)) {
                opener.focus({ preventScroll: true })
            } else {
                (document.activeElement as HTMLElement | null)?.blur()
            }
        }
        opener = null
    }, { flush: 'post' })
}