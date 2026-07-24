export function formatAmount(amount: number) {
    return amount.toLocaleString()
}

export function formatAmountWithCurrency(amount: number) {
    return '₱' + formatAmount(amount)
}