<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

const name = usePage().props.name;
</script>

<template>
    <div class="flex min-w-0 items-center gap-2">
        <div
            class="flex size-8 shrink-0 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground"
        >
            <AppLogoIcon class="size-5 text-white dark:text-black" />
        </div>
        <div class="grid min-w-0 flex-1 text-left text-sm">
            <span class="truncate leading-tight font-semibold">{{ name }}</span>
        </div>
    </div>
</template>
