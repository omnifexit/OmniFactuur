import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { aiService } from '@/scripts/api/services/ai.service'
import type {
  AiChatMessage,
  AiConversationSummary,
} from '@/scripts/types/ai-config'

/**
 * Chat drawer state + conversation history.
 *
 * The drawer is a global overlay (not a route), so this store is where its
 * open/closed state, current conversation, message list, and loading state all
 * live. Message-sending is persisted server-side — we don't keep optimistic
 * state across reloads.
 */
export const useAiChatStore = defineStore('ai-chat', () => {
  // --- Drawer UI state ---
  const isOpen = ref<boolean>(false)

  // --- Current conversation ---
  const currentConversationId = ref<number | null>(null)
  const messages = ref<AiChatMessage[]>([])
  const isSending = ref<boolean>(false)
  const lastError = ref<string | null>(null)

  // --- Conversation list (sidebar inside the drawer) ---
  const conversations = ref<AiConversationSummary[]>([])
  const isLoadingConversations = ref<boolean>(false)

  const hasActiveConversation = computed<boolean>(() => currentConversationId.value !== null)

  // --- Actions ---

  function open(): void {
    isOpen.value = true
    // Refresh the sidebar list on open so the user sees any new conversations
    // they started in another tab.
    void refreshConversations()
  }

  function close(): void {
    isOpen.value = false
  }

  function toggle(): void {
    isOpen.value ? close() : open()
  }

  function newConversation(): void {
    currentConversationId.value = null
    messages.value = []
    lastError.value = null
  }

  async function refreshConversations(): Promise<void> {
    isLoadingConversations.value = true
    try {
      const response = await aiService.listConversations()
      conversations.value = response.conversations
    } catch {
      // silent — the drawer stays functional without the sidebar list
    } finally {
      isLoadingConversations.value = false
    }
  }

  async function loadConversation(id: number): Promise<void> {
    lastError.value = null
    const response = await aiService.getConversation(id)
    currentConversationId.value = response.conversation.id
    messages.value = response.messages
  }

  async function sendMessage(text: string): Promise<void> {
    if (!text.trim()) return
    if (isSending.value) return

    lastError.value = null
    isSending.value = true

    // Optimistic local append so the user sees their message immediately.
    const optimistic: AiChatMessage = {
      id: Date.now() * -1,
      role: 'user',
      content: text,
      created_at: new Date().toISOString(),
    }
    messages.value.push(optimistic)

    try {
      const response = await aiService.sendChatMessage(currentConversationId.value, text)

      // The backend may have started a new conversation for us.
      currentConversationId.value = response.conversation.id
      messages.value.push(response.message)

      // Refresh the sidebar so the new/updated conversation bubbles to the top.
      void refreshConversations()
    } catch (err) {
      // Roll back the optimistic message so the user can retry.
      messages.value = messages.value.filter((m) => m.id !== optimistic.id)

      const message = err instanceof Error ? err.message : 'Unknown error'
      lastError.value = message
    } finally {
      isSending.value = false
    }
  }

  async function deleteConversation(id: number): Promise<void> {
    await aiService.deleteConversation(id)
    conversations.value = conversations.value.filter((c) => c.id !== id)

    // If the deleted conversation is the one currently shown, start fresh.
    if (currentConversationId.value === id) {
      newConversation()
    }
  }

  async function renameConversation(id: number, title: string): Promise<void> {
    await aiService.renameConversation(id, title)
    const existing = conversations.value.find((c) => c.id === id)
    if (existing) existing.title = title
  }

  return {
    // state
    isOpen,
    currentConversationId,
    messages,
    isSending,
    lastError,
    conversations,
    isLoadingConversations,
    // getters
    hasActiveConversation,
    // actions
    open,
    close,
    toggle,
    newConversation,
    refreshConversations,
    loadConversation,
    sendMessage,
    deleteConversation,
    renameConversation,
  }
})
