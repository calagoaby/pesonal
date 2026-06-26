<template>
    <div class="screen">
        <header class="header">
            <div>
                <p class="greeting">Kumusta, Aby</p>
                <h1 class="title">Your money this month</h1>
            </div>
            <Avatar label="A" shape="circle" class="avatar" />
        </header>
    </div>
</template>

<style scoped>
.screen {
    /* 16px gutter via the design token, not a body margin. */
    padding: var(--space-m) var(--space-l) 0;
}

.header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.greeting {
    margin: 0;
    font-size: 12px;
    color: var(--faded);
}

.title {
    margin: var(--space-xs) 0 0;
    font-size: 18px;
    font-weight: 700;
}

.avatar {
    width: 38px;
    height: 38px;
    border: 1.5px solid var(--ink);
    background-color: var(--paper);
    color: var(--ink);
    font-family: "Spline Sans Mono", monospace;
    font-weight: 600;
    font-size: 16px;
}
</style>