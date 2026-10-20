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
            d="M11 9.5h18a4 4 0 0 1 4 4v10a4 4 0 0 1-4 4H18l-7 5v-5a4 4 0 0 1-4-4v-10a4 4 0 0 1 4-4Z"
            stroke-width="2.2"
        />
        <path d="M13 16h14m-14 5h9" stroke-width="2.4" />
    </svg>
</template>
