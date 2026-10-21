<script setup lang="ts">
import { Head, router, setLayoutProps } from '@inertiajs/vue3';
import { onMounted, ref, watch } from 'vue';
import ChatForm from '@/components/ChatForm.vue';
import ChatMessageContent from '@/components/ChatMessageContent.vue';
import { storeMessage } from '@/actions/App/Http/Controllers/ThreadController';
import { show as showAttachment } from '@/actions/App/Http/Controllers/AttachmentController';
import { dashboard } from '@/routes';

type ChatAttachment = {
    id?: number;
    type?: string;
    filename: string;
    size: number;
};

type ChatMessage = {
    id?: number;
    role: 'user' | 'assistant';
    content: string | null;
    attachments?: ChatAttachment[];
};

const props = defineProps<{
    thread: {
        id: string;
        title: string;
    };
    messages: ChatMessage[];
    pendingMessageId: number | null;
}>();

const messages = ref<ChatMessage[]>(
    props.messages.map((message) => ({ ...message })),
);
const threadTitle = ref(props.thread.title);
const message = ref('');
const files = ref<File[]>([]);
const processing = ref(false);
const error = ref('');
const conversation = ref<HTMLElement | null>(null);

function scrollToBottom(): void {
    if (conversation.value) {
        conversation.value.scrollTop = conversation.value.scrollHeight;
    }
}

watch(() => messages.value.map((item) => item.content), scrollToBottom, {
    flush: 'post',
});

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
        throw new Error(
            'Could not find a security token. Refresh the page and try again.',
        );
    }

    return decodeURIComponent(cookie.slice('XSRF-TOKEN='.length));
}

async function sendMessage(
    content?: string,
    existingMessageId?: number,
    submittedFiles: File[] = [],
): Promise<void> {
    if (processing.value) {
        return;
    }

    let assistantMessage: ChatMessage;
    let optimisticUserMessage: ChatMessage | undefined;

    if (content !== undefined) {
        optimisticUserMessage = {
            role: 'user',
            content,
            attachments: submittedFiles.map((file) => ({
                filename: file.name,
                size: file.size,
            })),
        };
        messages.value.push(optimisticUserMessage);
        messages.value.push({ role: 'assistant', content: '' });
        assistantMessage = messages.value[messages.value.length - 1];
        message.value = '';
    } else {
        const userMessageIndex = messages.value.findIndex(
            (item) => item.id === existingMessageId && item.role === 'user',
        );
        const nextMessage = messages.value[userMessageIndex + 1];

        assistantMessage =
            nextMessage?.role === 'assistant'
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
        const formData = new FormData();

        if (existingMessageId === undefined) {
            formData.append('message', content ?? '');
            submittedFiles.forEach((file) =>
                formData.append('attachments[]', file),
            );
        } else {
            formData.append('message_id', String(existingMessageId));
        }

        const response = await fetch(storeMessage.url(props.thread.id), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json, text/event-stream',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            body: formData,
        });

        if (!response.ok) {
            let errorMessage = `The request failed (${response.status}). Please try again.`;

            if (response.status === 422) {
                const payload = (await response.json()) as {
                    errors?: Record<string, string[]>;
                };
                errorMessage =
                    Object.values(payload.errors ?? {}).flat()[0] ??
                    errorMessage;
            }

            throw new Error(errorMessage);
        }

        if (
            !response.headers.get('content-type')?.includes('text/event-stream')
        ) {
            throw new Error(
                'The server did not return a chat response. Refresh the page and try again.',
            );
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
                    throw new Error(
                        payload.message ??
                            'The assistant could not complete this response.',
                    );
                }

                if (eventName === 'title') {
                    const payload = JSON.parse(data) as { title?: unknown };

                    if (typeof payload.title === 'string') {
                        threadTitle.value = payload.title;
                        updateThreadBreadcrumb(payload.title);
                        router.reload({ only: ['threads'] });
                    }
                }

                if (eventName === 'user-message') {
                    const payload = JSON.parse(data) as {
                        id?: number;
                        attachments?: ChatAttachment[];
                    };

                    if (
                        optimisticUserMessage &&
                        typeof payload.id === 'number'
                    ) {
                        optimisticUserMessage.id = payload.id;
                        optimisticUserMessage.attachments =
                            payload.attachments ?? [];
                    }
                }

                if (eventName === 'attachments') {
                    const payload = JSON.parse(data) as {
                        attachments?: ChatAttachment[];
                    };
                    assistantMessage.attachments = payload.attachments ?? [];
                }

                if (eventName === 'update') {
                    const payload = JSON.parse(data) as { content?: unknown };

                    if (typeof payload.content === 'string') {
                        assistantMessage.content =
                            (assistantMessage.content ?? '') + payload.content;
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

        files.value = [];

        if (existingMessageId !== undefined) {
            window.history.replaceState(
                window.history.state,
                '',
                window.location.pathname,
            );
        }
    } catch (caughtError) {
        const assistantIndex = messages.value.indexOf(assistantMessage);

        if (assistantIndex >= 0) {
            messages.value.splice(assistantIndex, 1);
        }

        error.value =
            caughtError instanceof Error
                ? caughtError.message
                : 'The assistant could not complete this response.';
    } finally {
        processing.value = false;
    }
}

function submitMessage(content: string, submittedFiles: File[]): void {
    void sendMessage(content, undefined, submittedFiles);
}

onMounted(() => {
    scrollToBottom();

    if (props.pendingMessageId !== null) {
        void sendMessage(undefined, props.pendingMessageId);
    }
});
</script>

<template>
    <Head :title="threadTitle" />

    <main class="flex h-full min-h-0 flex-1 flex-col gap-4 p-4">
        <section
            ref="conversation"
            class="overflow-y-auto p-2"
            role="log"
            aria-live="polite"
            aria-label="Conversation"
        >
            <div class="mx-auto flex h-[75vh] max-w-4xl flex-1 flex-col gap-4">
                <article
                    v-for="(item, index) in messages"
                    :key="item.id ?? `local-${index}`"
                    class="max-w-6xl min-w-0 rounded-xl p-4"
                    :class="
                        item.role === 'user'
                            ? 'ml-auto bg-primary text-primary-foreground'
                            : 'mr-auto w-full'
                    "
                >
                    <p class="mb-1 text-xs font-semibold uppercase opacity-70">
                        {{ item.role === 'user' ? 'You' : 'Lexi' }}
                    </p>
                    <p
                        v-if="item.role === 'user'"
                        class="leading-7 whitespace-pre-wrap"
                    >
                        {{ item.content }}
                    </p>
                    <ChatMessageContent v-else :content="item.content" />
                    <ul
                        v-if="item.attachments?.length"
                        class="mt-3 flex flex-wrap gap-2"
                    >
                        <li
                            v-for="attachment in item.attachments"
                            :key="attachment.id ?? attachment.filename"
                        >
                            <a
                                v-if="attachment.id"
                                :href="showAttachment.url(attachment.id)"
                                class="inline-flex max-w-64 items-center gap-2 rounded-md border border-current/20 px-3 py-2 text-sm hover:bg-muted/50"
                            >
                                <span class="truncate">{{
                                    attachment.filename
                                }}</span>
                                <span class="shrink-0 text-xs opacity-70"
                                    >{{ attachment.size }} B</span
                                >
                            </a>
                            <span
                                v-else
                                class="inline-flex max-w-64 items-center gap-2 rounded-md border border-current/20 px-3 py-2 text-sm"
                            >
                                <span class="truncate">{{
                                    attachment.filename
                                }}</span>
                                <span class="shrink-0 text-xs opacity-70"
                                    >{{ attachment.size }} B</span
                                >
                            </span>
                        </li>
                    </ul>
                    <span
                        v-if="
                            item.role === 'assistant' &&
                            processing &&
                            index === messages.length - 1
                        "
                        class="animate-pulse"
                        aria-label="Generating"
                        >▍</span
                    >
                </article>
            </div>
        </section>

        <ChatForm
            v-model="message"
            v-model:files="files"
            :processing="processing"
            :error="error"
            @submit="submitMessage"
        />
    </main>
</template>
