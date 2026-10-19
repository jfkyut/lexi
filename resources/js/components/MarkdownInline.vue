<script setup lang="ts">
export type InlineNode =
    | { type: 'text'; text: string }
    | { type: 'strong' | 'em' | 'del' | 'inline-code'; text: string }
    | { type: 'link'; text: string; href: string };

defineProps<{
    nodes: InlineNode[];
}>();
</script>

<template>
    <template v-for="(node, index) in nodes" :key="index">
        <strong v-if="node.type === 'strong'" class="font-semibold">{{ node.text }}</strong>
        <em v-else-if="node.type === 'em'">{{ node.text }}</em>
        <del v-else-if="node.type === 'del'">{{ node.text }}</del>
        <code
            v-else-if="node.type === 'inline-code'"
            class="rounded bg-muted px-1.5 py-0.5 font-mono text-[0.9em]"
        >{{ node.text }}</code>
        <a
            v-else-if="node.type === 'link'"
            :href="node.href"
            class="text-primary underline underline-offset-4"
            target="_blank"
            rel="noopener noreferrer"
        >{{ node.text }}</a>
        <span v-else>{{ node.text }}</span>
    </template>
</template>
