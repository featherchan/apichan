import { ref } from 'vue'
import { useApichanAPI } from './useApichanAPI'
import { usePagination } from './usePagination'
import type { RemoteSchedule, CreateScheduleParams, CreateTaskParams } from '@/types/schedule'

export function useSchedules(remoteServerId: string) {
  const api = useApichanAPI()
  const pagination = usePagination()
  
  const schedules = ref<RemoteSchedule[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  const fetchSchedules = async () => {
    loading.value = true
    error.value = null
    try {
      const response = await api.getRemoteServerSchedules(remoteServerId)
      schedules.value = response.data
    } catch (e: any) {
      error.value = e.message || 'Failed to fetch schedules'
    } finally {
      loading.value = false
    }
  }

  const createSchedule = async (params: CreateScheduleParams) => {
    loading.value = true
    error.value = null
    try {
      await api.createRemoteServerSchedule(remoteServerId, params)
      await fetchSchedules()
    } catch (e: any) {
      error.value = e.message || 'Failed to create schedule'
      throw e
    } finally {
      loading.value = false
    }
  }

  const updateSchedule = async (scheduleId: number, params: Partial<CreateScheduleParams>) => {
    loading.value = true
    error.value = null
    try {
      await api.updateRemoteServerSchedule(remoteServerId, scheduleId, params)
      await fetchSchedules()
    } catch (e: any) {
      error.value = e.message || 'Failed to update schedule'
      throw e
    } finally {
      loading.value = false
    }
  }

  const deleteSchedule = async (scheduleId: number) => {
    loading.value = true
    error.value = null
    try {
      await api.deleteRemoteServerSchedule(remoteServerId, scheduleId)
      await fetchSchedules()
    } catch (e: any) {
      error.value = e.message || 'Failed to delete schedule'
      throw e
    } finally {
      loading.value = false
    }
  }

  const triggerSchedule = async (scheduleId: number) => {
    loading.value = true
    error.value = null
    try {
      await api.triggerRemoteServerSchedule(remoteServerId, scheduleId)
    } catch (e: any) {
      error.value = e.message || 'Failed to trigger schedule'
      throw e
    } finally {
      loading.value = false
    }
  }

  const createTask = async (scheduleId: number, params: CreateTaskParams) => {
    loading.value = true
    error.value = null
    try {
      await api.createRemoteServerScheduleTask(remoteServerId, scheduleId, params)
      await fetchSchedules()
    } catch (e: any) {
      error.value = e.message || 'Failed to create task'
      throw e
    } finally {
      loading.value = false
    }
  }

  const updateTask = async (scheduleId: number, taskId: number, params: Partial<CreateTaskParams>) => {
    loading.value = true
    error.value = null
    try {
      await api.updateRemoteServerScheduleTask(remoteServerId, scheduleId, taskId, params)
      await fetchSchedules()
    } catch (e: any) {
      error.value = e.message || 'Failed to update task'
      throw e
    } finally {
      loading.value = false
    }
  }

  const deleteTask = async (scheduleId: number, taskId: number) => {
    loading.value = true
    error.value = null
    try {
      await api.deleteRemoteServerScheduleTask(remoteServerId, scheduleId, taskId)
      await fetchSchedules()
    } catch (e: any) {
      error.value = e.message || 'Failed to delete task'
      throw e
    } finally {
      loading.value = false
    }
  }

  return {
    schedules,
    loading,
    error,
    pagination,
    fetchSchedules,
    createSchedule,
    updateSchedule,
    deleteSchedule,
    triggerSchedule,
    createTask,
    updateTask,
    deleteTask,
  }
}
