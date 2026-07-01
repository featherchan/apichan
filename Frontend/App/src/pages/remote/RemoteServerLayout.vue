<template>
  <div class="flex h-screen bg-gray-900 text-gray-100">
    <aside class="w-64 bg-gray-800 border-r border-gray-700 flex flex-col">
      <div class="p-4 border-b border-gray-700">
        <h2 class="text-lg font-semibold truncate">{{ serverName }}</h2>
        <div class="flex items-center gap-2 mt-2">
          <div :class="statusClass" class="w-2 h-2 rounded-full"></div>
          <span class="text-sm text-gray-400">{{ serverStatus }}</span>
        </div>
      </div>

      <nav class="flex-1 overflow-y-auto p-4">
        <div v-for="item in menuItems" :key="item.path">
          <a
            :href="item.path"
            :class="[
              'flex items-center gap-3 px-3 py-2 rounded-lg mb-1 transition-colors',
              isActive(item.path)
                ? 'bg-blue-600 text-white'
                : 'text-gray-300 hover:bg-gray-700'
            ]"
          >
            <component :is="item.icon" class="w-5 h-5" />
            <span>{{ item.label }}</span>
          </a>
        </div>
      </nav>
    </aside>

    <main class="flex-1 overflow-y-auto">
      <slot />
    </main>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRemoteServer } from '@/composables/useRemoteServer'
import {
  Terminal,
  Folder,
  Archive,
  Database,
  Calendar,
  Settings,
  Rocket,
  Users,
  Network,
  GitBranch,
} from 'lucide-vue-next'

const props = defineProps<{
  remoteServerId: string
}>()

const { server, serverDetails, serverStats, fetchServer, fetchServerDetails, fetchServerStats } = useRemoteServer(props.remoteServerId)

const menuItems = [
  { path: `/apichan/remote/${props.remoteServerId}/console`, label: 'Console', icon: Terminal },
  { path: `/apichan/remote/${props.remoteServerId}/files`, label: 'Files', icon: Folder },
  { path: `/apichan/remote/${props.remoteServerId}/backups`, label: 'Backups', icon: Archive },
  { path: `/apichan/remote/${props.remoteServerId}/databases`, label: 'Databases', icon: Database },
  { path: `/apichan/remote/${props.remoteServerId}/schedules`, label: 'Schedules', icon: Calendar },
  { path: `/apichan/remote/${props.remoteServerId}/users`, label: 'Users', icon: Users },
  { path: `/apichan/remote/${props.remoteServerId}/startup`, label: 'Startup', icon: Rocket },
  { path: `/apichan/remote/${props.remoteServerId}/settings`, label: 'Settings', icon: Settings },
  { path: `/apichan/remote/${props.remoteServerId}/allocations`, label: 'Network', icon: Network },
  { path: `/apichan/remote/${props.remoteServerId}/lifecycle-hooks`, label: 'Hooks', icon: GitBranch },
]

const serverName = computed(() => server.value?.name || 'Loading...')
const serverStatus = computed(() => {
  if (!serverStats.value) return 'Unknown'
  return serverStats.value.current_state || 'offline'
})

const statusClass = computed(() => {
  const status = serverStatus.value.toLowerCase()
  if (status === 'running') return 'bg-green-500'
  if (status === 'starting') return 'bg-yellow-500'
  if (status === 'stopping') return 'bg-orange-500'
  return 'bg-red-500'
})

const isActive = (path: string) => {
  return window.location.pathname === path
}

onMounted(async () => {
  await fetchServer()
  await fetchServerDetails()
  await fetchServerStats()
  
  setInterval(() => {
    fetchServerStats()
  }, 5000)
})
</script>
