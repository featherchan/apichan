<template>
  <div class="p-6">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold">Lifecycle Hooks</h1>
      <button
        @click="showCreateDialog = true"
        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors"
      >
        Create Hook
      </button>
    </div>

    <div v-if="loading && hooks.length === 0" class="text-center py-8 text-gray-400">
      Loading hooks...
    </div>

    <div v-else-if="error" class="text-center py-8 text-red-400">
      {{ error }}
    </div>

    <div v-else class="space-y-4">
      <div
        v-for="hook in hooks"
        :key="hook.id"
        class="bg-gray-800 rounded-lg p-4"
      >
        <div class="flex items-center justify-between mb-3">
          <div class="flex-1">
            <div class="flex items-center gap-3">
              <GitBranch class="w-5 h-5 text-indigo-400" />
              <div>
                <h3 class="font-semibold text-gray-100">{{ hook.event }}</h3>
                <div class="flex items-center gap-4 mt-1 text-sm text-gray-400">
                  <span>{{ hook.action }}</span>
                  <span v-if="hook.is_active" class="text-green-400">Active</span>
                  <span v-else class="text-gray-500">Inactive</span>
                </div>
              </div>
            </div>
          </div>

          <div class="flex items-center gap-2">
            <button
              @click="toggleHook(hook.id)"
              class="p-2 text-yellow-400 hover:bg-gray-700 rounded transition-colors"
              :title="hook.is_active ? 'Disable' : 'Enable'"
            >
              <Power class="w-5 h-5" />
            </button>

            <button
              @click="editHook(hook)"
              class="p-2 text-blue-400 hover:bg-gray-700 rounded transition-colors"
              title="Edit"
            >
              <Edit class="w-5 h-5" />
            </button>

            <button
              @click="deleteHook(hook.id)"
              class="p-2 text-red-400 hover:bg-gray-700 rounded transition-colors"
              title="Delete"
            >
              <Trash2 class="w-5 h-5" />
            </button>
          </div>
        </div>

        <div class="pl-8 space-y-2">
          <div class="bg-gray-900 rounded p-3 text-sm">
            <span class="text-gray-400">Payload:</span>
            <pre class="text-gray-300 mt-1">{{ hook.payload }}</pre>
          </div>
        </div>
      </div>

      <div v-if="hooks.length === 0" class="text-center py-8 text-gray-400">
        No lifecycle hooks configured
      </div>
    </div>

    <div v-if="showCreateDialog" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-gray-800 rounded-lg p-6 w-full max-w-md">
        <h2 class="text-xl font-bold mb-4">Create Lifecycle Hook</h2>
        
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-300 mb-2">Event</label>
          <select
            v-model="newHook.event"
            class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option value="">Select event</option>
            <option value="server.start">Server Start</option>
            <option value="server.stop">Server Stop</option>
            <option value="server.restart">Server Restart</option>
            <option value="server.crash">Server Crash</option>
            <option value="backup.complete">Backup Complete</option>
            <option value="backup.fail">Backup Failed</option>
          </select>
        </div>

        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-300 mb-2">Action</label>
          <select
            v-model="newHook.action"
            class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option value="">Select action</option>
            <option value="webhook">Webhook</option>
            <option value="command">Run Command</option>
            <option value="email">Send Email</option>
          </select>
        </div>

        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-300 mb-2">Payload</label>
          <textarea
            v-model="newHook.payload"
            rows="4"
            placeholder='{"url": "https://discord.com/api/webhooks/..."}'
            class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
          <p class="text-xs text-gray-400 mt-1">JSON format for webhook, command string for command action</p>
        </div>

        <div class="mb-4">
          <label class="flex items-center gap-2 text-sm text-gray-300">
            <input
              v-model="newHook.is_active"
              type="checkbox"
              class="rounded bg-gray-700 border-gray-600"
            />
            Active
          </label>
        </div>

        <div class="flex gap-2 justify-end">
          <button
            @click="closeCreateDialog"
            class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg transition-colors"
          >
            Cancel
          </button>
          <button
            @click="createHook"
            :disabled="loading || !newHook.event || !newHook.action || !newHook.payload"
            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-600 disabled:cursor-not-allowed rounded-lg transition-colors"
          >
            Create
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useToast } from 'vue-toastification'
import { GitBranch, Power, Edit, Trash2 } from 'lucide-vue-next'

const props = defineProps<{
  remoteServerId: string
}>()

const toast = useToast()

const loading = ref(false)
const error = ref<string | null>(null)
const hooks = ref<any[]>([])

const showCreateDialog = ref(false)
const newHook = ref({
  event: '',
  action: '',
  payload: '',
  is_active: true,
})

const fetchHooks = async () => {
  loading.value = true
  error.value = null
  try {
    hooks.value = []
    toast.info('Lifecycle hooks feature not implemented yet')
  } catch (e: any) {
    error.value = e.message || 'Failed to fetch hooks'
  } finally {
    loading.value = false
  }
}

const createHook = async () => {
  loading.value = true
  try {
    toast.info('Create hook not implemented yet')
    closeCreateDialog()
  } catch (e: any) {
    toast.error(e.message || 'Failed to create hook')
  } finally {
    loading.value = false
  }
}

const toggleHook = async (id: string) => {
  loading.value = true
  try {
    toast.info('Toggle hook not implemented yet')
  } catch (e: any) {
    toast.error(e.message || 'Failed to toggle hook')
  } finally {
    loading.value = false
  }
}

const editHook = (hook: any) => {
  toast.info('Edit hook not implemented yet')
}

const deleteHook = async (id: string) => {
  if (!confirm('Are you sure you want to delete this hook?')) return

  loading.value = true
  try {
    toast.info('Delete hook not implemented yet')
  } catch (e: any) {
    toast.error(e.message || 'Failed to delete hook')
  } finally {
    loading.value = false
  }
}

const closeCreateDialog = () => {
  showCreateDialog.value = false
  newHook.value = {
    event: '',
    action: '',
    payload: '',
    is_active: true,
  }
}

onMounted(() => {
  fetchHooks()
})
</script>
