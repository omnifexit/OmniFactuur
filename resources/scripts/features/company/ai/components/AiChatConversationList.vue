<script setup lang="ts">
import { useAiChatStore } from '../stores/ai-chat.store'
import type { AiConversationSummary } from '@/scripts/types/ai-config'

const store = useAiChatStore()

async function select(convo: AiConversationSummary): Promise<void> {
  await store.loadConversation(convo.id)
}

async function remove(convo: AiConversationSummary, event: MouseEvent): Promise<void> {
  event.stopPropagation()
  if (!window.confirm('Delete this conversation?')) return
  await store.deleteConversation(convo.id)
}
</script>

<template>
  <div class="flex flex-col h-full">
    <div class="h-12 px-3 border-b border-line-default flex items-center">
      <button
        type="button"
        class="w-full text-center text-xs font-medium rounded px-2 py-1 bg-btn-primary text-white hover:bg-btn-primary-hover"
        @click="store.newConversation()"
      >
        + {{ $t('ai.chat.new_conversation') }}
      </button>
    </div>

    <div class="flex-1 overflow-y-auto">
      <div
        v-if="store.isLoadingConversations && store.conversations.length === 0"
        class="p-3 text-xs text-muted"
      >
        {{ $t('general.loading') }}...
      </div>

      <div
        v-else-if="store.conversations.length === 0"
        class="p-3 text-xs text-muted"
      >
        {{ $t('ai.chat.no_conversations') }}
      </div>

      <ul v-else class="space-y-1 p-2">
        <li
          v-for="convo in store.conversations"
          :key="convo.id"
        >
          <button
            type="button"
            class="
              w-full text-left flex items-center justify-between
              px-3 py-2 rounded text-sm group
              hover:bg-hover
            "
            :class="{
              'bg-hover-strong font-semibold': store.currentConversationId === convo.id,
            }"
            @click="select(convo)"
          >
            <span class="truncate text-body">
              {{ convo.title ?? $t('ai.chat.untitled') }}
            </span>
            <span
              class="
                ml-2 text-xs text-muted opacity-0 group-hover:opacity-100
                hover:text-alert-error-text
              "
              @click="remove(convo, $event)"
            >
              {{ $t('general.delete') }}
            </span>
          </button>
        </li>
      </ul>
    </div>
  </div>
</template>
