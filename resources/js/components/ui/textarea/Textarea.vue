<script setup lang="ts">
import type { TextareaHTMLAttributes } from "vue"
import { nextTick, ref, watch } from "vue"
import { useVModel } from "@vueuse/core"
import { cn } from "@/lib/utils"

const props = withDefaults(defineProps<{
  defaultValue?: string | number
  modelValue?: string | number
  class?: TextareaHTMLAttributes["class"]
  autoResize?: boolean
  maxHeight?: number
}>(), {
  autoResize: false,
  maxHeight: 192,
})

const emits = defineEmits<{
  (e: "update:modelValue", payload: string | number): void
}>()

const modelValue = useVModel(props, "modelValue", emits, {
  passive: true,
  defaultValue: props.defaultValue,
})
const textarea = ref<HTMLTextAreaElement | null>(null)

function resizeTextarea(): void {
  if (!props.autoResize || !textarea.value) {
    return
  }

  textarea.value.style.height = "auto"
  textarea.value.style.height = `${Math.min(textarea.value.scrollHeight, props.maxHeight)}px`
  textarea.value.style.overflowY = textarea.value.scrollHeight > props.maxHeight ? "auto" : "hidden"
}

watch(modelValue, async () => {
  await nextTick()
  resizeTextarea()
}, { immediate: true })

function handleInput(): void {
  resizeTextarea()
}
</script>

<template>
  <textarea
    ref="textarea"
    v-model="modelValue"
    data-slot="textarea"
    :class="cn(
      'border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:bg-input/30 flex min-h-16 w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
      props.class,
    )"
    @input="handleInput"
  />
</template>
