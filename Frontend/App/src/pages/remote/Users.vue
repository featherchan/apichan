<template>
  <div class="p-6">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold">Subusers</h1>
      <button
        @click="showCreateDialog = true"
        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors"
      >
        Add Subuser
      </button>
    </div>

    <div v-if="loading && users.length === 0" class="text-center py-8 text-gray-400">
      Loading subusers...
    </div>

    <div v-else-if="error" class="text-center py-8 text-red-400">
      {{ error }}
    </div>

    <div v-else class="space-y-4">
      <div
        v-for="user in users"
        :key="user.id"
        class="bg-gray-800 rounded-lg p-4 flex items-center justify-between"
      >
        <div class="flex-1">
          <div class="flex items-center gap-3">
            <User class="w-5 h-5 text-purple-400" />
            <div>
              <h3 class="font-semibold text-gray-100">{{ user.email }}</h3>
              <div class="flex items-center gap-4 mt-1 text-sm text-gray-400">
                <span>{{ user.permissions.length }} permissions</span>
                <span v-if="user.created_at">Added {{ formatDate(user.created_at) }}</span>
              </div>
            </div>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <button
            @click="viewPermissions(user)"
            class="p-2 text-blue-400 hover:bg-gray-700 rounded transition-colors"
            title="View Permissions"
          >
            <Eye class="w-5 h-5" />
          </button>

          <button
            @click="editUser(user)"
            class="p-2 text-yellow-400 hover:bg-gray-700 rounded transition-colors"
            title="Edit"
          >
            <Edit class="w-5 h-5" />
          </button>

          <button
            @click="deleteUser(user.id)"
            class="p-2 text-red-400 hover:bg-gray-700 rounded transition-colors"
            title="Delete"
          >
            <Trash2 class="w-5 h-5" />
          </button>
        </div>
      </div>

      <div v-if="users.length === 0" class="text-center py-8 text-gray-400">
        No subusers found
      </div>
    </div>

    <div v-if="showCreateDialog" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-gray-800 rounded-lg p-6 w-full max-w-md">
        <h2 class="text-xl font-bold mb-4">Add Subuser</h2>
        
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-300 mb-2">Email</label>
          <input
            v-model="newUserEmail"
            type="email"
            placeholder="user@example.com"
            class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>

        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-300 mb-2">Permissions</label>
          <div class="space-y-2 max-h-64 overflow-y-auto">
            <label
              v-for="perm in availablePermissions"
              :key="perm"
              class="flex items-center gap-2 text-sm text-gray-300"
            >
              <input
                v-model="selectedPermissions"
                :value="perm"
                type="checkbox"
                class="rounded bg-gray-700 border-gray-600"
              />
              {{ perm }}
            </label>
          </div>
        </div>

        <div class="flex gap-2 justify-end">
          <button
            @click="showCreateDialog = false"
            class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg transition-colors"
          >
            Cancel
          </button>
          <button
            @click="createUser"
            :disabled="loading || !newUserEmail.trim()"
            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-600 disabled:cursor-not-allowed rounded-lg transition-colors"
          >
            Add
          </button>
        </div>
      </div>
    </div>

    <div v-if="showPermissionsDialog" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-gray-800 rounded-lg p-6 w-full max-w-md">
        <h2 class="text-xl font-bold mb-4">Permissions</h2>
        
        <div v-if="selectedUser" class="space-y-2">
          <div
            v-for="perm in selectedUser.permissions"
            :key="perm"
            class="px-3 py-2 bg-gray-700 rounded text-sm"
          >
            {{ perm }}
          </div>
        </div>

        <div class="flex gap-2 justify-end mt-4">
          <button
            @click="showPermissionsDialog = false"
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
import { useToast } from 'vue-toastification'
import { formatDate } from '@/utils/format'
import { User, Eye, Edit, Trash2 } from 'lucide-vue-next'

const props = defineProps<{
  remoteServerId: string
}>()

const toast = useToast()

const loading = ref(false)
const error = ref<string | null>(null)
const users = ref<any[]>([])

const showCreateDialog = ref(false)
const showPermissionsDialog = ref(false)
const newUserEmail = ref('')
const selectedPermissions = ref<string[]>([])
const selectedUser = ref<any>(null)

const availablePermissions = [
  'control.console',
  'control.start',
  'control.stop',
  'control.restart',
  'file.read',
  'file.write',
  'file.delete',
  'backup.create',
  'backup.read',
  'backup.delete',
  'database.create',
  'database.read',
  'database.delete',
  'schedule.create',
  'schedule.read',
  'schedule.update',
  'schedule.delete',
  'user.create',
  'user.read',
  'user.update',
  'user.delete',
]

const fetchUsers = async () => {
  loading.value = true
  error.value = null
  try {
    users.value = []
    toast.info('Subusers feature not implemented yet')
  } catch (e: any) {
    error.value = e.message || 'Failed to fetch subusers'
  } finally {
    loading.value = false
  }
}

const createUser = async () => {
  loading.value = true
  try {
    toast.info('Create subuser not implemented yet')
    showCreateDialog.value = false
    newUserEmail.value = ''
    selectedPermissions.value = []
  } catch (e: any) {
    toast.error(e.message || 'Failed to create subuser')
  } finally {
    loading.value = false
  }
}

const viewPermissions = (user: any) => {
  selectedUser.value = user
  showPermissionsDialog.value = true
}

const editUser = (user: any) => {
  toast.info('Edit subuser not implemented yet')
}

const deleteUser = async (id: string) => {
  if (!confirm('Are you sure you want to remove this subuser?')) return

  try {
    toast.info('Delete subuser not implemented yet')
  } catch (e: any) {
    toast.error(e.message || 'Failed to delete subuser')
  }
}

onMounted(() => {
  fetchUsers()
})
</script>
