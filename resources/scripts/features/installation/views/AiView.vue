<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { installClient } from '@/scripts/api/install-client'
import type {
  AiConfig,
  AiDriverOption,
  AiDriversResponse,
} from '@/scripts/types/ai-config'
import AiConfigurationForm from '@/scripts/features/company/settings/components/AiConfigurationForm.vue'
import { useInstallationFeedback } from '../use-installation-feedback'

const router = useRouter()
const { isSuccessfulResponse, showRequestError, showResponseError } = useInstallationFeedback()

const isSaving = ref(false)
const isFetchingInitialData = ref(false)
const configData = ref<AiConfig | null>(null)
const drivers = ref<AiDriverOption[]>([])

onMounted(loadData)

async function loadData(): Promise<void> {
  isFetchingInitialData.value = true
  try {
    const { data } = await installClient.get<{
      config: AiConfig
      drivers: AiDriversResponse['ai_drivers']
    }>('/api/v1/installation/ai/config')
    configData.value = data.config
    drivers.value = data.drivers
  } catch (error: unknown) {
    showRequestError(error)
  } finally {
    isFetchingInitialData.value = false
  }
}

async function saveAi(value: AiConfig): Promise<void> {
  isSaving.value = true
  try {
    const { data } = await installClient.post('/api/v1/installation/ai/config', value)

    if (!isSuccessfulResponse(data)) {
      showResponseError(data)
      return
    }

    await router.push({ name: 'installation.account' })
  } catch (error: unknown) {
    showRequestError(error)
  } finally {
    isSaving.value = false
  }
}

async function skipStep(): Promise<void> {
  // Persist the disabled default so bootstrap sees an explicit ai_enabled=NO
  // (rather than a missing key that defaults to NO anyway — we want the value
  // in storage so tests / repeated installer runs behave predictably).
  await saveAi({
    ai_enabled: 'NO',
    ai_driver: 'openrouter',
    ai_api_key: '',
    ai_base_url: '',
    ai_chat_enabled: 'NO',
    ai_chat_model: '',
    ai_text_generation_enabled: 'NO',
    ai_text_generation_model: '',
  })
}
</script>

<template>
  <BaseWizardStep
    :title="$t('settings.ai.installer_title')"
    :description="$t('settings.ai.installer_description')"
  >
    <div v-if="configData">
      <AiConfigurationForm
        :config-data="configData"
        :drivers="drivers"
        :is-saving="isSaving"
        :is-fetching-initial-data="isFetchingInitialData"
        @submit-data="saveAi"
      />

      <div class="mt-6">
        <BaseButton
          variant="primary-outline"
          type="button"
          :disabled="isSaving"
          @click="skipStep"
        >
          {{ $t('general.skip') }}
        </BaseButton>
      </div>
    </div>
  </BaseWizardStep>
</template>
