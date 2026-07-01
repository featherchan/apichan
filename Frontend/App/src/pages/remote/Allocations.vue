<template>
  <div class="p-6">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold">Network Allocations</h1>
      <button
        @click="autoAllocate"
        :disabled="loading"
        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-600 disabled:cursor-not-allowed rounded-lg transition-colors"
      >
        Auto Allocate
      </button>
    </div>

    <div v-if="loading && allocations.length === 0" class="text-center py-8 text-gray-400">
      Loading allocations...
    </div>

    <div v-else-if="error" class="text-center py-8 text-red-400">
      {{ error }}
    </div>

    <div v-else class="space-y-4">
      <div class="bg-gray-800 rounded-lg p-4">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-semibold">Allocations</h2>
          <span class="text-sm text-gray-400">
            {{ allocations.length }} / {{ maxAllocations }} used
          </span>
        </div>

        <div class="space-y-2">
          <div
            v-for="allocation in allocations"
            :key="allocation.id"
            class="flex items-center justify-between p-3 bg-gray-900 rounded-lg"
          >
            <div class="flex items-center gap-4 flex-1">
              <Network class="w-5 h-5 text-green-400" />
              <div>
                <div class="flex items-center gap-2">
                  <code class="text-gray-100">{{ allocation.ip }}:{{ allocation.port }}</code>
                  <span
                    v-if="allocation.is_default"
                    class="px-2 py-1 text-xs bg-blue-600 rounded"
                  >
                    Primary
                  </span>
                </div>
                <p v-if="allocation.notes" class="text-sm text-gray-400 mt-1">
                  {{ allocation.notes }}
                </p>
              </div>
            </div>

            <div class="flex items-center gap-2">
              <button
                @click="copyToClipboard(`${allocation.ip}:${allocation.port}`)"
                class="p-2 text-gray-400 hover:bg-gray-700 rounded transition-colors"
                title="Copy"
              >
                <Copy class="w-4 h-4" />
              </button>

              <button
                v-if="!allocation.is_default"
                @click="setPrimary(allocation.id)"
                class="p-2 text-blue-400 hover:bg-gray-700 rounded transition-colors"
                title="Set as Primary"
              >
                <Star class="w-4 h-4" />
              </button>

              <button
                v-if="!allocation.is_default"
                @click="deleteAllocation(allocation.id)"
                class="p-2 text-red-400 hover:bg-gray-700 rounded transition-colors"
                title="Delete"
              >
                <Trash2 class="w-4 h-4" />
              </button>
            </div>
          </div>

          <div v-if="allocations.length === 0" class="text-center py-8 text-gray-400">
            No allocations found
          </div>
        </div>
      </div>

      <div class="bg-gray-800 rounded-lg p-4">
        <h2 class="text-lg font-semibold mb-4">Add Allocation</h2>
        
        <div class="grid grid-cols-3 gap-3">
          <div class="col-span-2">
            <label class="block text-sm font-medium text-gray-300 mb-2">IP Address</label>
            <input
              v-model="newAllocationIp"
              type="text"
              placeholder="192.168.1.1"
              class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Port</label>
            <input
              v-model.number="newAllocationPort"
              type="number"
              placeholder="25565"
              class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
        </div>

        <div class="mt-3">
          <label class="block text-sm font-medium text-gray-300 mb-2">Notes (Optional)</label>
          <input
            v-model="newAllocationNotes"
            type="text"
            placeholder="e.g., Game port"
            class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>

        <button
          @click="addAllocation"
          :disabled="loading || !newAllocationIp || !newAllocationPort"
          class="mt-4 w-full px-4 py-2 bg-green-600 hover:bg-green-700 disabled:bg-gray-600 disabled:cursor-not-allowed rounded-lg transition-colors"
        >
          Add Allocation
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useApichanAPI } from '@/composables/useApichanAPI'
import { useToast } from 'vue-toastification'
import { Network, Copy, Star, Trash2 } from 'lucide-vue-next'

const props = defineProps<{
  remoteServerId: string
}>()

const api = useApichanAPI()
const toast = useToast()

const loading = ref(false)
const error = ref<string | null>(null)
const allocations = ref<any[]>([])
const maxAllocations = ref(5)

const newAllocationIp = ref('')
const newAllocationPort = ref<number | null>(null)
const newAllocationNotes = ref('')

const fetchAllocations = async () => {
  loading.value = true
  error.value = null
  try {
    const response = await api.remoteListAllocations(parseInt(props.remoteServerId))
    allocations.value = response
  } catch (e: any) {
    error.value = e.message || 'Failed to fetch allocations'
  } finally {
    loading.value = false
  }
}

const addAllocation = async () => {
  loading.value = true
  try {
    toast.info('Add allocation not implemented yet')
    newAllocationIp.value = ''
    newAllocationPort.value = null
    newAllocationNotes.value = ''
  } catch (e: any) {
    toast.error(e.message || 'Failed to add allocation')
  } finally {
    loading.value = false
  }
}

const autoAllocate = async () => {
  loading.value = true
  try {
    toast.info('Auto allocate not implemented yet')
  } catch (e: any) {
    toast.error(e.message || 'Failed to auto allocate')
  } finally {
    loading.value = false
  }
}

const setPrimary = async (id: number) => {
  loading.value = true
  try {
    toast.info('Set primary not implemented yet')
  } catch (e: any) {
    toast.error(e.message || 'Failed to set primary')
  } finally {
    loading.value = false
  }
}

const deleteAllocation = async (id: number) => {
  if (!confirm('Are you sure you want to delete this allocation?')) return

  loading.value = true
  try {
    toast.info('Delete allocation not implemented yet')
  } catch (e: any) {
    toast.error(e.message || 'Failed to delete allocation')
  } finally {
    loading.value = false
  }
}

const copyToClipboard = (text: string) => {
  navigator.clipboard.writeText(text)
  toast.success('Copied to clipboard')
}

onMounted(() => {
  fetchAllocations()
})
</script>
