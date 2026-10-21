<script setup lang="ts">
import { computed, ref } from 'vue';
import MarkdownInline, { type InlineNode } from '@/components/MarkdownInline.vue';

type MessageBlock =
    | { type: 'paragraph' | 'quote'; text: string; inline: InlineNode[] }
    | { type: 'heading'; level: number; text: string; inline: InlineNode[] }
    | { type: 'list'; ordered: boolean; items: InlineNode[][] }
    | { type: 'code'; language: string; code: string }
    | { type: 'rule' }
    | { type: 'table'; headers: InlineNode[][]; rows: InlineNode[][][] };

const props = defineProps<{
    content: string | null;
}>();

const copiedBlock = ref<number | null>(null);
const failedCopyBlock = ref<number | null>(null);

function safeLinkTarget(href: string): string | null {
    const target = href.trim();

    return !target.includes('\\') && /^(https?:\/\/|mailto:|\/(?!\/)|#)/i.test(target)
        ? target
        : null;
}

function parseInline(text: string): InlineNode[] {
    const nodes: InlineNode[] = [];
    const pattern = /(`[^`]+`|\*\*.+?\*\*|__.+?__|~~.+?~~|\*[^*\n]+\*|_[^_\n]+_|\[[^\]]+\]\([^)]+\))/g;
    let cursor = 0;

    for (const match of text.matchAll(pattern)) {
        const value = match[0];
        const index = match.index;

        if (index > cursor) {
            nodes.push({ type: 'text', text: text.slice(cursor, index) });
        }

        if (value.startsWith('`')) {
            nodes.push({ type: 'inline-code', text: value.slice(1, -1) });
        } else if (value.startsWith('**') || value.startsWith('__')) {
            nodes.push({ type: 'strong', text: value.slice(2, -2) });
        } else if (value.startsWith('~~')) {
            nodes.push({ type: 'del', text: value.slice(2, -2) });
        } else if (value.startsWith('*') || value.startsWith('_')) {
            nodes.push({ type: 'em', text: value.slice(1, -1) });
        } else {
            const link = value.match(/^\[([^\]]+)\]\(([^)]+)\)$/);
            const href = link ? safeLinkTarget(link[2]) : null;

            if (link && href) {
                nodes.push({ type: 'link', text: link[1], href });
            } else {
                nodes.push({ type: 'text', text: value });
            }
        }

        cursor = index + value.length;
    }

    if (cursor < text.length) {
        nodes.push({ type: 'text', text: text.slice(cursor) });
    }

    return nodes;
}

function tableCells(line: string): string[] {
    return line
        .trim()
        .replace(/^\|/, '')
        .replace(/\|$/, '')
        .split('|')
        .map((cell) => cell.trim());
}

const blocks = computed<MessageBlock[]>(() => {
    const lines = (props.content ?? '').split(/\r?\n/);
    const parsed: MessageBlock[] = [];
    let paragraph: string[] = [];
    let quote: string[] = [];
    let listItems: string[] = [];
    let listOrdered = false;

    const flushParagraph = (): void => {
        if (paragraph.length > 0) {
            const text = paragraph.join(' ');
            parsed.push({ type: 'paragraph', text, inline: parseInline(text) });
            paragraph = [];
        }
    };

    const flushQuote = (): void => {
        if (quote.length > 0) {
            const text = quote.join(' ');
            parsed.push({ type: 'quote', text, inline: parseInline(text) });
            quote = [];
        }
    };

    const flushList = (): void => {
        if (listItems.length > 0) {
            parsed.push({
                type: 'list',
                ordered: listOrdered,
                items: listItems.map(parseInline),
            });
            listItems = [];
        }
    };

    const flushText = (): void => {
        flushParagraph();
        flushQuote();
        flushList();
    };

    for (let index = 0; index < lines.length; index += 1) {
        const line = lines[index];
        const fence = line.match(/^\s*(```+|~~~+)\s*([\w.+-]*)\s*$/);

        if (fence) {
            flushText();

            const codeLines: string[] = [];
            let closingFence = -1;

            for (let codeIndex = index + 1; codeIndex < lines.length; codeIndex += 1) {
                if (new RegExp(`^\\s*${fence[1][0]}{${fence[1].length},}\\s*$`).test(lines[codeIndex])) {
                    closingFence = codeIndex;
                    break;
                }

                codeLines.push(lines[codeIndex]);
            }

            parsed.push({
                type: 'code',
                language: fence[2],
                code: codeLines.join('\n'),
            });

            index = closingFence === -1 ? lines.length : closingFence;
            continue;
        }

        if (line.trim() === '') {
            flushText();
            continue;
        }

        const nextLine = lines[index + 1] ?? '';

        if (line.includes('|') && /^\s*\|?\s*:?-{3,}:?\s*(\|\s*:?-{3,}:?\s*)+\|?\s*$/.test(nextLine)) {
            flushText();
            const headers = tableCells(line).map(parseInline);
            const rows: InlineNode[][][] = [];

            index += 2;

            while (index < lines.length && lines[index].includes('|') && lines[index].trim() !== '') {
                rows.push(tableCells(lines[index]).map(parseInline));
                index += 1;
            }

            index -= 1;
            parsed.push({ type: 'table', headers, rows });
            continue;
        }

        const heading = line.match(/^\s{0,3}(#{1,6})\s+(.+?)\s*#*\s*$/);

        if (heading) {
            flushText();
            parsed.push({
                type: 'heading',
                level: heading[1].length,
                text: heading[2],
                inline: parseInline(heading[2]),
            });
            continue;
        }

        if (/^\s{0,3}([-*_])(?:\s*\1){2,}\s*$/.test(line)) {
            flushText();
            parsed.push({ type: 'rule' });
            continue;
        }

        const quoteLine = line.match(/^\s{0,3}>\s?(.*)$/);

        if (quoteLine) {
            flushParagraph();
            flushList();
            quote.push(quoteLine[1]);
            continue;
        }

        const unorderedItem = line.match(/^\s*[-*+]\s+(.+)$/);
        const orderedItem = line.match(/^\s*\d+[.)]\s+(.+)$/);
        const listItem = unorderedItem ?? orderedItem;

        if (listItem) {
            flushParagraph();
            flushQuote();

            const ordered = orderedItem !== null;

            if (listItems.length > 0 && listOrdered !== ordered) {
                flushList();
            }

            listOrdered = ordered;
            listItems.push(listItem[1]);
            continue;
        }

        flushQuote();
        flushList();
        paragraph.push(line.trim());
    }

    flushText();

    return parsed;
});

async function copyCode(code: string, blockIndex: number): Promise<void> {
    try {
        await navigator.clipboard.writeText(code);
        copiedBlock.value = blockIndex;
        failedCopyBlock.value = null;
        window.setTimeout(() => {
            if (copiedBlock.value === blockIndex) {
                copiedBlock.value = null;
            }
        }, 1500);
    } catch {
        failedCopyBlock.value = blockIndex;
        window.setTimeout(() => {
            if (failedCopyBlock.value === blockIndex) {
                failedCopyBlock.value = null;
            }
        }, 2000);
    }
}
</script>

<template>
    <div class="flex min-w-0 flex-col gap-4 break-words">
        <template v-for="(block, index) in blocks" :key="index">
            <p v-if="block.type === 'paragraph'" class="whitespace-pre-wrap leading-7">
                <MarkdownInline :nodes="block.inline" />
            </p>

            <component
                :is="`h${block.level}`"
                v-else-if="block.type === 'heading'"
                class="font-semibold tracking-tight"
                :class="block.level <= 2 ? 'text-xl' : 'text-lg'"
            >
                <MarkdownInline :nodes="block.inline" />
            </component>

            <ul
                v-else-if="block.type === 'list' && !block.ordered"
                class="list-disc space-y-1.5 pl-6 leading-7 marker:text-muted-foreground"
            >
                <li v-for="(item, itemIndex) in block.items" :key="itemIndex">
                    <MarkdownInline :nodes="item" />
                </li>
            </ul>

            <ol
                v-else-if="block.type === 'list' && block.ordered"
                class="list-decimal space-y-1.5 pl-6 leading-7 marker:text-muted-foreground"
            >
                <li v-for="(item, itemIndex) in block.items" :key="itemIndex">
                    <MarkdownInline :nodes="item" />
                </li>
            </ol>

            <blockquote
                v-else-if="block.type === 'quote'"
                class="border-l-2 border-primary/40 pl-4 text-muted-foreground"
            >
                <MarkdownInline :nodes="block.inline" />
            </blockquote>

            <hr v-else-if="block.type === 'rule'" class="border-border" />

            <div
                v-else-if="block.type === 'table'"
                class="overflow-x-auto rounded-lg border border-border"
            >
                <table class="w-full border-collapse text-left text-sm">
                    <thead class="bg-muted/60">
                        <tr>
                            <th
                                v-for="(cell, cellIndex) in block.headers"
                                :key="cellIndex"
                                class="border-b border-border px-3 py-2 font-semibold"
                            >
                                <MarkdownInline :nodes="cell" />
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, rowIndex) in block.rows" :key="rowIndex" class="even:bg-muted/30">
                            <td
                                v-for="(cell, cellIndex) in row"
                                :key="cellIndex"
                                class="border-b border-border/70 px-3 py-2 last:border-b-0"
                            >
                                <MarkdownInline :nodes="cell" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-else-if="block.type === 'code'"
                class="overflow-hidden rounded-lg border border-border bg-muted/40"
            >
                <div class="flex items-center justify-between border-b border-border bg-muted/70 px-4 py-2">
                    <span class="text-xs text-muted-foreground">{{ block.language || 'Code' }}</span>
                    <button
                        type="button"
                        class="text-xs text-muted-foreground transition-colors hover:text-foreground"
                        :aria-label="copiedBlock === index
                            ? 'Code copied'
                            : failedCopyBlock === index
                                ? 'Could not copy code'
                                : 'Copy code'"
                        @click="copyCode(block.code, index)"
                    >
                        {{
                            copiedBlock === index
                                ? 'Copied'
                                : failedCopyBlock === index
                                    ? 'Copy failed'
                                    : 'Copy code'
                        }}
                    </button>
                </div>
                <pre class="overflow-x-auto p-4 text-sm leading-6"><code class="font-mono">{{ block.code }}</code></pre>
            </div>
        </template>
    </div>
</template>
