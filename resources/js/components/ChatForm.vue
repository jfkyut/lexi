<script setup lang="ts">
import { Input } from '@/components/ui/input';
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
        <div class="flex gap-2">
            <Input
                id="message"
                :model-value="props.modelValue"
                class="min-w-0 flex-1"
                required
                maxlength="10000"
                autocomplete="off"
                :placeholder="props.placeholder"
                aria-label="Message"
                @update:model-value="emit('update:modelValue', String($event))"
                @keydown.enter.exact.prevent="submitMessage"
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
