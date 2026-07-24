export function useRecentItems() {
    /** States */
    const items = [
        {
            text: 'Lunch with team',
            category: 'Food',
            category_color: '#C9692E',
            date_created: 'Jun 17',
            amount: 680,
        },
        {
            text: 'Grab to office',
            category: 'Transport',
            category_color: '#3F6F97',
            date_created: 'Jun 17',
            amount: 240,
        },
        {
            text: 'SM Groceries',
            category: 'Groceries ',
            category_color: '#5E7A3E',
            date_created: 'Jun 16',
            amount: 2150,
        },
    ]

    return {
        items
    }
}