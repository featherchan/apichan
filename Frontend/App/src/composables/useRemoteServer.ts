import { ref } from 'vue'
import { useApichanAPI } from './useApichanAPI'
import type { RemoteServer, RemoteServerDetails, RemoteServerStats } from '@/types/remote-server'

export function useRemoteServer(remoteServerId: string) {
  const api = useApichanAPI()
  
  const server = ref<RemoteServer | null>(null)
  const serverDetails = ref<RemoteServerDetails | null>(null)
  const serverStats = ref<RemoteServerStats | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)

  const fetchServer = async () => {
    loading.value = true
    error.value = null
    try {
      const response = await api.getRemoteServer(remoteServerId)
      server.value = response
    } catch (e: any) {
      error.value = e.message || 'Failed to fetch server'
    } finally {
      loading.value = false
    }
  }

  const fetchServerDetails = async () => {
    loading.value = true
    error.value = null
    try {
      const response = await api.getRemoteServerDetails(remoteServerId)
      serverDetails.value = response
    } catch (e: any) {
      error.value = e.message || 'Failed to fetch server details'
    } finally {
      loading.value = false
    }
  }

  const fetchServerStats = async () => {
    try {
      const response = await api.getRemoteServerStats(remoteServerId)
      serverStats.value = response
    } catch (e: any) {
      console.error('Failed to fetch server stats:', e)
    }
  }

  const sendPowerAction = async (action: 'start' | 'stop' | 'restart' | 'kill') => {
    loading.value = true
    error.value = null
    try {
      await api.sendRemoteServerPower(remoteServerId, action)
      await fetchServerStats()
    } catch (e: any) {
      error.value = e.message || 'Failed to send power action'
      throw e
    } finally {
      loading.value = false
    }
  }

  const sendCommand = async (command: string) => {
    try {
      await api.sendRemoteServerCommand(remoteServerId, command)
    } catch (e: any) {
      error.value = e.message || 'Failed to send command'
      throw e
    }
  }

  return {
    server,
    serverDetails,
    serverStats,
    loading,
    error,
    fetchServer,
    fetchServerDetails,
    fetchServerStats,
    sendPowerAction,
    sendCommand,
  }
}
