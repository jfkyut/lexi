<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 40 40"
        :class="className"
        v-bind="$attrs"
        fill="none"
        stroke="currentColor"
        stroke-linecap="round"
        stroke-linejoin="round"
    >
        <path
            d="M8 10a4 4 0 0 1 4-4h16a4 4 0 0 1 4 4v12a4 4 0 0 1-4 4h-8l-7 6v-6h-1a4 4 0 0 1-4-4V10Z"
            stroke-width="2.4"
        />
        <path
            d="M13 13.5c2.3-1.1 4.7-1.1 7 0v9c-2.3-1.1-4.7-1.1-7 0v-9Zm7 0c2.3-1.1 4.7-1.1 7 0v9c-2.3-1.1-4.7-1.1-7 0v-9Zm0 0v9"
            stroke-width="1.7"
        />
        <path
            d="M30 3v5m-2.5-2.5h5"
            stroke-width="1.8"
        />
    </svg>
</template>
