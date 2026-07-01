<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useToast } from 'vue-toastification'
import { useApichanAPI, type RemoteServer } from '@/composables/useApichanAPI'
import { Pin, Server, Trash2, Eye } from 'lucide-vue-next'
import RemoteServerManager from './RemoteServerManager.vue'

const toast = useToast()
const api = useApichanAPI()

const remoteServers = ref<RemoteServer[]>([])
const loading = ref(false)
const selectedServer = ref<RemoteServer | null>(null)
const viewMode = ref<'list' | 'manage'>('list')

async function loadRemoteServers() {
  loading.value = true
  try {
    remoteServers.value = await api.listRemoteServers()
  } catch (e: any) {
    toast.error(e.message || 'Failed to load remote servers')
  } finally {
    loading.value = false
  }
}

async function removeServer(id: number) {
  if (!confirm('Are you sure you want to remove this remote server?')) return
  
  try {
    await api.removeRemoteServer(id)
    toast.success('Remote server removed')
    await loadRemoteServers()
  } catch (e: any) {
    toast.error(e.message || 'Failed to remove server')
  }
}

function viewServer(server: RemoteServer) {
  selectedServer.value = server
  viewMode.value = 'manage'
}

function backToList() {
  selectedServer.value = null
  viewMode.value = 'list'
  loadRemoteServers()
}

onMounted(() => {
  loadRemoteServers()
})
</script>

<template>
  <div class="remote-container">
    <div v-if="viewMode === 'list'" class="list-view">
      <div class="header">
        <h2 class="title">Remote Servers</h2>
        <button @click="loadRemoteServers" class="btn btn-secondary" :disabled="loading">
          <svg v-if="loading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <span v-else>Refresh</span>
        </button>
      </div>

      <div v-if="loading && remoteServers.length === 0" class="loading">
        Loading remote servers...
      </div>

      <div v-else-if="remoteServers.length === 0" class="empty">
        <Pin class="w-12 h-12 opacity-20" />
        <p>No remote servers pinned yet</p>
        <p class="text-sm opacity-50">Pin servers from Sources to manage them here</p>
      </div>

      <div v-else class="grid">
        <div
          v-for="server in remoteServers"
          :key="server.id"
          class="card"
        >
          <div class="card-header">
            <div class="flex items-center gap-2">
              <Server class="w-5 h-5 text-blue-400" />
              <div>
                <h3 class="font-semibold">{{ server.name }}</h3>
                <p class="text-xs text-gray-400">{{ server.source_name }} ({{ server.source_type }})</p>
              </div>
            </div>
          </div>

          <div class="card-actions">
            <button @click="viewServer(server)" class="btn btn-primary btn-sm">
              <Eye class="w-4 h-4" />
              Manage
            </button>
            <button @click="removeServer(server.id)" class="btn btn-danger btn-sm">
              <Trash2 class="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <div v-else-if="viewMode === 'manage' && selectedServer" class="manage-view">
      <div class="back-button">
        <button @click="backToList" class="btn btn-secondary">
          ← Back to List
        </button>
      </div>
      <RemoteServerManager :remote-server-id="String(selectedServer.id)" />
    </div>
  </div>
</template>

<style scoped>
.remote-container {
  padding: 1.5rem;
}

.list-view {
  max-width: 1200px;
  margin: 0 auto;
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.title {
  font-size: 1.5rem;
  font-weight: bold;
}

.loading, .empty {
  text-align: center;
  padding: 4rem 0;
  color: #6b7280;
}

.empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
}

.grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 1rem;
}

.card {
  background: #1f2937;
  border: 1px solid #374151;
  border-radius: 0.5rem;
  padding: 1rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.card-header {
  flex: 1;
}

.card-actions {
  display: flex;
  gap: 0.5rem;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1rem;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  font-weight: 500;
  transition: all 0.2s;
  border: none;
  cursor: pointer;
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-primary {
  background: #3b82f6;
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background: #2563eb;
}

.btn-secondary {
  background: #374151;
  color: #d1d5db;
}

.btn-secondary:hover:not(:disabled) {
  background: #4b5563;
}

.btn-danger {
  background: #ef4444;
  color: white;
}

.btn-danger:hover:not(:disabled) {
  background: #dc2626;
}

.btn-sm {
  padding: 0.375rem 0.75rem;
  font-size: 0.8rem;
}

.manage-view {
  max-width: 100%;
}

.back-button {
  margin-bottom: 1rem;
}

.animate-spin {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}
</style>
