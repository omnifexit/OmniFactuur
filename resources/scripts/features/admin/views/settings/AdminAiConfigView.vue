<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useNotificationStore } from '@/scripts/stores/notification.store'
import { aiService } from '@/scripts/api/services/ai.service'
import type { AiConfig, AiDriverOption, AiTestPayload } from '@/scripts/types/ai-config'
import { getErrorTranslationKey, handleApiError } from '@/scripts/utils/error-handling'
import AiConfigurationForm from '@/scripts/features/company/settings/components/AiConfigurationForm.vue'

const { t } = useI18n()
const notificationStore = useNotificationStore()

const isSaving = ref(false)
const isTesting = ref(false)
const isFetchingInitialData = ref(false)
const configData = ref<AiConfig | null>(null)
const drivers = ref<AiDriverOption[]>([])

loadData()

async function loadData(): Promise<void> {
  isFetchingInitialData.value = true
  try {
    const [driversResponse, configResponse] = await Promise.all([
      aiService.getDrivers(),
      aiService.getGlobalConfig(),
    ])
    drivers.value = driversResponse.ai_drivers
    configData.value = configResponse
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

async function saveConfig(value: AiConfig): Promise<void> {
  isSaving.value = true
  try {
    const response = await aiService.saveGlobalConfig(value)
    if (response.success) {
      notificationStore.showNotification({
        type: 'success',
        message: 'settings.ai.saved',
      })
      configData.value = { ...value }
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
    const response = await aiService.testGlobalConnection(payload)
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
    <div v-if="configData" class="mt-14">
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
