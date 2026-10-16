<script setup lang="ts">
import { useAuth } from '~/stores/auth'

const auth = useAuth()


/** States */
// Provider avatar links expire (Facebook's are signed), so a dead one falls back to the initial.
const is_avatar_broken = ref(false)
const avatar_img = useTemplateRef<HTMLImageElement>('avatar_img')


/** Computed */
const first_name = computed(() => auth.user?.name.trim().split(/\s+/)[0] ?? '')
const initial = computed(() => first_name.value.charAt(0).toUpperCase())

watch(() => auth.user?.avatar_url, () => {
    is_avatar_broken.value = false
})

// A server-rendered <img> can fail before hydration attaches @error; catch that case here.
onMounted(() => {
    const img = avatar_img.value

    if (img?.complete && img.naturalWidth === 0) {
        is_avatar_broken.value = true
    }
})
</script>

<template>
    <header class="header">
        <div>
            <p class="greeting">Kumusta, {{ first_name }}</p>
            <h1 class="title">Your money this month</h1>
        </div>
        <div class="avatar">
            <img
                v-if="auth.user?.avatar_url && !is_avatar_broken"
                ref="avatar_img"
                class="avatar-img"
                :src="auth.user.avatar_url"
                alt=""
                referrerpolicy="no-referrer"
                @error="is_avatar_broken = true"
            >
            <template v-else>{{ initial }}</template>
        </div>
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
    overflow: hidden;
    border: 1.5px solid var(--ink);
    border-radius: var(--radius-pill);
    background-color: var(--paper);
    color: var(--ink);
    font-family: var(--mono);
    font-weight: 600;
    font-size: 16px;
}

.avatar-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
</style>