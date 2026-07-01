<template>
  <div class="p-6">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold">Server Settings</h1>
      <button
        @click="saveSettings"
        :disabled="loading"
        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-600 disabled:cursor-not-allowed rounded-lg transition-colors"
      >
        Save Changes
      </button>
    </div>

    <div v-if="loading && !settings" class="text-center py-8 text-gray-400">
      Loading settings...
    </div>

    <div v-else-if="error" class="text-center py-8 text-red-400">
      {{ error }}
    </div>

    <div v-else class="space-y-6">
      <div class="bg-gray-800 rounded-lg p-6">
        <h2 class="text-xl font-semibold mb-4">General</h2>
        
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Server Name</label>
            <input
              v-model="settings.name"
              type="text"
              class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Description</label>
            <textarea
              v-model="settings.description"
              rows="3"
              class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
        </div>
      </div>

      <div class="bg-gray-800 rounded-lg p-6">
        <h2 class="text-xl font-semibold mb-4">Resource Limits</h2>
        
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Memory (MB)</label>
            <input
              v-model.number="settings.limits.memory"
              type="number"
              class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
              readonly
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Disk (MB)</label>
            <input
              v-model.number="settings.limits.disk"
              type="number"
              class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
              readonly
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">CPU (%)</label>
            <input
              v-model.number="settings.limits.cpu"
              type="number"
              class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
              readonly
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Swap (MB)</label>
            <input
              v-model.number="settings.limits.swap"
              type="number"
              class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
              readonly
            />
          </div>
        </div>
        <p class="text-xs text-gray-400 mt-2">Resource limits are read-only</p>
      </div>

      <div class="bg-gray-800 rounded-lg p-6">
        <h2 class="text-xl font-semibold mb-4">Feature Limits</h2>
        
        <div class="grid grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Databases</label>
            <input
              v-model.number="settings.feature_limits.databases"
              type="number"
              class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
              readonly
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Allocations</label>
            <input
              v-model.number="settings.feature_limits.allocations"
              type="number"
              class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
              readonly
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Backups</label>
            <input
              v-model.number="settings.feature_limits.backups"
              type="number"
              class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
              readonly
            />
          </div>
        </div>
        <p class="text-xs text-gray-400 mt-2">Feature limits are read-only</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRemoteServer } from '@/composables/useRemoteServer'
import { useToast } from 'vue-toastification'

const props = defineProps<{
  remoteServerId: string
}>()

const toast = useToast()
const { serverDetails, loading, error, fetchServerDetails } = useRemoteServer(props.remoteServerId)

const settings = ref<any>({
  name: '',
  description: '',
  limits: {
    memory: 0,
    disk: 0,
    cpu: 0,
    swap: 0,
  },
  feature_limits: {
    databases: 0,
    allocations: 0,
    backups: 0,
  }
})

const saveSettings = async () => {
  toast.info('Settings update not implemented yet')
}

onMounted(async () => {
  await fetchServerDetails()
  if (serverDetails.value) {
    settings.value = {
      name: serverDetails.value.name,
      description: serverDetails.value.description,
      limits: serverDetails.value.limits,
      feature_limits: serverDetails.value.feature_limits,
    }
  }
})
</script>
