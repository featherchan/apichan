import { ref } from 'vue'
import { useApichanAPI } from './useApichanAPI'
import { usePagination } from './usePagination'
import type { RemoteBackup, CreateBackupParams } from '@/types/backup'

export function useBackups(remoteServerId: string) {
  const api = useApichanAPI()
  const pagination = usePagination()
  
  const backups = ref<RemoteBackup[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  const fetchBackups = async () => {
    loading.value = true
    error.value = null
    try {
      const response = await api.getRemoteServerBackups(remoteServerId, {
        page: pagination.currentPage.value,
        per_page: pagination.perPage.value,
      })
      backups.value = response.data
      pagination.setTotal(response.meta.total)
    } catch (e: any) {
      error.value = e.message || 'Failed to fetch backups'
    } finally {
      loading.value = false
    }
  }

  const createBackup = async (params: CreateBackupParams) => {
    loading.value = true
    error.value = null
    try {
      await api.createRemoteServerBackup(remoteServerId, params)
      await fetchBackups()
    } catch (e: any) {
      error.value = e.message || 'Failed to create backup'
      throw e
    } finally {
      loading.value = false
    }
  }

  const deleteBackup = async (backupUuid: string) => {
    loading.value = true
    error.value = null
    try {
      await api.deleteRemoteServerBackup(remoteServerId, backupUuid)
      await fetchBackups()
    } catch (e: any) {
      error.value = e.message || 'Failed to delete backup'
      throw e
    } finally {
      loading.value = false
    }
  }

  const restoreBackup = async (backupUuid: string, truncate = false) => {
    loading.value = true
    error.value = null
    try {
      await api.restoreRemoteServerBackup(remoteServerId, { backup_uuid: backupUuid, truncate })
    } catch (e: any) {
      error.value = e.message || 'Failed to restore backup'
      throw e
    } finally {
      loading.value = false
    }
  }

  const toggleBackupLock = async (backupUuid: string) => {
    loading.value = true
    error.value = null
    try {
      await api.toggleRemoteServerBackupLock(remoteServerId, backupUuid)
      await fetchBackups()
    } catch (e: any) {
      error.value = e.message || 'Failed to toggle backup lock'
      throw e
    } finally {
      loading.value = false
    }
  }

  const downloadBackup = async (backupUuid: string) => {
    try {
      const response = await api.downloadRemoteServerBackup(remoteServerId, backupUuid)
      return response.download_url
    } catch (e: any) {
      error.value = e.message || 'Failed to get download URL'
      throw e
    }
  }

  return {
    backups,
    loading,
    error,
    pagination,
    fetchBackups,
    createBackup,
    deleteBackup,
    restoreBackup,
    toggleBackupLock,
    downloadBackup,
  }
}
