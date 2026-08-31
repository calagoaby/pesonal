<template>
    <header class="header">
        <div>
            <p class="greeting">Kumusta, User</p>
            <h1 class="title">Your money this month</h1>
        </div>
        <div class="avatar">A</div>
    </header>
</template>

<style scoped>
.header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.greeting {
    margin: 0;
    font-size: var(--text-label);
    color: var(--faded);
}

.title {
    margin: var(--space-xs) 0 0;
    font-size: var(--text-title);
    font-weight: 700;
}

.avatar {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    border: 1.5px solid var(--ink);
    border-radius: var(--radius-pill);
    background-color: var(--paper);
    color: var(--ink);
    font-family: var(--mono);
    font-weight: 600;
    font-size: 16px;
}
</style>