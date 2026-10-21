<script setup lang="ts">
import { ref } from 'vue';
import Button from '@/components/ui/button/Button.vue';
import { Textarea } from '@/components/ui/textarea';
import { Loader2Icon, Paperclip, Send, X } from '@lucide/vue';

const MAX_ATTACHMENTS = 20;
const MAX_ATTACHMENT_SIZE = 1024 ** 3;
const MAX_TOTAL_ATTACHMENT_SIZE = 1024 ** 3;

const props = withDefaults(
    defineProps<{
        modelValue: string;
        files?: File[];
        processing?: boolean;
        error?: string;
        placeholder?: string;
        submitLabel?: string;
        centered?: boolean;
    }>(),
    {
        files: () => [],
        processing: false,
        error: '',
        placeholder: 'Type your message here...',
        submitLabel: 'Send',
        centered: false,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
    'update:files': [files: File[]];
    submit: [message: string, files: File[]];
}>();

const fileInput = ref<HTMLInputElement | null>(null);
const fileError = ref('');

function submitMessage(): void {
    const content = props.modelValue.trim();

    if ((content !== '' || props.files.length > 0) && !props.processing) {
        emit('submit', content, props.files);
    }
}

function addFiles(newFiles: File[]): void {
    const files = [...props.files, ...newFiles];

    fileError.value = '';

    if (files.length > MAX_ATTACHMENTS) {
        fileError.value = 'You can attach up to 20 files.';
        return;
    }

    if (files.some((file) => file.size > MAX_ATTACHMENT_SIZE)) {
        fileError.value = 'Each file must be 1 GB or smaller.';
        return;
    }

    if (
        files.reduce((total, file) => total + file.size, 0) >
        MAX_TOTAL_ATTACHMENT_SIZE
    ) {
        fileError.value = 'The combined size of the files may not exceed 1 GB.';
        return;
    }

    emit('update:files', files);
}

function selectFiles(event: Event): void {
    const input = event.target as HTMLInputElement;
    const selectedFiles = Array.from(input.files ?? []);

    input.value = '';

    addFiles(selectedFiles);
}

function pasteFiles(event: ClipboardEvent): void {
    const imageFiles = Array.from(event.clipboardData?.items ?? [])
        .filter((item) => item.kind === 'file' && item.type.startsWith('image/'))
        .map((item) => item.getAsFile())
        .filter((file): file is File => file !== null)
        .map((file, index) => {
            if (file.name) {
                return file;
            }

            const extension = file.type.split('/')[1]?.split('+')[0] || 'png';
            const filename = `pasted-image-${Date.now()}-${index + 1}.${extension}`;

            return new File([file], filename, {
                type: file.type,
                lastModified: file.lastModified,
            });
        });

    if (imageFiles.length > 0) {
        addFiles(imageFiles);
    }
}

function removeFile(index: number): void {
    emit(
        'update:files',
        props.files.filter((_, fileIndex) => fileIndex !== index),
    );
    fileError.value = '';
}

function handleEnter(event: KeyboardEvent): void {
    if (event.shiftKey || event.isComposing) {
        return;
    }

    event.preventDefault();
    submitMessage();
}
</script>

<template>
    <form
        class="mx-auto flex w-full flex-col gap-2"
        :class="props.centered ? 'max-w-2xl' : 'max-w-3xl'"
        @submit.prevent="submitMessage"
    >
        <div
            class="flex flex-col rounded-xl border border-input bg-background transition-colors focus-within:border-ring focus-within:ring-2 focus-within:ring-ring/30"
        >
            <div
                v-if="props.files.length > 0"
                class="flex flex-wrap gap-2 px-3 pt-3"
            >
                <span
                    v-for="(file, index) in props.files"
                    :key="`${file.name}-${file.size}-${index}`"
                    class="inline-flex max-w-full items-center gap-1 rounded-md bg-muted px-2 py-1 text-xs"
                >
                    <span class="max-w-56 truncate">{{ file.name }}</span>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        class="size-6"
                        :aria-label="`Remove ${file.name}`"
                        @click="removeFile(index)"
                    >
                        <X class="size-3" aria-hidden="true" />
                    </Button>
                </span>
            </div>
            <Textarea
                id="message"
                :model-value="props.modelValue"
                class="max-h-48 min-h-14 resize-none border-0 bg-transparent px-4 py-3 shadow-none focus-visible:border-transparent focus-visible:ring-0 dark:bg-transparent"
                auto-resize
                :max-height="192"
                maxlength="10000"
                autocomplete="off"
                :placeholder="props.placeholder"
                aria-label="Message"
                rows="1"
                @paste="pasteFiles"
                @update:model-value="emit('update:modelValue', String($event))"
                @keydown.enter="handleEnter"
            />
            <div class="flex items-center justify-between px-2 pb-2">
                <div class="flex items-center gap-2">
                    <input
                        ref="fileInput"
                        type="file"
                        multiple
                        accept=".pdf,.docx,.txt,.jpg,.jpeg,.png,.webp"
                        class="sr-only"
                        aria-label="Choose images, PDF, DOCX, or TXT files to attach"
                        @change="selectFiles"
                    />
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        aria-label="Attach files"
                        title="Attach files"
                        :disabled="
                            props.processing ||
                            props.files.length >= MAX_ATTACHMENTS
                        "
                        @click="fileInput?.click()"
                    >
                        <Paperclip class="size-4" aria-hidden="true" />
                    </Button>
                    <p class="text-xs text-muted-foreground">
                        Paste screenshots or attach images, PDF, DOCX, TXT · Up
                        to 20 files, 1 GB total
                    </p>
                </div>
                <Button
                    type="submit"
                    size="icon-sm"
                    class="hover:cursor-pointer"
                    :disabled="
                        props.processing ||
                        (!props.modelValue.trim() && props.files.length === 0)
                    "
                    :aria-label="
                        props.processing ? 'Generating...' : props.submitLabel
                    "
                    :title="
                        props.processing ? 'Generating...' : props.submitLabel
                    "
                >
                    <Loader2Icon
                        v-if="props.processing"
                        class="size-4 animate-spin"
                        aria-hidden="true"
                    />
                    <Send v-else class="size-4" aria-hidden="true" />
                </Button>
            </div>
        </div>
        <p
            v-if="props.error || fileError"
            class="text-sm text-destructive"
            role="alert"
        >
            {{ fileError || props.error }}
        </p>
    </form>
</template>
