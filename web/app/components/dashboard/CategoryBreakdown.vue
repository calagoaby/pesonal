<script setup lang="ts">
const chart_data = ref()
const chart_options = ref()


/** Functions */
function setChartData() {
    const document_style = getComputedStyle(document.body)

    return {
        datasets: [
            {
                data: [35, 20, 15, 10, 18],
                backgroundColor: ['#534F8C', '#C9692E', '#5E7A3E', '#3F6F97', '#8A857B']
            }
        ]
    }
}

function setChartOptions() {
    const document_style = getComputedStyle(document.documentElement)

    const textColor = '#C9692E'
    return {
        plugins: {
            legend: {
                labels: {
                    cutout: '60%',
                    color: textColor
                }
            }
        }
    }
}


onMounted(() => {
    chart_data.value = setChartData()
    chart_options.value = setChartOptions()
})
</script>

<template>
    <div class="category-breakdown">
        <div class="header-row">
            <span class="where">WHERE IT WENT</span>
            <span class="hint">tap a row to filter</span>
        </div>
        <div class="body-row">
            <div class="svg">
                <Chart type="doughnut" :data="chart_data" :options="chart_options"/>
                <div class="inside-text">
                    <div class="total">₱43.2k</div>
                    <div class="text">total</div>
                </div>
            </div>
            <div class="legend">
                <div>
                    <div class="leg-row">
                        <div class="leg-name">
                            <span class="dot" style="background-color: var(--c-housing);"></span>
                            <span>Housing</span>
                        </div>
                        <div class="numbers">
                            <span class="amount">₱15,000 </span>
                            <span class="percent">35%</span>
                        </div>
                    </div>
                    <div class="seg">
                        <div class="fill" style="width: 35%; background-color: var(--c-housing);"></div>
                    </div>
                </div>
                <div>
                    <div class="leg-row">
                        <div class="leg-name">
                            <span class="dot" style="background-color: var(--c-food);"></span>
                            <span>Food</span>
                        </div>
                        <div class="numbers">
                            <span class="amount">₱5,000 </span>
                            <span class="percent">20%</span>
                        </div>
                    </div>
                    <div class="seg">
                        <div class="fill" style="width: 20%; background-color: var(--c-food);"></div>
                    </div>
                </div>
                <div>
                    <div class="leg-row">
                        <div class="leg-name">
                            <span class="dot" style="background-color: var(--c-groc);"></span>
                            <span>Groceries</span>
                        </div>
                        <div class="numbers">
                            <span class="amount">₱6,400 </span>
                            <span class="percent">15%</span>
                        </div>
                    </div>
                    <div class="seg">
                        <div class="fill" style="width: 15%; background-color: var(--c-groc);"></div>
                    </div>
                </div>
                <div>
                    <div class="leg-row">
                        <div class="leg-name">
                            <span class="dot" style="background-color: var(--c-transport);"></span>
                            <span>Transport</span>
                        </div>
                        <div class="numbers">
                            <span class="amount">₱4,200 </span>
                            <span class="percent">10%</span>
                        </div>
                    </div>
                    <div class="seg">
                        <div class="fill" style="width: 10%; background-color: var(--c-transport);"></div>
                    </div>
                </div>
                <div>
                    <div class="leg-row">
                        <div class="leg-name">
                            <span class="dot" style="background-color: var(--c-other);"></span>
                            <span>Others</span>
                        </div>
                        <div class="numbers">
                            <span class="amount">₱7,810 </span>
                            <span class="percent">18%</span>
                        </div>
                    </div>
                    <div class="seg">
                        <div class="fill" style="width: 18%; background-color: var(--c-other);"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.category-breakdown {
    background-color: var(--paper);
    border-radius: var(--radius-card);
    padding: var(--space-l);
}

.header-row {
    display: flex;
    justify-content: space-between;
    color: var(--faded);
    margin-bottom: var(--space-m);
}

.where {
    font-family: var(--mono);
    font-size: var(--text-label);
    letter-spacing: .08em;
}

.hint {
    font-size: var(--text-micro);
}

.body-row {
    display: flex;
    align-items: center;
    gap: var(--space-l);
}

.svg {
    width: 116px;
    height: 116px;
    position: relative;
}

.inside-text {
    position: absolute;
    top: 50%;
    left: 50%;
    transform:translate(-50%,-50%);
    font-family: var(--mono);

    .total {
        font-size: 16px;
        font-weight: 600;
    }

    .text {
        font-size: 10px;
        font-size: var(--text-label);
        text-align: center;
        color: var(--faded);
    }
}

.legend {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: var(--space-m);
}

.leg-row {
    display: flex;
    justify-content: space-between;
}

.leg-name {
    display: flex;
    font-size: var(--text-body);
    gap: var(--space-s);
    align-items: center;

    .dot {
        width: 10px;
        height: 10px;
        border-radius: 3px;
    }
}

.numbers {
    font-family: var(--mono);

    .amount {
        font-size: var(--text-body);
        font-variant-numeric: tabular-nums;
        font-weight: 500;
    }

    .percent {
        font-size: var(--text-micro);
        color: var(--faded);
    }
}

.seg {
    height: 5px;
    border-radius: var(--radius-pill);
    background-color: var(--desk);

    .fill {
        height: 100%;
        border-radius: var(--radius-pill);
    }
}
</style>