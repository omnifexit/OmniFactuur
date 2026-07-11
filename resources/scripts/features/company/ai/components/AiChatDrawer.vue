<script setup lang="ts">
import { nextTick, ref, watch } from 'vue'
import { useAiChatStore } from '../stores/ai-chat.store'
import AiChatMessage from './AiChatMessage.vue'
import AiChatMessageInput from './AiChatMessageInput.vue'
import AiChatConversationList from './AiChatConversationList.vue'

const store = useAiChatStore()

const messagesEl = ref<HTMLDivElement | null>(null)

// Auto-scroll to the bottom whenever the message list grows.
watch(
  () => store.messages.length,
  async () => {
    await nextTick()
    if (messagesEl.value) {
      messagesEl.value.scrollTop = messagesEl.value.scrollHeight
    }
  },
)

async function onSend(message: string): Promise<void> {
  await store.sendMessage(message)
  await nextTick()
  if (messagesEl.value) {
    messagesEl.value.scrollTop = messagesEl.value.scrollHeight
  }
}
</script>

<template>
  <!-- Backdrop -->
  <Teleport to="body">
    <transition name="ai-drawer-fade">
      <div
        v-if="store.isOpen"
        class="fixed inset-0 bg-black/20 z-40"
        @click="store.close()"
      />
    </transition>

    <!-- Drawer panel -->
    <transition name="ai-drawer-slide">
      <aside
        v-if="store.isOpen"
        class="
          fixed top-0 right-0 bottom-0 z-50
          w-full sm:w-[480px] lg:w-[640px]
          bg-surface shadow-2xl
          flex
        "
      >
        <!-- Conversation list sidebar -->
        <div class="hidden sm:block w-48 border-r border-line-default bg-surface-secondary">
          <AiChatConversationList />
        </div>

        <!-- Messages + input -->
        <div class="flex-1 flex flex-col">
          <div class="h-12 flex items-center justify-between px-3 border-b border-line-default">
            <div class="flex items-center gap-2">
              <BaseIcon name="SparklesIcon" class="w-5 h-5 text-primary-500" />
              <h2 class="text-sm font-semibold text-heading">
                {{ $t('ai.chat.title') }}
              </h2>
            </div>
            <button
              type="button"
              class="text-muted hover:text-heading"
              @click="store.close()"
            >
              <BaseIcon name="XMarkIcon" class="w-5 h-5" />
            </button>
          </div>

          <div
            ref="messagesEl"
            class="flex-1 overflow-y-auto p-4 space-y-3"
          >
            <div
              v-if="store.messages.length === 0"
              class="text-center text-sm text-muted mt-12"
            >
              <BaseIcon name="SparklesIcon" class="w-10 h-10 mx-auto mb-2 text-subtle" />
              <p>{{ $t('ai.chat.empty_state') }}</p>
            </div>

            <AiChatMessage
              v-for="msg in store.messages"
              :key="msg.id"
              :message="msg"
            />

            <div
              v-if="store.isSending"
              class="flex justify-start"
            >
              <div class="bg-surface-tertiary rounded-lg px-4 py-2 text-sm text-muted italic">
                {{ $t('ai.chat.thinking') }}
              </div>
            </div>

            <div
              v-if="store.lastError"
              class="p-3 text-xs text-alert-error-text bg-alert-error-bg rounded"
            >
              {{ store.lastError }}
            </div>
          </div>

          <AiChatMessageInput
            :is-sending="store.isSending"
            @send="onSend"
          />
        </div>
      </aside>
    </transition>
  </Teleport>
</template>

<style scoped>
.ai-drawer-fade-enter-active,
.ai-drawer-fade-leave-active {
  transition: opacity 0.2s ease;
}
.ai-drawer-fade-enter-from,
.ai-drawer-fade-leave-to {
  opacity: 0;
}

.ai-drawer-slide-enter-active,
.ai-drawer-slide-leave-active {
  transition: transform 0.25s ease;
}
.ai-drawer-slide-enter-from,
.ai-drawer-slide-leave-to {
  transform: translateX(100%);
}
</style>
