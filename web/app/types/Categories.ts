/** View model — a donut legend row. The contract shape lives in #shared/types/Category. */
export interface CategoryRow {
    name: string
    color: string
    amount: number
    percentage: number
}