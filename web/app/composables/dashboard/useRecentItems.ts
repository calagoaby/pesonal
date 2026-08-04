import type { Ref } from "vue";
import { listExpenses } from "~/repositories/expenses";

/** The newest few rows of the month — the expenses list, not the stats call. */
export function useRecentItems(month: Ref<string>) {
    /** States */
    const { data, status, error } = useAsyncData(
        'recent-items',
        () => listExpenses({ month: month.value, per_page: 3 }),
        { watch: [month] },
    )


    /** Computed */
    const items = computed(() => data.value?.items ?? [])


    return {
        items,
        status,
        error
    }
}