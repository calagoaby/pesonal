export function formatAmount(amount: number) {
    return (amount / 100).toFixed(2).toLocaleString()
}

export function formatAmountWithCurrency(amount: number) {
    return '₱' + formatAmount(amount)
}