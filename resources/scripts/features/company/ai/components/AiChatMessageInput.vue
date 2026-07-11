<script setup lang="ts">
import { ref } from 'vue'

const props = defineProps<{
  isSending?: boolean
}>()

const emit = defineEmits<{
  send: [message: string]
}>()

const text = ref<string>('')

function submit(): void {
  const trimmed = text.value.trim()
  if (!trimmed || props.isSending) return

  emit('send', trimmed)
  text.value = ''
}

/**
 * Shift+Enter → newline, Enter alone → submit (standard chat UX).
 */
function onKeydown(e: KeyboardEvent): void {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault()
    submit()
  }
}
</script>

<template>
  <form
    class="border-t border-line-default p-3 flex items-end gap-2"
    @submit.prevent="submit"
  >
    <textarea
      v-model="text"
      rows="2"
      class="
        flex-1 resize-none rounded-md border border-line-default
        bg-surface text-body text-sm px-3 py-2
        focus:outline-none focus:ring-1 focus:ring-primary-500
      "
      :placeholder="$t('ai.chat.input_placeholder')"
      :disabled="isSending"
      @keydown="onKeydown"
    />
    <button
      type="submit"
      class="
        rounded-md px-3 py-2 text-sm font-medium
        bg-btn-primary text-white hover:bg-btn-primary-hover
        disabled:opacity-50 disabled:cursor-not-allowed
      "
      :disabled="!text.trim() || isSending"
    >
      {{ isSending ? $t('ai.chat.sending') : $t('ai.chat.send') }}
    </button>
  </form>
</template>
