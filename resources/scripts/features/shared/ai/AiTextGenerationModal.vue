<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useModalStore } from '@/scripts/stores/modal.store'
import { useNotificationStore } from '@/scripts/stores/notification.store'
import { aiService } from '@/scripts/api/services/ai.service'

/**
 * One-shot text generation popup for WYSIWYG editors.
 *
 * Usage pattern — a caller opens this modal via modalStore and passes data with:
 *   - currentContent: string           // the editor's current HTML (used as optional context)
 *   - onInsert: (text: string) => void // invoked when the user accepts "Insert"
 *   - onReplace: (text: string) => void // invoked when the user accepts "Replace"
 *
 * See RichEditor.vue for the canonical caller. The modal doesn't know
 * anything about tiptap or ProseMirror — it just hands back the text it got
 * from the backend and lets the caller decide how to splice it into the editor.
 */

interface ModalData {
  currentContent?: string
  onInsert?: (text: string) => void
  onReplace?: (text: string) => void
}

const { t } = useI18n()
const modalStore = useModalStore()
const notificationStore = useNotificationStore()

const modalActive = computed<boolean>(
  () => modalStore.active && modalStore.componentName === 'AiTextGenerationModal',
)

const data = computed<ModalData>(() => (modalStore.data as ModalData) ?? {})

const prompt = ref<string>('')
const useContext = ref<boolean>(false)
const generatedText = ref<string>('')
const isGenerating = ref<boolean>(false)

const canInsert = computed<boolean>(() => generatedText.value.trim() !== '')

async function generate(): Promise<void> {
  if (!prompt.value.trim() || isGenerating.value) return

  isGenerating.value = true
  generatedText.value = ''

  try {
    const response = await aiService.generateText({
      prompt: prompt.value,
      context: useContext.value ? data.value.currentContent : undefined,
    })

    if (response.text !== undefined) {
      generatedText.value = response.text
    } else if (response.error) {
      notificationStore.showNotification({
        type: 'error',
        message: t('settings.ai.errors.' + response.error, { error: response.message ?? '' }),
      })
    }
  } catch (err: unknown) {
    const message = err instanceof Error ? err.message : 'Unknown error'
    notificationStore.showNotification({ type: 'error', message })
  } finally {
    isGenerating.value = false
  }
}

function insert(): void {
  data.value.onInsert?.(generatedText.value)
  close()
}

function replace(): void {
  data.value.onReplace?.(generatedText.value)
  close()
}

function close(): void {
  modalStore.closeModal()
  setTimeout(() => {
    prompt.value = ''
    generatedText.value = ''
    useContext.value = false
  }, 200)
}
</script>

<template>
  <BaseModal :show="modalActive" @close="close">
    <template #header>
      <div class="flex items-center justify-between w-full">
        <div class="flex items-center gap-2">
          <BaseIcon name="SparklesIcon" class="w-5 h-5 text-primary-500" />
          <span>{{ $t('ai.generate.title') }}</span>
        </div>
        <BaseIcon
          name="XMarkIcon"
          class="w-6 h-6 text-muted cursor-pointer"
          @click="close"
        />
      </div>
    </template>

    <div class="p-6 space-y-4">
      <BaseInputGroup
        :label="$t('ai.generate.prompt_label')"
        :content-loading="false"
        required
      >
        <BaseTextarea
          v-model="prompt"
          rows="3"
          :placeholder="$t('ai.generate.prompt_placeholder')"
          :disabled="isGenerating"
        />
      </BaseInputGroup>

      <div v-if="data.currentContent">
        <BaseSwitch
          v-model="useContext"
          class="flex"
          :label-right="$t('ai.generate.use_current_as_context')"
        />
        <p class="mt-1 text-xs text-muted">
          {{ $t('ai.generate.use_context_help') }}
        </p>
      </div>

      <div v-if="generatedText" class="border border-line-default rounded-md p-3 bg-surface-secondary">
        <p class="text-xs text-muted mb-2">{{ $t('ai.generate.preview') }}</p>
        <p class="text-sm text-body whitespace-pre-wrap">{{ generatedText }}</p>
      </div>
    </div>

    <div class="flex justify-end gap-2 p-4 border-t border-line-default">
      <BaseButton
        variant="primary-outline"
        type="button"
        :disabled="isGenerating"
        @click="close"
      >
        {{ $t('general.cancel') }}
      </BaseButton>

      <BaseButton
        v-if="canInsert"
        variant="primary-outline"
        type="button"
        :disabled="isGenerating"
        @click="replace"
      >
        {{ $t('ai.generate.replace') }}
      </BaseButton>

      <BaseButton
        v-if="canInsert"
        variant="primary-outline"
        type="button"
        :disabled="isGenerating"
        @click="generate"
      >
        {{ $t('ai.generate.regenerate') }}
      </BaseButton>

      <BaseButton
        v-if="canInsert"
        variant="primary"
        type="button"
        :disabled="isGenerating"
        @click="insert"
      >
        {{ $t('ai.generate.insert') }}
      </BaseButton>

      <BaseButton
        v-else
        variant="primary"
        type="button"
        :loading="isGenerating"
        :disabled="!prompt.trim() || isGenerating"
        @click="generate"
      >
        <template #left="slotProps">
          <BaseIcon v-if="!isGenerating" name="SparklesIcon" :class="slotProps.class" />
        </template>
        {{ $t('ai.generate.generate') }}
      </BaseButton>
    </div>
  </BaseModal>
</template>
