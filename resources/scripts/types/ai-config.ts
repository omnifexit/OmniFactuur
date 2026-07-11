export interface AiSuggestedModel {
  value: string
  label: string
}

export interface AiDriverConfigField {
  key: string
  type: 'text' | 'select'
  label: string
  default?: string
  options?: Array<{ label: string; value: string }>
  visible_when?: Record<string, string>
}

export interface AiDriverOption {
  value: string
  label: string
  website: string
  default_base_url: string
  supported_roles: string[]
  suggested_models: AiSuggestedModel[]
  config_fields: AiDriverConfigField[]
}

export interface AiDriversResponse {
  ai_drivers: AiDriverOption[]
}

export interface AiConfig {
  ai_enabled: 'YES' | 'NO'
  ai_driver: string
  ai_api_key: string
  ai_base_url: string
  ai_chat_enabled: 'YES' | 'NO'
  ai_chat_model: string
  ai_text_generation_enabled: 'YES' | 'NO'
  ai_text_generation_model: string
}

export interface CompanyAiConfig extends AiConfig {
  use_custom_ai_config: 'YES' | 'NO'
}

export interface AiTestPayload {
  ai_driver: string
  ai_api_key?: string
  ai_base_url?: string
}

export interface AiTestResponse {
  success?: boolean
  error?: string
  message?: string
  details?: Record<string, unknown>
}

// --- Phase 2: chat types ---

export interface AiConversationSummary {
  id: number
  title: string | null
  model: string | null
  created_at: string
  updated_at: string
}

export interface AiChatMessage {
  id: number
  role: 'user' | 'assistant'
  content: string | null
  created_at: string
}

export interface AiChatSendResponse {
  conversation: AiConversationSummary
  message: AiChatMessage
  error?: string
}

export interface AiConversationDetail {
  conversation: AiConversationSummary
  messages: AiChatMessage[]
}

// --- Phase 3: text generation types ---

export interface AiGenerateRequest {
  prompt: string
  context?: string
}

export interface AiGenerateResponse {
  text?: string
  error?: string
  message?: string
}
