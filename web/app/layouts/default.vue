<template>
    <div class="content">
        <slot />
        <SharedNavBar />
    </div>
</template>

<style scoped>
.content {
    display: flex;
    flex-direction: column;
    height: 100dvh;
}
</style>