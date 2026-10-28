import type { ExpenseItem } from '#shared/types/Expense'

// Shared by every form with an Items section (expenses, standing orders). A row is an
// item still being typed, so it can be blank or half-filled; the payload never is.

/** `key` is only for `v-for`: names repeat (two iced teas), so neither name nor index is stable. */
export interface ItemRow {
    key: number
    name: string
    /** centavos */
    amount: number
}

// Keys count from 0 per form, not from a module-level counter: the server render and the
// browser would each run their own counter and disagree.
export function toItemRows(items: ExpenseItem[]): ItemRow[] {
    return items.map((item, index) => ({ key: index, name: item.name, amount: item.amount }))
}

export function blankItemRow(rows: ItemRow[]): ItemRow {
    const key = rows.reduce((highest, row) => Math.max(highest, row.key), -1) + 1

    return { key, name: '', amount: 0 }
}

export function isBlankItem(row: ItemRow) {
    return row.name.trim() === '' && row.amount === 0
}

/** A name without a price or a price without a name — the server refuses either. */
export function isHalfItem(row: ItemRow) {
    return (row.name.trim() === '') !== (row.amount === 0)
}

// A blank row is a "+ Add item" tap that went nowhere: dropped, not refused.
export function itemsPayload(rows: ItemRow[]): ExpenseItem[] {
    return rows
        .filter(row => !isBlankItem(row))
        .map(row => ({ name: row.name.trim(), amount: row.amount }))
}

export function itemsTotal(rows: ItemRow[]) {
    return rows.reduce((sum, row) => sum + row.amount, 0)
}

export function hasHalfItem(rows: ItemRow[]) {
    return rows.some(isHalfItem)
}