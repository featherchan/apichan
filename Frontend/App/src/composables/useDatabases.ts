import { ref } from 'vue'
import { useApichanAPI } from './useApichanAPI'
import type { RemoteDatabase, CreateDatabaseParams } from '@/types/database'

export function useDatabases(remoteServerId: string) {
  const api = useApichanAPI()
  
  const databases = ref<RemoteDatabase[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  const fetchDatabases = async () => {
    loading.value = true
    error.value = null
    try {
      const response = await api.getRemoteServerDatabases(remoteServerId)
      databases.value = response.data
    } catch (e: any) {
      error.value = e.message || 'Failed to fetch databases'
    } finally {
      loading.value = false
    }
  }

  const createDatabase = async (params: CreateDatabaseParams) => {
    loading.value = true
    error.value = null
    try {
      await api.createRemoteServerDatabase(remoteServerId, params)
      await fetchDatabases()
    } catch (e: any) {
      error.value = e.message || 'Failed to create database'
      throw e
    } finally {
      loading.value = false
    }
  }

  const deleteDatabase = async (databaseId: string) => {
    loading.value = true
    error.value = null
    try {
      await api.deleteRemoteServerDatabase(remoteServerId, databaseId)
      await fetchDatabases()
    } catch (e: any) {
      error.value = e.message || 'Failed to delete database'
      throw e
    } finally {
      loading.value = false
    }
  }

  const rotatePassword = async (databaseId: string) => {
    loading.value = true
    error.value = null
    try {
      await api.rotateRemoteServerDatabasePassword(remoteServerId, { database_id: databaseId })
      await fetchDatabases()
    } catch (e: any) {
      error.value = e.message || 'Failed to rotate password'
      throw e
    } finally {
      loading.value = false
    }
  }

  return {
    databases,
    loading,
    error,
    fetchDatabases,
    createDatabase,
    deleteDatabase,
    rotatePassword,
  }
}
