<script setup lang="ts">
import Home from '@primeicons/vue/home';
import Receipt from '@primeicons/vue/receipt';
import Plus from '@primeicons/vue/plus';
import Sync from '@primeicons/vue/sync';
import EllipsisH from '@primeicons/vue/ellipsis-h';
</script>

<template>
    <div class="navbar">
        <div class="nav-item active">
            <Home />
            Home
        </div>
        <div class="nav-item">
            <Receipt />
            Expenses
        </div>
        <div class="nav-item">
            <div class="add-icon">
                <Plus />
            </div>
        </div>
        <div class="nav-item">
            <Sync />
            Recurring
        </div>
        <div class="nav-item">
            <EllipsisH />
            More
        </div>
    </div>
</template>

<style scoped>
.navbar {
    background-color: var(--paper);
    border-top: 1.5px dashed var(--perforation);
    padding: var(--space-m) var(--space-l) var(--space-l);
    display: flex;
    align-items: center;
    justify-content: space-between;

    .active {
        color: var(--ink)
    }
}

.nav-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: var(--space-xs);
    font-size: var(--text-micro);
    color: var(--faded);
    font-weight: 500;
    flex: 1;
}

.nav-item :deep(.p-icon) {
    width: 20px;
    height: 20px;
}

.add-icon {
    color: var(--paper);
    background-color: var(--stamp);
    border-radius: var(--radius-pill);
    width: 54px;
    height: 54px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 3px solid var(--paper);
    box-shadow: 0 8px 18px rgba(200, 71, 46, .32);
    margin-top: -26px;
}

.add-icon :deep(.p-icon) {
    width: 32px;
    height: 32px;
}
</style>