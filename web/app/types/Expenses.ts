export interface Expenses {
    description: string
    category: string
    amount: number    
}

export interface DailyExpenses {
    date: string
    items: Expenses[]
}

export interface MonthlyExpenses {
    month: string
    days: DailyExpenses[]
}