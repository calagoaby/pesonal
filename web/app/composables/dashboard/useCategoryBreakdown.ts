export function useCategoryBreakdown() {
    /** Types */
    interface UpdatedCategory {
        name: string,
        color: string,
        amount: number
        percentage: number
    }


    /** States */
    const chart_data = ref()
    const categories = ([
        {
            name: 'Housing',
            color: '#534F8C',
            amount: 15000,
        },
        {
            name: 'Food',
            color: '#C9692E',
            amount: 9800,
        },
        {
            name: 'Groceries',
            color: '#5E7A3E',
            amount: 6400,
        },
        {
            name: 'Transport',
            color: '#3F6F97',
            amount: 4200,
        },
        {
            name: 'Others',
            color: '#8A857B',
            amount: 7810,
        }
    ])
    const updated_categories = ref<UpdatedCategory[]>([])


    /** Functions */
    function buildChartData() {
        const percentages = updated_categories.value.map(category => category.percentage)
        const colors =  updated_categories.value.map(category => category.color)

        return {
            datasets: [
                {
                    data: percentages,
                    backgroundColor: colors
                }
            ]
        }
    }

    function calculateCategoriesPercentages() {
        let total = categories.reduce((sum, category) => { 
            return sum + category.amount 
        }, 0)

        updated_categories.value = categories.map(category => ({
            ...category,
            percentage: (category.amount / total) * 100
        }))
    }

    function populateChart() {
        calculateCategoriesPercentages()
        chart_data.value = buildChartData()
    }


    onMounted(() => {
        populateChart()
    })


    return {
        chart_data,
        updated_categories
    }
}