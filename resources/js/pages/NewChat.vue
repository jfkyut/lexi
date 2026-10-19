<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import { dashboard } from '@/routes';
import Button from '@/components/ui/button/Button.vue';
import { store } from '@/actions/App/Http/Controllers/ThreadController';
import { Input } from '@/components/ui/input';

type ChatMessage = {
    role: 'user' | 'assistant';
    content: string;
};

const messages = ref<ChatMessage[]>([]);
const message = ref('');
const processing = ref(false);
const error = ref('');

function xsrfToken(): string {
    const cookie = document.cookie
        .split('; ')
        .find((part) => part.startsWith('XSRF-TOKEN='));

    if (!cookie) {
        throw new Error('Could not find a security token. Refresh the page and try again.');
    }

    return decodeURIComponent(cookie.slice('XSRF-TOKEN='.length));
}

async function sendMessage(): Promise<void> {
    const content = message.value.trim();

    if (content === '' || processing.value) {
        return;
    }

    messages.value.push(
        { role: 'user', content },
        { role: 'assistant', content: '' },
    );
    message.value = '';
    error.value = '';
    processing.value = true;

    try {
        const response = await fetch(store.url(), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'text/event-stream, application/json',
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            body: JSON.stringify({ message: content }),
        });

        if (!response.ok) {
            throw new Error(`The request failed (${response.status}). Please try again.`);
        }

        if (!response.body) {
            throw new Error('The server did not provide a response stream.');
        }

        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let lineBuffer = '';
        let eventName = 'update';
        let eventData: string[] = [];

        const dispatchEvent = (): void => {
            if (eventData.length > 0) {
                const data = eventData.join('\n');

                if (eventName === 'error') {
                    const payload = JSON.parse(data) as { message?: string };
                    throw new Error(payload.message ?? 'The assistant could not complete this response.');
                }

                if (eventName === 'update') {
                    const payload = JSON.parse(data) as { content?: unknown };

                    if (typeof payload.content === 'string') {
                        messages.value[messages.value.length - 1].content += payload.content;
                    }
                }
            }

            eventName = 'update';
            eventData = [];
        };

        const processLine = (line: string): void => {
            if (line === '') {
                dispatchEvent();
            } else if (line.startsWith('event:')) {
                eventName = line.slice('event:'.length).trim();
            } else if (line.startsWith('data:')) {
                eventData.push(line.slice('data:'.length).replace(/^ /, ''));
            }
        };

        while (true) {
            const { value, done } = await reader.read();
            lineBuffer += decoder.decode(value, { stream: !done });

            const lines = lineBuffer.split(/\r?\n/);
            lineBuffer = lines.pop() ?? '';

            for (const line of lines) {
                processLine(line);
            }

            if (done) {
                if (lineBuffer !== '') {
                    processLine(lineBuffer);
                }

                dispatchEvent();
                break;
            }
        }

        reader.releaseLock();
    } catch (caughtError) {
        error.value = caughtError instanceof Error
            ? caughtError.message
            : 'The assistant could not complete this response.';
    } finally {
        processing.value = false;
    }
}

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Chat',
                href: dashboard(),
            },
        ],
    },
});


</script>

<template>
    <Head title="Chat" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 relative">
        <div
            class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto"
            role="log"
            aria-live="polite"
            aria-label="Conversation"
        >
            <div
                v-for="(item, index) in messages"
                :key="index"
                class="max-w-3xl whitespace-pre-wrap rounded-xl p-4"
                :class="item.role === 'user'
                    ? 'ml-auto bg-primary text-primary-foreground'
                    : 'mr-auto border border-sidebar-border/70 dark:border-sidebar-border'"
            >
                <p class="mb-1 text-xs font-semibold uppercase opacity-70">
                    {{ item.role === 'user' ? 'You' : 'Lexi' }}
                </p>
                <p>{{ item.content }}<span v-if="item.role === 'assistant' && processing && index === messages.length - 1" aria-label="Generating">▍</span></p>
            </div>

            <p v-if="error" class="text-sm text-destructive" role="alert">
                {{ error }}
            </p>
        </div>

        <form class="flex gap-2 absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2" @submit.prevent="sendMessage">
            <Input
                id="message"
                v-model="message"
                class="min-w-0 flex-1"
                required
                autocomplete="off"
                placeholder="Type your message here..."
                aria-label="Message"
                @keydown.enter.exact.prevent="sendMessage"
            />
            <Button type="submit" :disabled="processing || !message.trim()">
                {{ processing ? 'Generating...' : 'Send' }}
            </Button>
        </form>
    </div>
</template>
