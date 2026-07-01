<template>
  <div class="p-6">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold">Databases</h1>
      <button
        @click="showCreateDialog = true"
        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors"
      >
        Create Database
      </button>
    </div>

    <div v-if="loading && databases.length === 0" class="text-center py-8 text-gray-400">
      Loading databases...
    </div>

    <div v-else-if="error" class="text-center py-8 text-red-400">
      {{ error }}
    </div>

    <div v-else class="space-y-4">
      <div
        v-for="db in databases"
        :key="db.id"
        class="bg-gray-800 rounded-lg p-4 flex items-center justify-between"
      >
        <div class="flex-1">
          <div class="flex items-center gap-3">
            <Database class="w-5 h-5 text-green-400" />
            <div>
              <h3 class="font-semibold text-gray-100">{{ db.name }}</h3>
              <div class="flex items-center gap-4 mt-1 text-sm text-gray-400">
                <span>User: {{ db.username }}</span>
                <span>Remote: {{ db.remote }}</span>
                <span v-if="db.max_connections">Max Connections: {{ db.max_connections }}</span>
              </div>
            </div>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <button
            @click="viewCredentials(db)"
            class="p-2 text-blue-400 hover:bg-gray-700 rounded transition-colors"
            title="View Credentials"
          >
            <Eye class="w-5 h-5" />
          </button>

          <button
            @click="rotatePassword(db.id)"
            class="p-2 text-yellow-400 hover:bg-gray-700 rounded transition-colors"
            title="Rotate Password"
          >
            <RefreshCw class="w-5 h-5" />
          </button>

          <button
            @click="deleteDatabase(db.id)"
            class="p-2 text-red-400 hover:bg-gray-700 rounded transition-colors"
            title="Delete"
          >
            <Trash2 class="w-5 h-5" />
          </button>
        </div>
      </div>

      <div v-if="databases.length === 0" class="text-center py-8 text-gray-400">
        No databases found
      </div>
    </div>

    <div v-if="showCreateDialog" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-gray-800 rounded-lg p-6 w-full max-w-md">
        <h2 class="text-xl font-bold mb-4">Create Database</h2>
        
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-300 mb-2">Database Name</label>
          <input
            v-model="databaseName"
            type="text"
            placeholder="my_database"
            class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>

        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-300 mb-2">Remote Host</label>
          <input
            v-model="remoteHost"
            type="text"
            placeholder="%"
            class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
          <p class="text-xs text-gray-400 mt-1">Use % to allow connections from any host</p>
        </div>

        <div class="flex gap-2 justify-end">
          <button
            @click="showCreateDialog = false"
            class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg transition-colors"
          >
            Cancel
          </button>
          <button
            @click="createDatabase"
            :disabled="loading || !databaseName.trim()"
            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-600 disabled:cursor-not-allowed rounded-lg transition-colors"
          >
            Create
          </button>
        </div>
      </div>
    </div>

    <div v-if="showCredentialsDialog" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-gray-800 rounded-lg p-6 w-full max-w-md">
        <h2 class="text-xl font-bold mb-4">Database Credentials</h2>
        
        <div class="bg-yellow-900 border border-yellow-700 rounded-lg p-4 mb-4">
          <p class="text-yellow-200 text-sm">
            <strong>Warning:</strong> These credentials are sensitive. Keep them secure.
          </p>
        </div>

        <div v-if="selectedDatabase" class="space-y-3">
          <div>
            <label class="block text-sm font-medium text-gray-400 mb-1">Database Name</label>
            <div class="flex items-center gap-2">
              <code class="flex-1 px-3 py-2 bg-gray-900 rounded text-sm">{{ selectedDatabase.name }}</code>
              <button
                @click="copyToClipboard(selectedDatabase.name)"
                class="p-2 hover:bg-gray-700 rounded transition-colors"
              >
                <Copy class="w-4 h-4" />
              </button>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-400 mb-1">Username</label>
            <div class="flex items-center gap-2">
              <code class="flex-1 px-3 py-2 bg-gray-900 rounded text-sm">{{ selectedDatabase.username }}</code>
              <button
                @click="copyToClipboard(selectedDatabase.username)"
                class="p-2 hover:bg-gray-700 rounded transition-colors"
              >
                <Copy class="w-4 h-4" />
              </button>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-400 mb-1">Password</label>
            <div class="flex items-center gap-2">
              <code class="flex-1 px-3 py-2 bg-gray-900 rounded text-sm">
                {{ selectedDatabase.relationships?.password?.password || '••••••••' }}
              </code>
              <button
                @click="copyToClipboard(selectedDatabase.relationships?.password?.password || '')"
                class="p-2 hover:bg-gray-700 rounded transition-colors"
              >
                <Copy class="w-4 h-4" />
              </button>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-400 mb-1">Remote Host</label>
            <div class="flex items-center gap-2">
              <code class="flex-1 px-3 py-2 bg-gray-900 rounded text-sm">{{ selectedDatabase.remote }}</code>
              <button
                @click="copyToClipboard(selectedDatabase.remote)"
                class="p-2 hover:bg-gray-700 rounded transition-colors"
              >
                <Copy class="w-4 h-4" />
              </button>
            </div>
          </div>
        </div>

        <div class="flex gap-2 justify-end mt-4">
          <button
            @click="showCredentialsDialog = false"
            class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg transition-colors"
          >
            Close
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useDatabases } from '@/composables/useDatabases'
import { useToast } from 'vue-toastification'
import { Database, Eye, RefreshCw, Trash2, Copy } from 'lucide-vue-next'
import type { RemoteDatabase } from '@/types/database'

const props = defineProps<{
  remoteServerId: string
}>()

const toast = useToast()
const { databases, loading, error, fetchDatabases, createDatabase: createDatabaseFn, deleteDatabase: deleteDatabaseFn, rotatePassword: rotatePasswordFn } = useDatabases(props.remoteServerId)

const showCreateDialog = ref(false)
const showCredentialsDialog = ref(false)
const databaseName = ref('')
const remoteHost = ref('%')
const selectedDatabase = ref<RemoteDatabase | null>(null)

const createDatabase = async () => {
  try {
    await createDatabaseFn({
      database: databaseName.value.trim(),
      remote: remoteHost.value.trim(),
    })
    toast.success('Database created successfully')
    showCreateDialog.value = false
    databaseName.value = ''
    remoteHost.value = '%'
  } catch (e: any) {
    toast.error(e.message || 'Failed to create database')
  }
}

const viewCredentials = (db: RemoteDatabase) => {
  selectedDatabase.value = db
  showCredentialsDialog.value = true
}

const rotatePassword = async (id: string) => {
  if (!confirm('Are you sure you want to rotate the database password? This will change the password immediately.')) return

  try {
    await rotatePasswordFn(id)
    toast.success('Password rotated successfully')
  } catch (e: any) {
    toast.error(e.message || 'Failed to rotate password')
  }
}

const deleteDatabase = async (id: string) => {
  if (!confirm('Are you sure you want to delete this database? This action cannot be undone.')) return

  try {
    await deleteDatabaseFn(id)
    toast.success('Database deleted successfully')
  } catch (e: any) {
    toast.error(e.message || 'Failed to delete database')
  }
}

const copyToClipboard = (text: string) => {
  navigator.clipboard.writeText(text)
  toast.success('Copied to clipboard')
}

onMounted(() => {
  fetchDatabases()
})
</script>
