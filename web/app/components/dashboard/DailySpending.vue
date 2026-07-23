<script setup lang="ts">
import { useDailySpending } from '~/composables/dashboard/useDailySpending';

const {
    spending_percentages
} = useDailySpending()
</script>

<template>
    <div class="card">
        <div class="header-row">
            <span class="shdr">DAILY DAMAGE</span>
            <span class="hint">biggest: Jun 1</span>
        </div>
        <div class="bars">
            <div v-for="(daily, index) in spending_percentages" 
                :key="index" 
                :style="{
                    flex: 1,
                    maxWidth: '20px',
                    height: `${daily}px`, 
                    backgroundColor: `${daily === 100 ? 'var(--stamp)' : 'var(--perforation)'}`,
                    borderRadius: '2px',
                }">
            </div>
        </div>
        <div class="number_labels">
            <div v-for="index in spending_percentages.length" 
                :key="index" 
                :style="{
                    flex: 1,
                    maxWidth: '20px',
                    textAlign: 'center',
                    fontVariantNumeric: 'tabular-nums',
                }">
                {{ (index === 1 || index % 5 === 0) ? index : '' }}
            </div>
        </div>
    </div>
</template>

<style scoped>
.card {
    background-color: var(--paper);
    border-radius: var(--radius-card);
    padding: var(--space-l);
}

.header-row {
    display: flex;
    justify-content: space-between;
    color: var(--faded);
    margin-bottom: var(--space-m);

    .shdr {
        font-family: var(--mono);
        font-size: var(--text-label);
        letter-spacing: .08em;
    }

    .hint {
        font-size: var(--text-micro);
    }
}

.bars {
    display: flex;
    justify-content: center;
    gap: 5px;
    align-items: flex-end;
    height: 100px;
    padding: 0 var(--space-xs);
    border-bottom: 1.5px dashed var(--perforation);
}

.number_labels {
    display: flex;
    justify-content: center;
    gap: 5px;
    padding: 0 var(--space-xs);
    font-style: var(--mono);
    font-size: var(--text-micro);
    color: var(--faded);
}
</style>