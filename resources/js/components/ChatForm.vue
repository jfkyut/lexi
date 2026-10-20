<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import { Textarea } from '@/components/ui/textarea';

const props = withDefaults(defineProps<{
    modelValue: string;
    processing?: boolean;
    error?: string;
    placeholder?: string;
    submitLabel?: string;
    centered?: boolean;
}>(), {
    processing: false,
    error: '',
    placeholder: 'Type your message here...',
    submitLabel: 'Send',
    centered: false,
});

const emit = defineEmits<{
    'update:modelValue': [value: string];
    submit: [message: string];
}>();

function submitMessage(): void {
    const content = props.modelValue.trim();

    if (content !== '' && !props.processing) {
        emit('submit', content);
    }
}

function handleEnter(event: KeyboardEvent): void {
    if (event.shiftKey) {
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
            <Textarea
                id="message"
                :model-value="props.modelValue"
                class="min-h-14 max-h-48 resize-none border-0 bg-transparent px-4 py-3 shadow-none focus-visible:border-transparent focus-visible:ring-0 dark:bg-transparent"
                auto-resize
                :max-height="192"
                required
                maxlength="10000"
                autocomplete="off"
                :placeholder="props.placeholder"
                aria-label="Message"
                rows="1"
                @update:model-value="emit('update:modelValue', String($event))"
                @keydown.enter="handleEnter"
            />
            <Button
                type="submit"
                size="sm"
                class="self-end mx-2 mb-2"
                :disabled="props.processing || !props.modelValue.trim()"
            >
                {{ props.processing ? 'Generating...' : props.submitLabel }}
            </Button>
        </div>
        <p v-if="props.error" class="text-sm text-destructive" role="alert">
            {{ props.error }}
        </p>
    </form>
</template>
