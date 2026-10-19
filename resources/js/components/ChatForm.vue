<script setup lang="ts">
import { nextTick, ref, watch } from 'vue';
import Button from '@/components/ui/button/Button.vue';

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

const textarea = ref<HTMLTextAreaElement | null>(null);

function resizeTextarea(): void {
    const element = textarea.value;

    if (!element) {
        return;
    }

    element.style.height = 'auto';
    element.style.height = `${Math.min(element.scrollHeight, 192)}px`;
    element.style.overflowY = element.scrollHeight > 192 ? 'auto' : 'hidden';
}

watch(
    () => props.modelValue,
    async () => {
        await nextTick();
        resizeTextarea();
    },
    { immediate: true },
);

function submitMessage(): void {
    const content = props.modelValue.trim();

    if (content !== '' && !props.processing) {
        emit('submit', content);
    }
}
</script>

<template>
    <form
        class="mx-auto flex w-full flex-col gap-2"
        :class="props.centered ? 'max-w-2xl' : 'max-w-3xl'"
        @submit.prevent="submitMessage"
    >
        <div class="flex items-end gap-2">
            <textarea
                ref="textarea"
                id="message"
                :value="props.modelValue"
                class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input min-h-9 max-h-48 w-full min-w-0 flex-1 resize-none rounded-md border bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive md:text-sm"
                required
                maxlength="10000"
                autocomplete="off"
                :placeholder="props.placeholder"
                aria-label="Message"
                rows="1"
                @input="emit('update:modelValue', ($event.target as HTMLTextAreaElement).value); resizeTextarea()"
                @keydown.ctrl.enter.prevent="submitMessage"
                @keydown.meta.enter.prevent="submitMessage"
            />
            <Button type="submit" :disabled="props.processing || !props.modelValue.trim()">
                {{ props.processing ? 'Generating...' : props.submitLabel }}
            </Button>
        </div>
        <p v-if="props.error" class="text-sm text-destructive" role="alert">
            {{ props.error }}
        </p>
    </form>
</template>
