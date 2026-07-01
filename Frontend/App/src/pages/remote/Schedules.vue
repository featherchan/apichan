<template>
  <div class="p-6">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold">Schedules</h1>
      <button
        @click="showCreateDialog = true"
        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors"
      >
        Create Schedule
      </button>
    </div>

    <div v-if="loading && schedules.length === 0" class="text-center py-8 text-gray-400">
      Loading schedules...
    </div>

    <div v-else-if="error" class="text-center py-8 text-red-400">
      {{ error }}
    </div>

    <div v-else class="space-y-4">
      <div
        v-for="schedule in schedules"
        :key="schedule.id"
        class="bg-gray-800 rounded-lg p-4"
      >
        <div class="flex items-center justify-between mb-3">
          <div class="flex-1">
            <div class="flex items-center gap-3">
              <Calendar class="w-5 h-5 text-purple-400" />
              <div>
                <h3 class="font-semibold text-gray-100">{{ schedule.name }}</h3>
                <div class="flex items-center gap-4 mt-1 text-sm text-gray-400">
                  <span>{{ formatCron(schedule.cron) }}</span>
                  <span v-if="schedule.next_run_at">Next: {{ formatDate(schedule.next_run_at) }}</span>
                  <span v-if="schedule.is_processing" class="text-yellow-400">Processing...</span>
                  <span v-else-if="schedule.is_active" class="text-green-400">Active</span>
                  <span v-else class="text-gray-500">Inactive</span>
                </div>
              </div>
            </div>
          </div>

          <div class="flex items-center gap-2">
            <button
              @click="triggerSchedule(schedule.id)"
              :disabled="loading || schedule.is_processing"
              class="p-2 text-blue-400 hover:bg-gray-700 rounded transition-colors disabled:opacity-50"
              title="Run Now"
            >
              <Play class="w-5 h-5" />
            </button>

            <button
              @click="editSchedule(schedule)"
              class="p-2 text-yellow-400 hover:bg-gray-700 rounded transition-colors"
              title="Edit"
            >
              <Edit class="w-5 h-5" />
            </button>

            <button
              @click="deleteSchedule(schedule.id)"
              class="p-2 text-red-400 hover:bg-gray-700 rounded transition-colors"
              title="Delete"
            >
              <Trash2 class="w-5 h-5" />
            </button>
          </div>
        </div>

        <div v-if="schedule.relationships?.tasks && schedule.relationships.tasks.length > 0" class="pl-8 space-y-2">
          <div
            v-for="task in schedule.relationships.tasks"
            :key="task.id"
            class="bg-gray-900 rounded p-3 text-sm"
          >
            <div class="flex items-center justify-between">
              <div>
                <span class="font-medium text-gray-300">{{ task.action.toUpperCase() }}</span>
                <span class="text-gray-400 ml-2">{{ task.payload }}</span>
                <span class="text-gray-500 ml-2">(+{{ task.time_offset }}s)</span>
              </div>
              <button
                @click="deleteTask(schedule.id, task.id)"
                class="p-1 text-red-400 hover:bg-gray-800 rounded transition-colors"
              >
                <X class="w-4 h-4" />
              </button>
            </div>
          </div>
        </div>
      </div>

      <div v-if="schedules.length === 0" class="text-center py-8 text-gray-400">
        No schedules found
      </div>
    </div>

    <div v-if="showCreateDialog || showEditDialog" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-gray-800 rounded-lg p-6 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <h2 class="text-xl font-bold mb-4">{{ showEditDialog ? 'Edit' : 'Create' }} Schedule</h2>
        
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-300 mb-2">Name</label>
          <input
            v-model="scheduleName"
            type="text"
            placeholder="Daily Restart"
            class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>

        <div class="grid grid-cols-5 gap-3 mb-4">
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Minute</label>
            <input
              v-model="cronMinute"
              type="text"
              placeholder="*"
              class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Hour</label>
            <input
              v-model="cronHour"
              type="text"
              placeholder="*"
              class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Day</label>
            <input
              v-model="cronDayOfMonth"
              type="text"
              placeholder="*"
              class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Month</label>
            <input
              v-model="cronMonth"
              type="text"
              placeholder="*"
              class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Weekday</label>
            <input
              v-model="cronDayOfWeek"
              type="text"
              placeholder="*"
              class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"
            />
          </div>
        </div>

        <div class="mb-4 flex gap-4">
          <label class="flex items-center gap-2 text-sm text-gray-300">
            <input
              v-model="isActive"
              type="checkbox"
              class="rounded bg-gray-700 border-gray-600"
            />
            Active
          </label>
          <label class="flex items-center gap-2 text-sm text-gray-300">
            <input
              v-model="onlyWhenOnline"
              type="checkbox"
              class="rounded bg-gray-700 border-gray-600"
            />
            Only when server is online
          </label>
        </div>

        <div class="flex gap-2 justify-end">
          <button
            @click="closeDialogs"
            class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg transition-colors"
          >
            Cancel
          </button>
          <button
            @click="saveSchedule"
            :disabled="loading || !scheduleName.trim()"
            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-600 disabled:cursor-not-allowed rounded-lg transition-colors"
          >
            {{ showEditDialog ? 'Update' : 'Create' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useSchedules } from '@/composables/useSchedules'
import { formatDate, formatCron } from '@/utils/format'
import { useToast } from 'vue-toastification'
import { Calendar, Play, Edit, Trash2, X } from 'lucide-vue-next'
import type { RemoteSchedule } from '@/types/schedule'

const props = defineProps<{
  remoteServerId: string
}>()

const toast = useToast()
const { schedules, loading, error, fetchSchedules, createSchedule, updateSchedule, deleteSchedule: deleteScheduleFn, triggerSchedule: triggerScheduleFn, deleteTask: deleteTaskFn } = useSchedules(props.remoteServerId)

const showCreateDialog = ref(false)
const showEditDialog = ref(false)
const editingScheduleId = ref<number | null>(null)
const scheduleName = ref('')
const cronMinute = ref('*')
const cronHour = ref('*')
const cronDayOfMonth = ref('*')
const cronMonth = ref('*')
const cronDayOfWeek = ref('*')
const isActive = ref(true)
const onlyWhenOnline = ref(false)

const saveSchedule = async () => {
  try {
    const params = {
      name: scheduleName.value.trim(),
      minute: cronMinute.value,
      hour: cronHour.value,
      day_of_month: cronDayOfMonth.value,
      month: cronMonth.value,
      day_of_week: cronDayOfWeek.value,
      is_active: isActive.value,
      only_when_online: onlyWhenOnline.value,
    }

    if (showEditDialog.value && editingScheduleId.value !== null) {
      await updateSchedule(editingScheduleId.value, params)
      toast.success('Schedule updated successfully')
    } else {
      await createSchedule(params)
      toast.success('Schedule created successfully')
    }

    closeDialogs()
  } catch (e: any) {
    toast.error(e.message || 'Failed to save schedule')
  }
}

const editSchedule = (schedule: RemoteSchedule) => {
  editingScheduleId.value = schedule.id
  scheduleName.value = schedule.name
  cronMinute.value = schedule.cron.minute
  cronHour.value = schedule.cron.hour
  cronDayOfMonth.value = schedule.cron.day_of_month
  cronMonth.value = schedule.cron.month
  cronDayOfWeek.value = schedule.cron.day_of_week
  isActive.value = schedule.is_active
  onlyWhenOnline.value = schedule.only_when_online
  showEditDialog.value = true
}

const deleteSchedule = async (id: number) => {
  if (!confirm('Are you sure you want to delete this schedule?')) return

  try {
    await deleteScheduleFn(id)
    toast.success('Schedule deleted successfully')
  } catch (e: any) {
    toast.error(e.message || 'Failed to delete schedule')
  }
}

const triggerSchedule = async (id: number) => {
  try {
    await triggerScheduleFn(id)
    toast.success('Schedule triggered successfully')
  } catch (e: any) {
    toast.error(e.message || 'Failed to trigger schedule')
  }
}

const deleteTask = async (scheduleId: number, taskId: number) => {
  if (!confirm('Are you sure you want to delete this task?')) return

  try {
    await deleteTaskFn(scheduleId, taskId)
    toast.success('Task deleted successfully')
  } catch (e: any) {
    toast.error(e.message || 'Failed to delete task')
  }
}

const closeDialogs = () => {
  showCreateDialog.value = false
  showEditDialog.value = false
  editingScheduleId.value = null
  scheduleName.value = ''
  cronMinute.value = '*'
  cronHour.value = '*'
  cronDayOfMonth.value = '*'
  cronMonth.value = '*'
  cronDayOfWeek.value = '*'
  isActive.value = true
  onlyWhenOnline.value = false
}

onMounted(() => {
  fetchSchedules()
})
</script>
