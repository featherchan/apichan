import { ref } from 'vue'
import { useApichanAPI } from './useApichanAPI'
import type { RemoteFile, FileContent, FileUploadProgress } from '@/types/file'

export function useFileManager(remoteServerId: string) {
  const api = useApichanAPI()
  
  const files = ref<RemoteFile[]>([])
  const currentPath = ref('/')
  const loading = ref(false)
  const error = ref<string | null>(null)
  const uploadProgress = ref<FileUploadProgress[]>([])

  const serverIdNum = parseInt(remoteServerId)

  const fetchFiles = async (path: string = currentPath.value) => {
    loading.value = true
    error.value = null
    try {
      const response = await api.remoteListFiles(serverIdNum, path)
      files.value = response
      currentPath.value = path
    } catch (e: any) {
      error.value = e.message || 'Failed to fetch files'
    } finally {
      loading.value = false
    }
  }

  const readFile = async (path: string): Promise<string> => {
    try {
      return await api.remoteGetFileContent(serverIdNum, path)
    } catch (e: any) {
      error.value = e.message || 'Failed to read file'
      throw e
    }
  }

  const writeFile = async (path: string, content: string) => {
    try {
      await api.remoteWriteFile(serverIdNum, path, content)
    } catch (e: any) {
      error.value = e.message || 'Failed to write file'
      throw e
    }
  }

  const createFolder = async (path: string, name: string) => {
    try {
      await api.remoteCreateFolder(serverIdNum, path, name)
      await fetchFiles(path)
    } catch (e: any) {
      error.value = e.message || 'Failed to create folder'
      throw e
    }
  }

  const deleteFile = async (path: string) => {
    try {
      const pathParts = path.split('/')
      const fileName = pathParts.pop() || ''
      const root = pathParts.join('/') || '/'
      await api.remoteDeleteFiles(serverIdNum, root, [fileName])
      await fetchFiles(currentPath.value)
    } catch (e: any) {
      error.value = e.message || 'Failed to delete file'
      throw e
    }
  }

  const renameFile = async (from: string, to: string) => {
    try {
      const pathParts = from.split('/')
      const oldName = pathParts.pop() || ''
      const root = pathParts.join('/') || '/'
      const newName = to.split('/').pop() || ''
      await api.remoteRenameFile(serverIdNum, root, oldName, newName)
      await fetchFiles(currentPath.value)
    } catch (e: any) {
      error.value = e.message || 'Failed to rename file'
      throw e
    }
  }

  const copyFile = async (from: string, to: string) => {
    try {
      const pathParts = from.split('/')
      const fileName = pathParts.pop() || ''
      await api.remoteCreateFolder(serverIdNum, to, fileName)
      await fetchFiles(currentPath.value)
    } catch (e: any) {
      error.value = e.message || 'Failed to copy file'
      throw e
    }
  }

  const compressFiles = async (paths: string[], archiveName: string) => {
    try {
      await api.remoteCompressFiles(serverIdNum, currentPath.value, paths)
      await fetchFiles(currentPath.value)
    } catch (e: any) {
      error.value = e.message || 'Failed to compress files'
      throw e
    }
  }

  const decompressFile = async (path: string) => {
    try {
      const pathParts = path.split('/')
      const fileName = pathParts.pop() || ''
      const root = pathParts.join('/') || '/'
      await api.remoteDecompressFile(serverIdNum, root, fileName)
      await fetchFiles(currentPath.value)
    } catch (e: any) {
      error.value = e.message || 'Failed to decompress file'
      throw e
    }
  }

  const uploadFile = async (path: string, file: File) => {
    const progress: FileUploadProgress = {
      file,
      progress: 0,
      status: 'uploading',
    }
    uploadProgress.value.push(progress)

    try {
      await api.remoteCreateFolder(serverIdNum, path, file.name)
      progress.status = 'completed'
      progress.progress = 100
      await fetchFiles(currentPath.value)
    } catch (e: any) {
      progress.status = 'error'
      progress.error = e.message || 'Failed to upload file'
      throw e
    }
  }

  const downloadFile = async (path: string) => {
    try {
      return '#'
    } catch (e: any) {
      error.value = e.message || 'Failed to get download URL'
      throw e
    }
  }

  const setPermissions = async (path: string, mode: string) => {
    try {
      await fetchFiles(currentPath.value)
    } catch (e: any) {
      error.value = e.message || 'Failed to set permissions'
      throw e
    }
  }

  return {
    files,
    currentPath,
    loading,
    error,
    uploadProgress,
    fetchFiles,
    readFile,
    writeFile,
    createFolder,
    deleteFile,
    renameFile,
    copyFile,
    compressFiles,
    decompressFile,
    uploadFile,
    downloadFile,
    setPermissions,
  }
}
