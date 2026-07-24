export function formatAmount(amount: number) {
    return (amount / 100).toLocaleString('en-PH', { minimumFractionDigits: 2 })
}

export function formatAmountWithCurrency(amount: number) {
    return '₱' + formatAmount(amount)
}