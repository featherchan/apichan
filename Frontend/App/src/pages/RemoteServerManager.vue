<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useToast } from 'vue-toastification'
import { useApichanAPI } from '@/composables/useApichanAPI'
import RemoteServerLayout from './remote/RemoteServerLayout.vue'
import Console from './remote/Console.vue'
import Files from './remote/Files.vue'
import Backups from './remote/Backups.vue'
import Databases from './remote/Databases.vue'
import Schedules from './remote/Schedules.vue'
import Settings from './remote/Settings.vue'
import Startup from './remote/Startup.vue'
import Users from './remote/Users.vue'
import Allocations from './remote/Allocations.vue'
import LifecycleHooks from './remote/LifecycleHooks.vue'

const props = defineProps<{
  remoteServerId: string
  initialPage?: string
}>()

const toast = useToast()
const currentPage = ref(props.initialPage || 'console')

const pageComponents: Record<string, any> = {
  console: Console,
  files: Files,
  backups: Backups,
  databases: Databases,
  schedules: Schedules,
  settings: Settings,
  startup: Startup,
  users: Users,
  allocations: Allocations,
  'lifecycle-hooks': LifecycleHooks,
}

const CurrentComponent = computed(() => {
  return pageComponents[currentPage.value] || Console
})

const handleNavigation = () => {
  const path = window.location.pathname
  const parts = path.split('/')
  const pageIndex = parts.indexOf('remote') + 2
  if (pageIndex > 1 && parts[pageIndex]) {
    currentPage.value = parts[pageIndex]
  }
}

onMounted(() => {
  handleNavigation()
  window.addEventListener('popstate', handleNavigation)
})
</script>

<template>
  <RemoteServerLayout :remote-server-id="remoteServerId">
    <component :is="CurrentComponent" :remote-server-id="remoteServerId" />
  </RemoteServerLayout>
</template>
