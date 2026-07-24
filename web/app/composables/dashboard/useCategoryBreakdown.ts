import type { CategoryRow } from "~/types/Categories"

export function useCategoryBreakdown() {
    /** States */
    const chart_data = ref()
    const chart_options = ref()
    const categories = [
        {
            name: 'Housing',
            color: '#534F8C',
            amount: 1500000,
        },
        {
            name: 'Food',
            color: '#C9692E',
            amount: 980000,
        },
        {
            name: 'Groceries',
            color: '#5E7A3E',
            amount: 640000,
        },
        {
            name: 'Transport',
            color: '#3F6F97',
            amount: 420000,
        },
        {
            name: 'Others',
            color: '#8A857B',
            amount: 781000,
        }
    ]
    const category_rows = ref<CategoryRow[]>([])


    /** Functions */
    function buildChartData() {
        const percentages = category_rows.value.map(category => category.percentage)
        const colors =  category_rows.value.map(category => category.color)

        return {
            datasets: [
                {
                    data: percentages,
                    backgroundColor: colors
                }
            ]
        }
    }

    function buildChartOptions() {
        return {
            cutout: '65%'
        }
    }

    function calculateCategoriesPercentages() {
        let total = categories.reduce((sum, category) => { 
            return sum + category.amount 
        }, 0)

        category_rows.value = categories.map(category => ({
            ...category,
            percentage: Math.round((category.amount / total) * 100)
        }))
    }

    function populateChart() {
        calculateCategoriesPercentages()
        chart_data.value = buildChartData()
        chart_options.value = buildChartOptions()
    }


    populateChart()


    return {
        chart_data,
        chart_options,
        category_rows
    }
}