<script setup lang="ts">
const { public: { apiBase } } = useRuntimeConfig()
</script>

<template>
    <a class="social" :href="`${apiBase}/api/auth/google/redirect`">
        <span class="mark">G</span>Continue with Google
    </a>
    <a class="social" :href="`${apiBase}/api/auth/facebook/redirect`">
        <span class="mark">f</span>Continue with Facebook
    </a>
    <div class="or">or</div>
</template>

<style scoped>
.social {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--space-s);
    margin-bottom: var(--space-s);
    padding: var(--space-m) var(--space-l);
    border: 1.5px solid var(--ink);
    border-radius: var(--radius-pill);
    background: var(--paper);
    font-family: var(--mono);
    font-size: var(--text-label);
    font-weight: 600;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: var(--ink);
    text-decoration: none;
}

.mark {
    font-family: var(--sans);
    font-size: var(--text-body);
    font-weight: 700;
    letter-spacing: 0;
}

.or {
    display: flex;
    align-items: center;
    gap: var(--space-m);
    margin: var(--space-l) 0 var(--space-s);
    font-size: var(--text-micro);
    letter-spacing: .16em;
    text-transform: uppercase;
    color: var(--faded);

    &::before,
    &::after {
        content: "";
        flex: 1;
        border-top: 1.5px dashed var(--perforation);
    }
}
</style>