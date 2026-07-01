<template>
  <div class="p-6">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold">Startup Configuration</h1>
      <button
        @click="saveStartup"
        :disabled="loading"
        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-600 disabled:cursor-not-allowed rounded-lg transition-colors"
      >
        Save Changes
      </button>
    </div>

    <div v-if="loading && !startupConfig" class="text-center py-8 text-gray-400">
      Loading startup configuration...
    </div>

    <div v-else-if="error" class="text-center py-8 text-red-400">
      {{ error }}
    </div>

    <div v-else class="space-y-6">
      <div class="bg-gray-800 rounded-lg p-6">
        <h2 class="text-xl font-semibold mb-4">Startup Command</h2>
        <div class="bg-gray-900 rounded p-4 font-mono text-sm text-gray-300">
          {{ startupConfig.startup_command }}
        </div>
      </div>

      <div class="bg-gray-800 rounded-lg p-6">
        <h2 class="text-xl font-semibold mb-4">Variables</h2>
        
        <div class="space-y-4">
          <div
            v-for="variable in startupConfig.variables"
            :key="variable.env_variable"
            class="border border-gray-700 rounded-lg p-4"
          >
            <div class="flex items-start justify-between mb-2">
              <div class="flex-1">
                <h3 class="font-semibold text-gray-100">{{ variable.name }}</h3>
                <p class="text-sm text-gray-400 mt-1">{{ variable.description }}</p>
              </div>
              <span class="text-xs px-2 py-1 bg-gray-700 rounded">
                {{ variable.env_variable }}
              </span>
            </div>

            <div class="mt-3">
              <label class="block text-sm font-medium text-gray-300 mb-2">Value</label>
              <input
                v-model="variable.server_value"
                :disabled="!variable.is_editable"
                type="text"
                :placeholder="variable.default_value"
                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
              />
              <p v-if="variable.rules" class="text-xs text-gray-400 mt-1">
                Rules: {{ variable.rules }}
              </p>
            </div>
          </div>

          <div v-if="startupConfig.variables.length === 0" class="text-center py-8 text-gray-400">
            No variables configured
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useApichanAPI } from '@/composables/useApichanAPI'
import { useToast } from 'vue-toastification'

const props = defineProps<{
  remoteServerId: string
}>()

const api = useApichanAPI()
const toast = useToast()

const loading = ref(false)
const error = ref<string | null>(null)
const startupConfig = ref<any>({
  startup_command: '',
  variables: []
})

const fetchStartup = async () => {
  loading.value = true
  error.value = null
  try {
    const response = await api.remoteGetStartup(parseInt(props.remoteServerId))
    startupConfig.value = response
  } catch (e: any) {
    error.value = e.message || 'Failed to fetch startup configuration'
  } finally {
    loading.value = false
  }
}

const saveStartup = async () => {
  loading.value = true
  error.value = null
  try {
    for (const variable of startupConfig.value.variables) {
      if (variable.is_editable) {
        await api.remoteUpdateStartupVariable(
          parseInt(props.remoteServerId),
          variable.env_variable,
          variable.server_value
        )
      }
    }
    toast.success('Startup configuration updated')
  } catch (e: any) {
    error.value = e.message || 'Failed to update startup configuration'
    toast.error(error.value)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchStartup()
})
</script>
