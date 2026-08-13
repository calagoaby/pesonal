<script setup lang="ts">
import { useToaster } from '~/stores/toaster';

// Connects the global toast store to the presentational toast. Mounted once per
// layout so whichever screen is active shows the one live toast.
const toaster = useToaster()
</script>

<template>
    <SharedToast
        :open="toaster.visible"
        :message="toaster.message"
        :action-label="toaster.action?.label"
        @action="toaster.runAction"
    />
</template>
