<template>
  <div class="p-6">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold">File Manager</h1>
      <div class="flex gap-2">
        <button
          @click="showUploadDialog = true"
          class="px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors"
        >
          Upload
        </button>
        <button
          @click="showCreateFileDialog = true"
          class="px-4 py-2 bg-green-600 hover:bg-green-700 rounded-lg transition-colors"
        >
          New File
        </button>
        <button
          @click="showCreateFolderDialog = true"
          class="px-4 py-2 bg-green-600 hover:bg-green-700 rounded-lg transition-colors"
        >
          New Folder
        </button>
      </div>
    </div>

    <div class="mb-4 flex items-center gap-2 text-sm">
      <span class="text-gray-400">/</span>
      <template v-for="(part, index) in pathParts" :key="index">
        <span class="text-gray-400">/</span>
        <button
          @click="navigateTo(index)"
          class="text-blue-400 hover:text-blue-300 transition-colors"
        >
          {{ part }}
        </button>
      </template>
    </div>

    <div v-if="loading" class="text-center py-8 text-gray-400">
      Loading files...
    </div>

    <div v-else-if="error" class="text-center py-8 text-red-400">
      {{ error }}
    </div>

    <div v-else class="bg-gray-800 rounded-lg overflow-hidden">
      <table class="w-full">
        <thead class="bg-gray-700">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase">Name</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase">Size</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase">Modified</th>
            <th class="px-4 py-3 text-right text-xs font-medium text-gray-300 uppercase">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-700">
          <tr
            v-if="currentPath !== '/'"
            @click="goUp"
            class="hover:bg-gray-700 cursor-pointer transition-colors"
          >
            <td class="px-4 py-3" colspan="4">
              <div class="flex items-center gap-2 text-gray-300">
                <Folder class="w-5 h-5" />
                <span>..</span>
              </div>
            </td>
          </tr>
          <tr
            v-for="file in files"
            :key="file.name"
            @click="handleFileClick(file)"
            class="hover:bg-gray-700 cursor-pointer transition-colors"
          >
            <td class="px-4 py-3">
              <div class="flex items-center gap-2 text-gray-300">
                <Folder v-if="!file.is_file" class="w-5 h-5 text-yellow-500" />
                <File v-else class="w-5 h-5 text-gray-400" />
                <span>{{ file.name }}</span>
              </div>
            </td>
            <td class="px-4 py-3 text-sm text-gray-400">
              {{ file.is_file ? formatBytes(file.size) : '-' }}
            </td>
            <td class="px-4 py-3 text-sm text-gray-400">
              {{ formatDate(file.modified_at) }}
            </td>
            <td class="px-4 py-3 text-right">
              <div class="flex gap-2 justify-end">
                <button
                  v-if="file.is_file"
                  @click.stop="editFile(file)"
                  class="p-2 hover:bg-gray-600 rounded transition-colors"
                  title="Edit"
                >
                  <Edit class="w-4 h-4" />
                </button>
                <button
                  @click.stop="renameFile(file)"
                  class="p-2 hover:bg-gray-600 rounded transition-colors"
                  title="Rename"
                >
                  <Edit2 class="w-4 h-4" />
                </button>
                <button
                  v-if="file.is_file"
                  @click.stop="downloadFile(file)"
                  class="p-2 hover:bg-gray-600 rounded transition-colors"
                  title="Download"
                >
                  <Download class="w-4 h-4" />
                </button>
                <button
                  @click.stop="deleteFile(file)"
                  class="p-2 hover:bg-red-600 rounded transition-colors"
                  title="Delete"
                >
                  <Trash2 class="w-4 h-4" />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="showCreateFileDialog" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-gray-800 rounded-lg p-6 w-full max-w-md">
        <h2 class="text-xl font-bold mb-4">Create File</h2>
        <input
          v-model="newFileName"
          type="text"
          placeholder="File name"
          class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg mb-4 focus:outline-none focus:ring-2 focus:ring-blue-500"
        />
        <div class="flex gap-2 justify-end">
          <button
            @click="showCreateFileDialog = false"
            class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg transition-colors"
          >
            Cancel
          </button>
          <button
            @click="createFile"
            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors"
          >
            Create
          </button>
        </div>
      </div>
    </div>

    <div v-if="showCreateFolderDialog" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-gray-800 rounded-lg p-6 w-full max-w-md">
        <h2 class="text-xl font-bold mb-4">Create Folder</h2>
        <input
          v-model="newFolderName"
          type="text"
          placeholder="Folder name"
          class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg mb-4 focus:outline-none focus:ring-2 focus:ring-blue-500"
        />
        <div class="flex gap-2 justify-end">
          <button
            @click="showCreateFolderDialog = false"
            class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg transition-colors"
          >
            Cancel
          </button>
          <button
            @click="createFolder"
            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors"
          >
            Create
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useFileManager } from '@/composables/useFileManager'
import { formatBytes, formatDate } from '@/utils/format'
import { useToast } from 'vue-toastification'
import { Folder, File, Edit, Edit2, Download, Trash2 } from 'lucide-vue-next'
import type { RemoteFile } from '@/types/file'

const props = defineProps<{
  remoteServerId: string
}>()

const toast = useToast()
const { files, currentPath, loading, error, fetchFiles, createFolder: createFolderFn, writeFile, downloadFile: downloadFileFn, deleteFile: deleteFileFn } = useFileManager(props.remoteServerId)

const showCreateFileDialog = ref(false)
const showCreateFolderDialog = ref(false)
const showUploadDialog = ref(false)
const newFileName = ref('')
const newFolderName = ref('')

const pathParts = computed(() => {
  return currentPath.value.split('/').filter(p => p)
})

const navigateTo = (index: number) => {
  const parts = pathParts.value.slice(0, index + 1)
  const path = '/' + parts.join('/')
  fetchFiles(path)
}

const goUp = () => {
  const parts = pathParts.value.slice(0, -1)
  const path = parts.length > 0 ? '/' + parts.join('/') : '/'
  fetchFiles(path)
}

const handleFileClick = (file: RemoteFile) => {
  if (!file.is_file) {
    const newPath = currentPath.value === '/' 
      ? `/${file.name}` 
      : `${currentPath.value}/${file.name}`
    fetchFiles(newPath)
  }
}

const createFile = async () => {
  if (!newFileName.value.trim()) {
    toast.error('File name is required')
    return
  }

  try {
    const filePath = currentPath.value === '/' 
      ? `/${newFileName.value}` 
      : `${currentPath.value}/${newFileName.value}`
    
    await writeFile(filePath, '')
    toast.success('File created successfully')
    showCreateFileDialog.value = false
    newFileName.value = ''
    await fetchFiles(currentPath.value)
  } catch (e: any) {
    toast.error(e.message || 'Failed to create file')
  }
}

const createFolder = async () => {
  if (!newFolderName.value.trim()) {
    toast.error('Folder name is required')
    return
  }

  try {
    await createFolderFn(currentPath.value, newFolderName.value)
    toast.success('Folder created successfully')
    showCreateFolderDialog.value = false
    newFolderName.value = ''
  } catch (e: any) {
    toast.error(e.message || 'Failed to create folder')
  }
}

const editFile = (file: RemoteFile) => {
  const filePath = currentPath.value === '/' 
    ? `/${file.name}` 
    : `${currentPath.value}/${file.name}`
  window.location.href = `/apichan/remote/${props.remoteServerId}/files/edit?file=${encodeURIComponent(filePath)}`
}

const downloadFile = async (file: RemoteFile) => {
  try {
    const filePath = currentPath.value === '/' 
      ? `/${file.name}` 
      : `${currentPath.value}/${file.name}`
    const url = await downloadFileFn(filePath)
    window.open(url, '_blank')
  } catch (e: any) {
    toast.error(e.message || 'Failed to download file')
  }
}

const renameFile = (file: RemoteFile) => {
  toast.info('Rename feature coming soon')
}

const deleteFile = async (file: RemoteFile) => {
  if (!confirm(`Are you sure you want to delete ${file.name}?`)) return

  try {
    const filePath = currentPath.value === '/' 
      ? `/${file.name}` 
      : `${currentPath.value}/${file.name}`
    await deleteFileFn(filePath)
    toast.success('File deleted successfully')
  } catch (e: any) {
    toast.error(e.message || 'Failed to delete file')
  }
}

onMounted(() => {
  fetchFiles('/')
})
</script>
