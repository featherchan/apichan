<template>
  <div class="p-6">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold">Backups</h1>
      <button
        @click="showCreateDialog = true"
        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors"
      >
        Create Backup
      </button>
    </div>

    <div v-if="loading && backups.length === 0" class="text-center py-8 text-gray-400">
      Loading backups...
    </div>

    <div v-else-if="error" class="text-center py-8 text-red-400">
      {{ error }}
    </div>

    <div v-else class="space-y-4">
      <div
        v-for="backup in backups"
        :key="backup.uuid"
        class="bg-gray-800 rounded-lg p-4 flex items-center justify-between"
      >
        <div class="flex-1">
          <div class="flex items-center gap-3">
            <Archive class="w-5 h-5 text-blue-400" />
            <div>
              <h3 class="font-semibold text-gray-100">{{ backup.name }}</h3>
              <div class="flex items-center gap-4 mt-1 text-sm text-gray-400">
                <span>{{ formatBytes(backup.bytes) }}</span>
                <span>{{ formatDate(backup.created_at) }}</span>
                <span v-if="backup.completed_at" class="text-green-400">Completed</span>
                <span v-else class="text-yellow-400">In Progress</span>
              </div>
            </div>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <button
            v-if="backup.is_locked"
            @click="toggleLock(backup.uuid)"
            class="p-2 text-yellow-400 hover:bg-gray-700 rounded transition-colors"
            title="Unlock"
          >
            <Lock class="w-5 h-5" />
          </button>
          <button
            v-else
            @click="toggleLock(backup.uuid)"
            class="p-2 text-gray-400 hover:bg-gray-700 rounded transition-colors"
            title="Lock"
          >
            <Unlock class="w-5 h-5" />
          </button>

          <button
            v-if="backup.completed_at"
            @click="download(backup.uuid)"
            class="p-2 text-blue-400 hover:bg-gray-700 rounded transition-colors"
            title="Download"
          >
            <Download class="w-5 h-5" />
          </button>

          <button
            v-if="backup.completed_at"
            @click="restore(backup.uuid)"
            class="p-2 text-green-400 hover:bg-gray-700 rounded transition-colors"
            title="Restore"
          >
            <RotateCcw class="w-5 h-5" />
          </button>

          <button
            @click="deleteBackup(backup.uuid)"
            :disabled="backup.is_locked"
            class="p-2 text-red-400 hover:bg-gray-700 rounded transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            title="Delete"
          >
            <Trash2 class="w-5 h-5" />
          </button>
        </div>
      </div>

      <div v-if="backups.length === 0" class="text-center py-8 text-gray-400">
        No backups found
      </div>
    </div>

    <div v-if="showCreateDialog" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-gray-800 rounded-lg p-6 w-full max-w-md">
        <h2 class="text-xl font-bold mb-4">Create Backup</h2>
        
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-300 mb-2">Backup Name (Optional)</label>
          <input
            v-model="backupName"
            type="text"
            placeholder="Leave empty for auto-generated name"
            class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>

        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-300 mb-2">Ignored Files (Optional)</label>
          <textarea
            v-model="ignoredFiles"
            placeholder="*.log&#10;temp/*"
            rows="3"
            class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
          <p class="text-xs text-gray-400 mt-1">One pattern per line</p>
        </div>

        <div class="mb-4">
          <label class="flex items-center gap-2 text-sm text-gray-300">
            <input
              v-model="isLocked"
              type="checkbox"
              class="rounded bg-gray-700 border-gray-600"
            />
            Lock backup (prevent deletion)
          </label>
        </div>

        <div class="flex gap-2 justify-end">
          <button
            @click="showCreateDialog = false"
            class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg transition-colors"
          >
            Cancel
          </button>
          <button
            @click="createBackup"
            :disabled="loading"
            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-600 disabled:cursor-not-allowed rounded-lg transition-colors"
          >
            Create
          </button>
        </div>
      </div>
    </div>

    <div v-if="showRestoreDialog" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-gray-800 rounded-lg p-6 w-full max-w-md">
        <h2 class="text-xl font-bold mb-4">Restore Backup</h2>
        
        <p class="text-gray-300 mb-4">
          Are you sure you want to restore this backup? This will stop the server and replace all files.
        </p>

        <div class="mb-4">
          <label class="flex items-center gap-2 text-sm text-gray-300">
            <input
              v-model="truncate"
              type="checkbox"
              class="rounded bg-gray-700 border-gray-600"
            />
            Delete existing files before restore
          </label>
        </div>

        <div class="flex gap-2 justify-end">
          <button
            @click="showRestoreDialog = false"
            class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg transition-colors"
          >
            Cancel
          </button>
          <button
            @click="confirmRestore"
            :disabled="loading"
            class="px-4 py-2 bg-green-600 hover:bg-green-700 disabled:bg-gray-600 disabled:cursor-not-allowed rounded-lg transition-colors"
          >
            Restore
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useBackups } from '@/composables/useBackups'
import { formatBytes, formatDate } from '@/utils/format'
import { useToast } from 'vue-toastification'
import { Archive, Download, Trash2, Lock, Unlock, RotateCcw } from 'lucide-vue-next'

const props = defineProps<{
  remoteServerId: string
}>()

const toast = useToast()
const { backups, loading, error, fetchBackups, createBackup: createBackupFn, deleteBackup: deleteBackupFn, restoreBackup, toggleBackupLock, downloadBackup } = useBackups(props.remoteServerId)

const showCreateDialog = ref(false)
const showRestoreDialog = ref(false)
const backupName = ref('')
const ignoredFiles = ref('')
const isLocked = ref(false)
const truncate = ref(false)
const restoreBackupUuid = ref('')

const createBackup = async () => {
  try {
    const params: any = {
      is_locked: isLocked.value,
    }

    if (backupName.value.trim()) {
      params.name = backupName.value.trim()
    }

    if (ignoredFiles.value.trim()) {
      params.ignored_files = ignoredFiles.value.split('\n').map(l => l.trim()).filter(l => l)
    }

    await createBackupFn(params)
    toast.success('Backup created successfully')
    showCreateDialog.value = false
    backupName.value = ''
    ignoredFiles.value = ''
    isLocked.value = false
  } catch (e: any) {
    toast.error(e.message || 'Failed to create backup')
  }
}

const toggleLock = async (uuid: string) => {
  try {
    await toggleBackupLock(uuid)
    toast.success('Backup lock toggled')
  } catch (e: any) {
    toast.error(e.message || 'Failed to toggle lock')
  }
}

const download = async (uuid: string) => {
  try {
    const url = await downloadBackup(uuid)
    window.open(url, '_blank')
    toast.success('Download started')
  } catch (e: any) {
    toast.error(e.message || 'Failed to download backup')
  }
}

const restore = (uuid: string) => {
  restoreBackupUuid.value = uuid
  showRestoreDialog.value = true
}

const confirmRestore = async () => {
  try {
    await restoreBackup(restoreBackupUuid.value, truncate.value)
    toast.success('Backup restore started')
    showRestoreDialog.value = false
    truncate.value = false
  } catch (e: any) {
    toast.error(e.message || 'Failed to restore backup')
  }
}

const deleteBackup = async (uuid: string) => {
  if (!confirm('Are you sure you want to delete this backup?')) return

  try {
    await deleteBackupFn(uuid)
    toast.success('Backup deleted successfully')
  } catch (e: any) {
    toast.error(e.message || 'Failed to delete backup')
  }
}

onMounted(() => {
  fetchBackups()
})
</script>
