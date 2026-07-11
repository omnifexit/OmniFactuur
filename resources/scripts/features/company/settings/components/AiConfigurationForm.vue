<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import useVuelidate from '@vuelidate/core'
import { helpers, required, requiredIf, url as urlValidator } from '@vuelidate/validators'
import type {
  AiConfig,
  AiDriverConfigField,
  AiDriverOption,
} from '@/scripts/types/ai-config'

const props = withDefaults(
  defineProps<{
    configData?: Partial<AiConfig>
    isSaving?: boolean
    isFetchingInitialData?: boolean
    drivers?: AiDriverOption[]
    isTesting?: boolean
  }>(),
  {
    configData: () => ({}),
    isSaving: false,
    isFetchingInitialData: false,
    drivers: () => [],
    isTesting: false,
  },
)

const emit = defineEmits<{
  'submit-data': [config: AiConfig]
  'test-connection': [config: Pick<AiConfig, 'ai_driver' | 'ai_api_key' | 'ai_base_url'>]
}>()

const { t } = useI18n()

const form = reactive<AiConfig>(createDefaults())
const showKey = ref(false)

const selectedDriver = computed<AiDriverOption | undefined>(() =>
  props.drivers.find((d) => d.value === form.ai_driver),
)

const suggestedModels = computed(() => selectedDriver.value?.suggested_models ?? [])
const configFields = computed<AiDriverConfigField[]>(() => selectedDriver.value?.config_fields ?? [])

const isAiOn = computed(() => form.ai_enabled === 'YES')
const isChatOn = computed(() => form.ai_chat_enabled === 'YES')
const isTextGenOn = computed(() => form.ai_text_generation_enabled === 'YES')

const driversList = computed(() =>
  props.drivers.map((d) => ({ value: d.value, label: t(d.label) })),
)

const modelDatalistId = 'ai-model-suggestions'

const rules = computed(() => ({
  ai_driver: {
    required: helpers.withMessage(
      t('validation.required'),
      requiredIf(() => isAiOn.value),
    ),
  },
  ai_api_key: {
    required: helpers.withMessage(
      t('validation.required'),
      requiredIf(() => isAiOn.value),
    ),
  },
  ai_base_url: {
    url: helpers.withMessage(t('validation.invalid_url'), (value: string) => {
      if (!value) return true
      return urlValidator.$validator(value, {} as never, {} as never)
    }),
  },
  ai_chat_model: {
    required: helpers.withMessage(
      t('validation.required'),
      requiredIf(() => isAiOn.value && isChatOn.value),
    ),
  },
  ai_text_generation_model: {
    required: helpers.withMessage(
      t('validation.required'),
      requiredIf(() => isAiOn.value && isTextGenOn.value),
    ),
  },
}))

const v$ = useVuelidate(rules, form)

function createDefaults(): AiConfig {
  return {
    ai_enabled: 'NO',
    ai_driver: 'openrouter',
    ai_api_key: '',
    ai_base_url: '',
    ai_chat_enabled: 'NO',
    ai_chat_model: 'anthropic/claude-sonnet-4.6',
    ai_text_generation_enabled: 'NO',
    ai_text_generation_model: 'anthropic/claude-haiku-4.5',
  }
}

function hydrateFromProps() {
  if (!props.configData) return
  for (const key of Object.keys(form) as Array<keyof AiConfig>) {
    if (props.configData[key] !== undefined && props.configData[key] !== null) {
      ;(form as Record<string, unknown>)[key] = props.configData[key]
    }
  }
}

watch(() => props.configData, hydrateFromProps, { immediate: true, deep: true })

// When the driver changes, fill in the driver-default base_url if the user hasn't provided one.
watch(
  () => form.ai_driver,
  (next) => {
    const driver = props.drivers.find((d) => d.value === next)
    if (driver?.default_base_url && !form.ai_base_url) {
      form.ai_base_url = driver.default_base_url
    }
  },
)

async function onSubmit() {
  const valid = await v$.value.$validate()
  if (!valid) return
  emit('submit-data', { ...form })
}

function onTestConnection() {
  emit('test-connection', {
    ai_driver: form.ai_driver,
    ai_api_key: form.ai_api_key,
    ai_base_url: form.ai_base_url,
  })
}
</script>

<template>
  <form @submit.prevent="onSubmit">
    <!-- Global enable -->
    <div class="mb-8">
      <BaseSwitch
        :model-value="isAiOn"
        class="flex"
        :label-right="$t('settings.ai.enable')"
        @update:model-value="form.ai_enabled = $event ? 'YES' : 'NO'"
      />
      <p class="mt-2 text-xs text-muted">{{ $t('settings.ai.enable_help') }}</p>
    </div>

    <div v-if="isAiOn" class="space-y-6">
      <!-- Provider selection -->
      <BaseInputGroup
        :label="$t('settings.ai.driver')"
        :content-loading="isFetchingInitialData"
        required
        :error="v$.ai_driver.$error && v$.ai_driver.$errors[0]?.$message"
      >
        <BaseMultiselect
          v-model="form.ai_driver"
          :options="driversList"
          :content-loading="isFetchingInitialData"
          value-prop="value"
          label="label"
          track-by="label"
          :can-deselect="false"
          :invalid="v$.ai_driver.$error"
        />
      </BaseInputGroup>

      <!-- API key -->
      <BaseInputGroup
        :label="$t('settings.ai.api_key')"
        :content-loading="isFetchingInitialData"
        :help-text="$t('settings.ai.api_key_help')"
        required
        :error="v$.ai_api_key.$error && v$.ai_api_key.$errors[0]?.$message"
      >
        <div class="flex gap-2">
          <BaseInput
            v-model="form.ai_api_key"
            :content-loading="isFetchingInitialData"
            :type="showKey ? 'text' : 'password'"
            class="flex-1"
            name="ai_api_key"
            :invalid="v$.ai_api_key.$error"
          />
          <BaseButton
            type="button"
            variant="primary-outline"
            @click="showKey = !showKey"
          >
            {{ showKey ? $t('general.hide') : $t('general.show') }}
          </BaseButton>
        </div>
      </BaseInputGroup>

      <!-- Driver-specific config fields (base_url for OpenRouter, etc.) -->
      <BaseInputGroup
        v-for="field in configFields"
        :key="field.key"
        :label="$t(field.label)"
        :content-loading="isFetchingInitialData"
      >
        <BaseInput
          v-if="field.type === 'text'"
          :model-value="(form as unknown as Record<string, string>)[`ai_${field.key}`] ?? ''"
          :placeholder="field.default"
          type="text"
          :name="`ai_${field.key}`"
          @update:model-value="(val: string) => ((form as unknown as Record<string, string>)[`ai_${field.key}`] = val)"
        />
      </BaseInputGroup>

      <!-- Role: chat -->
      <div class="border-t border-line-default pt-6">
        <h3 class="text-sm font-semibold text-heading mb-3">{{ $t('settings.ai.roles') }}</h3>
        <p class="text-xs text-muted mb-4">{{ $t('settings.ai.roles_help') }}</p>

        <div class="mb-6">
          <BaseSwitch
            :model-value="isChatOn"
            class="flex"
            :label-right="$t('settings.ai.chat')"
            @update:model-value="form.ai_chat_enabled = $event ? 'YES' : 'NO'"
          />
          <p class="mt-2 text-xs text-muted">{{ $t('settings.ai.chat_help') }}</p>

          <BaseInputGroup
            v-if="isChatOn"
            class="mt-3"
            :label="$t('settings.ai.chat_model')"
            required
            :error="v$.ai_chat_model.$error && v$.ai_chat_model.$errors[0]?.$message"
          >
            <BaseInput
              v-model="form.ai_chat_model"
              type="text"
              :list="modelDatalistId"
              :invalid="v$.ai_chat_model.$error"
            />
          </BaseInputGroup>
        </div>

        <!-- Role: text generation -->
        <div>
          <BaseSwitch
            :model-value="isTextGenOn"
            class="flex"
            :label-right="$t('settings.ai.text_generation')"
            @update:model-value="form.ai_text_generation_enabled = $event ? 'YES' : 'NO'"
          />
          <p class="mt-2 text-xs text-muted">{{ $t('settings.ai.text_generation_help') }}</p>

          <BaseInputGroup
            v-if="isTextGenOn"
            class="mt-3"
            :label="$t('settings.ai.text_generation_model')"
            required
            :error="
              v$.ai_text_generation_model.$error &&
              v$.ai_text_generation_model.$errors[0]?.$message
            "
          >
            <BaseInput
              v-model="form.ai_text_generation_model"
              type="text"
              :list="modelDatalistId"
              :invalid="v$.ai_text_generation_model.$error"
            />
          </BaseInputGroup>
        </div>

        <!-- Datalist with suggested models for both inputs -->
        <datalist :id="modelDatalistId">
          <option
            v-for="model in suggestedModels"
            :key="model.value"
            :value="model.value"
          >
            {{ model.label }}
          </option>
        </datalist>
      </div>
    </div>

    <!-- Actions -->
    <div class="flex items-center gap-3 mt-8">
      <BaseButton
        :loading="isSaving"
        :disabled="isSaving"
        variant="primary"
        type="submit"
      >
        <template #left="slotProps">
          <BaseIcon v-if="!isSaving" name="ArrowDownOnSquareIcon" :class="slotProps.class" />
        </template>
        {{ $t('general.save') }}
      </BaseButton>

      <BaseButton
        v-if="isAiOn"
        :loading="isTesting"
        :disabled="isTesting || isSaving"
        variant="primary-outline"
        type="button"
        @click="onTestConnection"
      >
        {{ $t('settings.ai.test_connection') }}
      </BaseButton>
    </div>
  </form>
</template>
