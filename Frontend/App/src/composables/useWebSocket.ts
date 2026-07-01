import { ref, onMounted, onUnmounted } from 'vue'

interface WebSocketMessage {
  event: string
  args: string[]
}

export function useWebSocket(url: string) {
  const socket = ref<WebSocket | null>(null)
  const isConnected = ref(false)
  const messages = ref<string[]>([])
  const error = ref<string | null>(null)

  const connect = () => {
    try {
      socket.value = new WebSocket(url)

      socket.value.onopen = () => {
        isConnected.value = true
        error.value = null
      }

      socket.value.onmessage = (event) => {
        try {
          const data: WebSocketMessage = JSON.parse(event.data)
          
          if (data.event === 'console output') {
            messages.value.push(...data.args)
          } else if (data.event === 'status') {
            messages.value.push(`[Status] ${data.args[0]}`)
          }
        } catch (e) {
          messages.value.push(event.data)
        }
      }

      socket.value.onerror = () => {
        error.value = 'WebSocket connection error'
        isConnected.value = false
      }

      socket.value.onclose = () => {
        isConnected.value = false
      }
    } catch (e) {
      error.value = 'Failed to create WebSocket connection'
    }
  }

  const disconnect = () => {
    if (socket.value) {
      socket.value.close()
      socket.value = null
      isConnected.value = false
    }
  }

  const send = (message: string) => {
    if (socket.value && isConnected.value) {
      socket.value.send(JSON.stringify({ event: 'send command', args: [message] }))
    }
  }

  const clear = () => {
    messages.value = []
  }

  onMounted(() => {
    connect()
  })

  onUnmounted(() => {
    disconnect()
  })

  return {
    isConnected,
    messages,
    error,
    connect,
    disconnect,
    send,
    clear,
  }
}
