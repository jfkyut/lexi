<script setup lang="ts">
export type InlineNode =
    | { type: 'text'; text: string }
    | { type: 'strong' | 'em' | 'del' | 'inline-code'; text: string }
    | { type: 'link'; text: string; href: string };

withDefaults(
    defineProps<{
        nodes: InlineNode[];
        typing?: boolean;
    }>(),
    {
        typing: false,
    },
);
</script>

<template>
    <template v-for="(node, index) in nodes" :key="index">
        <strong v-if="node.type === 'strong'" class="font-semibold">{{
            node.text
        }}</strong>
        <em v-else-if="node.type === 'em'">{{ node.text }}</em>
        <del v-else-if="node.type === 'del'">{{ node.text }}</del>
        <code
            v-else-if="node.type === 'inline-code'"
            class="rounded bg-muted px-1.5 py-0.5 font-mono text-[0.9em]"
            >{{ node.text }}</code
        >
        <a
            v-else-if="node.type === 'link'"
            :href="node.href"
            class="font-medium text-sky-700 underline decoration-sky-500/60 underline-offset-4 transition-colors hover:text-sky-800 hover:decoration-sky-700 focus-visible:rounded-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-600 dark:text-sky-400 dark:decoration-sky-400/60 dark:hover:text-sky-300 dark:hover:decoration-sky-300 dark:focus-visible:outline-sky-400"
            target="_blank"
            rel="noopener noreferrer"
            >{{ node.text }}</a
        >
        <span v-else>{{ node.text }}</span>
    </template>
    <span
        v-if="typing"
        class="ml-0.5 inline-block animate-pulse"
        aria-hidden="true"
        >▍</span
    >
</template>
