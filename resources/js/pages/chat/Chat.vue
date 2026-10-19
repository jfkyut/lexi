<script setup lang="ts">
import { Head, router, setLayoutProps } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import ChatForm from '@/components/ChatForm.vue';
import ChatMessageContent from '@/components/ChatMessageContent.vue';
import { storeMessage } from '@/actions/App/Http/Controllers/ThreadController';
import { dashboard } from '@/routes';

type ChatMessage = {
    id?: number;
    role: 'user' | 'assistant';
    content: string | null;
};

const props = defineProps<{
    thread: {
        id: string;
        title: string;
    };
    messages: ChatMessage[];
    pendingMessageId: number | null;
}>();

const messages = ref<ChatMessage[]>(props.messages.map((message) => ({ ...message })));
const threadTitle = ref(props.thread.title);
const message = ref('');
const processing = ref(false);
const error = ref('');

function updateThreadBreadcrumb(title: string): void {
    setLayoutProps({
        breadcrumbs: [
            {
                title,
                href: dashboard(),
            },
        ],
    });
}

updateThreadBreadcrumb(threadTitle.value);

function xsrfToken(): string {
    const cookie = document.cookie
        .split('; ')
        .find((part) => part.startsWith('XSRF-TOKEN='));

    if (!cookie) {
        throw new Error('Could not find a security token. Refresh the page and try again.');
    }

    return decodeURIComponent(cookie.slice('XSRF-TOKEN='.length));
}

async function sendMessage(content?: string, existingMessageId?: number): Promise<void> {
    if (processing.value) {
        return;
    }

    let assistantMessage: ChatMessage;

    if (content !== undefined) {
        messages.value.push({ role: 'user', content });
        messages.value.push({ role: 'assistant', content: '' });
        assistantMessage = messages.value[messages.value.length - 1];
        message.value = '';
    } else {
        const userMessageIndex = messages.value.findIndex(
            (item) => item.id === existingMessageId && item.role === 'user',
        );
        const nextMessage = messages.value[userMessageIndex + 1];

        assistantMessage = nextMessage?.role === 'assistant'
            ? nextMessage
            : { role: 'assistant', content: '' };

        if (nextMessage?.role !== 'assistant') {
            messages.value.push(assistantMessage);
            assistantMessage = messages.value[messages.value.length - 1];
        }
    }

    error.value = '';
    processing.value = true;

    try {
        const response = await fetch(storeMessage.url(props.thread.id), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'text/event-stream, application/json',
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            body: JSON.stringify(existingMessageId === undefined
                ? { message: content }
                : { message_id: existingMessageId }),
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

                if (eventName === 'title') {
                    const payload = JSON.parse(data) as { title?: unknown };

                    if (typeof payload.title === 'string') {
                        threadTitle.value = payload.title;
                        updateThreadBreadcrumb(payload.title);
                        router.reload({ only: ['threads'] });
                    }
                }

                if (eventName === 'update') {
                    const payload = JSON.parse(data) as { content?: unknown };

                    if (typeof payload.content === 'string') {
                        assistantMessage.content = (assistantMessage.content ?? '') + payload.content;
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

        if (existingMessageId !== undefined) {
            window.history.replaceState(window.history.state, '', window.location.pathname);
        }
    } catch (caughtError) {
        const assistantIndex = messages.value.indexOf(assistantMessage);

        if (assistantIndex >= 0) {
            messages.value.splice(assistantIndex, 1);
        }

        error.value = caughtError instanceof Error
            ? caughtError.message
            : 'The assistant could not complete this response.';
    } finally {
        processing.value = false;
    }
}

function submitMessage(content: string): void {
    void sendMessage(content);
}

onMounted(() => {
    if (props.pendingMessageId !== null) {
        void sendMessage(undefined, props.pendingMessageId);
    }
});

</script>

<template>
    <Head :title="threadTitle" />

    <main class="flex h-full min-h-0 flex-1 flex-col gap-4 p-4 border">
        <section
            class="overflow-y-auto p-2"
            role="log"
            aria-live="polite"
            aria-label="Conversation"
        >
            <div class="h-[75vh] max-w-4xl mx-auto flex flex-1 flex-col gap-4">
                <article
                    v-for="(item, index) in messages"
                    :key="item.id ?? `local-${index}`"
                    class="min-w-0 max-w-6xl rounded-xl p-4"
                    :class="item.role === 'user'
                        ? 'ml-auto bg-primary text-primary-foreground'
                        : 'mr-auto w-full'"
                >
                    <p class="mb-1 text-xs font-semibold uppercase opacity-70">
                        {{ item.role === 'user' ? 'You' : 'Lexi' }}
                    </p>
                    <p v-if="item.role === 'user'" class="whitespace-pre-wrap leading-7">
                        {{ item.content }}
                    </p>
                    <ChatMessageContent v-else :content="item.content" />
                    <span
                        v-if="item.role === 'assistant' && processing && index === messages.length - 1"
                        class="animate-pulse"
                        aria-label="Generating"
                    >▍</span>
                </article>
            </div>
        </section>

        <ChatForm
            v-model="message"
            :processing="processing"
            :error="error"
            @submit="submitMessage"
        />
    </main>
</template>