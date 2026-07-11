import { client } from '../client'
import { API } from '../endpoints'
import type {
  AiChatSendResponse,
  AiConfig,
  AiConversationDetail,
  AiConversationSummary,
  AiDriversResponse,
  AiGenerateRequest,
  AiGenerateResponse,
  AiTestPayload,
  AiTestResponse,
  CompanyAiConfig,
} from '@/scripts/types/ai-config'

export const aiService = {
  // Driver catalog — same shape across admin, company, installer contexts.
  async getDrivers(): Promise<AiDriversResponse> {
    const { data } = await client.get(API.AI_DRIVERS)
    return data
  },

  // --- Global (admin) ---

  async getGlobalConfig(): Promise<AiConfig> {
    const { data } = await client.get(API.AI_CONFIG)
    return data
  },

  async saveGlobalConfig(payload: AiConfig): Promise<{ success?: string; error?: string }> {
    const { data } = await client.post(API.AI_CONFIG, payload)
    return data
  },

  async testGlobalConnection(payload: AiTestPayload): Promise<AiTestResponse> {
    const { data } = await client.post(API.AI_TEST, payload)
    return data
  },

  // --- Per-company ---

  async getCompanyConfig(): Promise<CompanyAiConfig> {
    const { data } = await client.get(API.COMPANY_AI_CONFIG)
    return data
  },

  async saveCompanyConfig(payload: CompanyAiConfig): Promise<{ success?: boolean; error?: string }> {
    const { data } = await client.post(API.COMPANY_AI_CONFIG, payload)
    return data
  },

  async testCompanyConnection(payload: AiTestPayload): Promise<AiTestResponse> {
    const { data } = await client.post(API.COMPANY_AI_TEST, payload)
    return data
  },

  // --- Phase 2: chat ---

  async sendChatMessage(
    conversationId: number | null,
    message: string,
  ): Promise<AiChatSendResponse> {
    const { data } = await client.post(API.AI_CHAT, {
      conversation_id: conversationId,
      message,
    })
    return data
  },

  async listConversations(): Promise<{ conversations: AiConversationSummary[] }> {
    const { data } = await client.get(API.AI_CONVERSATIONS)
    return data
  },

  async getConversation(id: number): Promise<AiConversationDetail> {
    const { data } = await client.get(`${API.AI_CONVERSATIONS}/${id}`)
    return data
  },

  async renameConversation(id: number, title: string): Promise<{ success: boolean }> {
    const { data } = await client.patch(`${API.AI_CONVERSATIONS}/${id}`, { title })
    return data
  },

  async deleteConversation(id: number): Promise<{ success: boolean }> {
    const { data } = await client.delete(`${API.AI_CONVERSATIONS}/${id}`)
    return data
  },

  // --- Phase 3: text generation ---

  async generateText(payload: AiGenerateRequest): Promise<AiGenerateResponse> {
    const { data } = await client.post(API.AI_GENERATE, payload)
    return data
  },
}
