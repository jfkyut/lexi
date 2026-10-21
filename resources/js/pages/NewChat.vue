<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ChatForm from '@/components/ChatForm.vue';
import { store } from '@/actions/App/Http/Controllers/ThreadController';
import { dashboard } from '@/routes';
import { useForm } from '@inertiajs/vue3';

const form = useForm({
    message: '',
    attachments: [] as File[],
});

function createThread(message: string, attachments: File[]): void {
    form.message = message;
    form.attachments = attachments;
    form.post(store.url(), {
        preserveScroll: true,
        forceFormData: true,
    });
}

function attachmentError(): string | undefined {
    return (
        form.errors.attachments ??
        Object.entries(form.errors).find(([field]) =>
            field.startsWith('attachments.'),
        )?.[1]
    );
}

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'New chat',
                href: dashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head title="New chat" />

    <main
        class="flex h-full min-h-[60vh] flex-1 flex-col items-center justify-center gap-5 p-4"
    >
        <div class="w-full max-w-2xl space-y-2 text-center">
            <h1 class="text-3xl font-semibold tracking-tight">
                Start a new chat
            </h1>
            <p class="text-muted-foreground">
                Ask Lexi a question or attach a photo or document to analyze.
            </p>
        </div>

        <ChatForm
            :model-value="form.message"
            :processing="form.processing"
            :files="form.attachments"
            :error="form.errors.message || attachmentError()"
            submit-label="Start chat"
            centered
            @update:model-value="form.message = $event"
            @update:files="form.attachments = $event"
            @submit="createThread"
        />
    </main>
</template>
