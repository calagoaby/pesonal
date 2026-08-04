import { listCategories } from "~/repositories/categories";

/** App-wide, not feature-scoped: the picker, the filter and the category screen all read this. */
export function useCategories() {
    /** States */
    const { data, status, error } = useAsyncData(
        'categories',
        () => listCategories(),
    )


    /** Computed */
    const categories = computed(() => data.value ?? [])


    return {
        categories,
        status,
        error
    }
}