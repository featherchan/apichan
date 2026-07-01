<template>
  <div class="flex flex-col h-full">
    <div class="flex items-center justify-between p-4 border-b border-gray-700">
      <h1 class="text-2xl font-bold">Console</h1>
      <div class="flex gap-2">
        <button
          @click="sendPower('start')"
          :disabled="loading || serverStatus === 'running'"
          class="px-4 py-2 bg-green-600 hover:bg-green-700 disabled:bg-gray-600 disabled:cursor-not-allowed rounded-lg transition-colors"
        >
          Start
        </button>
        <button
          @click="sendPower('restart')"
          :disabled="loading || serverStatus === 'offline'"
          class="px-4 py-2 bg-yellow-600 hover:bg-yellow-700 disabled:bg-gray-600 disabled:cursor-not-allowed rounded-lg transition-colors"
        >
          Restart
        </button>
        <button
          @click="sendPower('stop')"
          :disabled="loading || serverStatus === 'offline'"
          class="px-4 py-2 bg-orange-600 hover:bg-orange-700 disabled:bg-gray-600 disabled:cursor-not-allowed rounded-lg transition-colors"
        >
          Stop
        </button>
        <button
          @click="sendPower('kill')"
          :disabled="loading || serverStatus === 'offline'"
          class="px-4 py-2 bg-red-600 hover:bg-red-700 disabled:bg-gray-600 disabled:cursor-not-allowed rounded-lg transition-colors"
        >
          Kill
        </button>
      </div>
    </div>

    <div class="flex-1 bg-black p-4 overflow-y-auto font-mono text-sm" ref="consoleOutput">
      <div v-for="(line, index) in messages" :key="index" class="text-gray-300">
        {{ line }}
      </div>
      <div v-if="!isConnected" class="text-yellow-500">
        Connecting to console...
      </div>
    </div>

    <div class="p-4 border-t border-gray-700">
      <div class="flex gap-2">
        <input
          v-model="command"
          @keyup.enter="sendCommand"
          :disabled="!isConnected || serverStatus === 'offline'"
          type="text"
          placeholder="Enter command..."
          class="flex-1 px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
        />
        <button
          @click="sendCommand"
          :disabled="!isConnected || !command.trim() || serverStatus === 'offline'"
          class="px-6 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-600 disabled:cursor-not-allowed rounded-lg transition-colors"
        >
          Send
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, nextTick, onMounted } from 'vue'
import { useRemoteServer } from '@/composables/useRemoteServer'
import { useWebSocket } from '@/composables/useWebSocket'
import { useApichanAPI } from '@/composables/useApichanAPI'
import { useToast } from 'vue-toastification'

const props = defineProps<{
  remoteServerId: string
}>()

const api = useApichanAPI()
const toast = useToast()
const { serverStats, fetchServerStats, sendPowerAction } = useRemoteServer(props.remoteServerId)

const command = ref('')
const loading = ref(false)
const consoleOutput = ref<HTMLElement | null>(null)
const wsUrl = ref('')

const serverStatus = computed(() => {
  if (!serverStats.value) return 'unknown'
  return serverStats.value.current_state || 'offline'
})

const { isConnected, messages, send } = useWebSocket(wsUrl.value)

const sendCommand = () => {
  if (!command.value.trim() || !isConnected.value) return
  
  send(command.value)
  command.value = ''
}

const sendPower = async (action: 'start' | 'stop' | 'restart' | 'kill') => {
  loading.value = true
  try {
    await sendPowerAction(action)
    toast.success(`Server ${action} command sent`)
  } catch (e: any) {
    toast.error(e.message || `Failed to ${action} server`)
  } finally {
    loading.value = false
  }
}

watch(messages, async () => {
  await nextTick()
  if (consoleOutput.value) {
    consoleOutput.value.scrollTop = consoleOutput.value.scrollHeight
  }
})

onMounted(async () => {
  try {
    const wsData = await api.getRemoteWebsocket(parseInt(props.remoteServerId))
    wsUrl.value = `${wsData.socket}?token=${wsData.token}`
  } catch (e: any) {
    toast.error('Failed to connect to console')
  }

  await fetchServerStats()
  setInterval(() => {
    fetchServerStats()
  }, 5000)
})
</script>
