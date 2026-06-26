<template>
    <div class="content">
        <div class="header">
            <div>
                <div class="hello">Kamusta, Aby</div>
                <div class="htitle">Your money this month</div>
            </div>
            <div>
                <Avatar label="A" class="mr-2 avatar" shape="circle" />
            </div>
        </div>
    </div>
</template>

<style>
.content {
    padding: 10px 16px 0;
    font-family: "Plus Jakarta Sans", sans-serif;
    font-optical-sizing: auto;
    font-weight: 300;
    font-style: normal;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;

    .hello {
        font-size: 12px;
        color: var(--faded)
    }

    .htitle {
        font-size: 18px;
        font-weight: 700;
        margin-top: 2px;
    }
}

.avatar {
    background-color: var(--paper);
    font-family: "Spline Sans Mono", monospace;
    font-optical-sizing: auto;
    font-weight: 600;
    font-style: normal;
    border: 1.5px solid black;
    font-size: 16px;
    width: 38px;
    height: 38px;
}
</style>