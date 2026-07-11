<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useNotificationStore } from '@/scripts/stores/notification.store'
import { aiService } from '@/scripts/api/services/ai.service'
import type {
  AiConfig,
  AiDriverOption,
  AiTestPayload,
  CompanyAiConfig,
} from '@/scripts/types/ai-config'
import { getErrorTranslationKey, handleApiError } from '@/scripts/utils/error-handling'
import AiConfigurationForm from '@/scripts/features/company/settings/components/AiConfigurationForm.vue'

const { t } = useI18n()
const notificationStore = useNotificationStore()

const isSaving = ref(false)
const isTesting = ref(false)
const isFetchingInitialData = ref(false)
const useCustomAiConfig = ref(false)
const configData = ref<CompanyAiConfig | null>(null)
const drivers = ref<AiDriverOption[]>([])

loadData()

async function loadData(): Promise<void> {
  isFetchingInitialData.value = true
  try {
    const [driversResponse, configResponse] = await Promise.all([
      aiService.getDrivers(),
      aiService.getCompanyConfig(),
    ])
    drivers.value = driversResponse.ai_drivers
    configData.value = configResponse
    useCustomAiConfig.value = configResponse.use_custom_ai_config === 'YES'
  } catch (error: unknown) {
    const normalizedError = handleApiError(error)
    notificationStore.showNotification({
      type: 'error',
      message: getErrorTranslationKey(normalizedError.message) ?? normalizedError.message,
    })
  } finally {
    isFetchingInitialData.value = false
  }
}

// Mirror the mail pattern: flipping the toggle OFF auto-saves and discards driver fields.
watch(useCustomAiConfig, async (next, prev) => {
  if (prev === undefined) return
  if (next) return // ON — wait for explicit save

  isSaving.value = true
  try {
    await aiService.saveCompanyConfig({
      use_custom_ai_config: 'NO',
    } as CompanyAiConfig)

    if (configData.value) {
      configData.value.use_custom_ai_config = 'NO'
    }

    notificationStore.showNotification({
      type: 'success',
      message: 'settings.ai.saved',
    })
  } catch (error: unknown) {
    const normalizedError = handleApiError(error)
    notificationStore.showNotification({
      type: 'error',
      message: getErrorTranslationKey(normalizedError.message) ?? normalizedError.message,
    })
    useCustomAiConfig.value = true // revert the toggle
  } finally {
    isSaving.value = false
  }
})

async function saveConfig(value: AiConfig): Promise<void> {
  isSaving.value = true
  try {
    const payload: CompanyAiConfig = {
      ...value,
      use_custom_ai_config: 'YES',
    }

    const response = await aiService.saveCompanyConfig(payload)
    if (response.success) {
      notificationStore.showNotification({
        type: 'success',
        message: 'settings.ai.saved',
      })
      configData.value = payload
    }
  } catch (error: unknown) {
    const normalizedError = handleApiError(error)
    notificationStore.showNotification({
      type: 'error',
      message: getErrorTranslationKey(normalizedError.message) ?? normalizedError.message,
    })
  } finally {
    isSaving.value = false
  }
}

async function testConnection(payload: AiTestPayload): Promise<void> {
  isTesting.value = true
  try {
    const response = await aiService.testCompanyConnection(payload)
    if (response.success) {
      notificationStore.showNotification({
        type: 'success',
        message: 'settings.ai.test_success',
      })
    } else if (response.error) {
      notificationStore.showNotification({
        type: 'error',
        message: t('settings.ai.errors.' + response.error, { error: response.message ?? '' }),
      })
    }
  } catch (error: unknown) {
    const normalizedError = handleApiError(error)
    notificationStore.showNotification({
      type: 'error',
      message: getErrorTranslationKey(normalizedError.message) ?? normalizedError.message,
    })
  } finally {
    isTesting.value = false
  }
}
</script>

<template>
  <BaseSettingCard
    :title="$t('settings.ai.title')"
    :description="$t('settings.ai.description')"
  >
    <div class="mt-8">
      <BaseSwitchSection
        v-model="useCustomAiConfig"
        :title="$t('settings.ai.use_custom_ai_config')"
        :description="$t('settings.ai.use_custom_ai_config_desc')"
      />
    </div>

    <div
      v-if="!useCustomAiConfig"
      class="mt-6 p-4 rounded bg-alert-success-bg text-alert-success-text text-sm"
    >
      {{ $t('settings.ai.using_global_ai_config') }}
    </div>

    <div v-if="useCustomAiConfig && configData" class="mt-8">
      <AiConfigurationForm
        :config-data="configData"
        :drivers="drivers"
        :is-saving="isSaving"
        :is-testing="isTesting"
        :is-fetching-initial-data="isFetchingInitialData"
        @submit-data="saveConfig"
        @test-connection="testConnection"
      />
    </div>
  </BaseSettingCard>
</template>
