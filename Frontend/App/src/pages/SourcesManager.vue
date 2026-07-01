<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useToast } from 'vue-toastification'
import {
  useApichanAPI,
  type Source, type SourceType, type NormalizedServer,
  type RemoteServer, type ServerStatus, type FileEntry,
  type Allocation, type Schedule, type Backup, type Database,
} from '@/composables/useApichanAPI'
import {
  Link, Plus, Trash2, Pencil, RefreshCw, Loader2, ExternalLink,
  ChevronDown, ChevronUp, Server, X, Wifi, WifiOff, Check,
  Play, Square, RotateCcw, Zap, Folder, File, ChevronRight,
  Terminal, HardDrive, BarChart3, Database as DbIcon, Clock, Shield,
  Pin, PinOff,
} from 'lucide-vue-next'

const toast = useToast()
const api   = useApichanAPI()

// ── Tabs ───────────────────────────────────────────────────────────────────────
const activeTab = ref<'sources' | 'remote'>('sources')

const TYPE_OPTIONS: { value: SourceType; label: string }[] = [
  { value: 'pterodactyl', label: 'Pterodactyl' },
  { value: 'featherpanel', label: 'FeatherPanel' },
  { value: 'pelican', label: 'Pelican' },
  { value: 'calagopus', label: 'Calagopus' },
]
const TYPE_LABELS = Object.fromEntries(TYPE_OPTIONS.map(o => [o.value, o.label]))

// ── Sources ────────────────────────────────────────────────────────────────────
const sources = ref<Source[]>([])
const loading = ref(false)

async function loadSources() {
  loading.value = true
  try { sources.value = await api.listSources() }
  catch (e) { toast.error(e instanceof Error ? e.message : 'Failed to load sources') }
  finally { loading.value = false }
}

// ── Source modal ───────────────────────────────────────────────────────────────
const showSourceModal = ref(false)
const editingSource   = ref<Source | null>(null)
const sName = ref(''); const sType = ref<SourceType>('pterodactyl')
const sUrl  = ref(''); const sKey  = ref(''); const sTimeout = ref(15)
const saving = ref(false); const typeDropOpen = ref(false)
const typeDropRef = ref<HTMLElement | null>(null)
const testing = ref(false); const testResult = ref<{ ok: boolean; msg: string } | null>(null)

function handleClickOutsideType(e: MouseEvent) {
  if (typeDropRef.value && !typeDropRef.value.contains(e.target as Node)) typeDropOpen.value = false
}
onMounted(() => document.addEventListener('mousedown', handleClickOutsideType))
onUnmounted(() => document.removeEventListener('mousedown', handleClickOutsideType))

function pickType(v: SourceType) { sType.value = v; typeDropOpen.value = false; testResult.value = null }

function openCreateModal() {
  editingSource.value = null; sName.value = ''; sType.value = 'pterodactyl'
  sUrl.value = ''; sKey.value = ''; sTimeout.value = 15; testResult.value = null
  showSourceModal.value = true
}
function openEditModal(s: Source) {
  editingSource.value = s; sName.value = s.name; sType.value = s.type
  sUrl.value = s.url; sKey.value = ''; sTimeout.value = s.timeout; testResult.value = null
  showSourceModal.value = true
}
async function testConnection() {
  const key = sKey.value.trim(); const url = sUrl.value.trim()
  if (!url || !key) { toast.warning('Enter URL and API key before testing'); return }
  testing.value = true; testResult.value = null
  try {
    const d = await api.testConnection({ type: sType.value, url, api_key: key, timeout: sTimeout.value })
    testResult.value = { ok: true, msg: `Connected — ${d.server_count} server(s) found` }
  } catch (e) {
    testResult.value = { ok: false, msg: e instanceof Error ? e.message : 'Connection failed' }
  } finally { testing.value = false }
}
async function saveSource() {
  if (!sName.value.trim() || !sUrl.value.trim()) { toast.warning('Name and URL are required'); return }
  if (!editingSource.value && !sKey.value.trim()) { toast.warning('API key is required'); return }
  saving.value = true
  try {
    if (editingSource.value) {
      const payload: Parameters<typeof api.updateSource>[1] = {
        name: sName.value.trim(), type: sType.value, url: sUrl.value.trim(), timeout: sTimeout.value,
      }
      if (sKey.value.trim()) payload.api_key = sKey.value.trim()
      await api.updateSource(editingSource.value.id, payload)
      toast.success('Source updated')
    } else {
      await api.createSource({ name: sName.value.trim(), type: sType.value, url: sUrl.value.trim(), api_key: sKey.value.trim(), timeout: sTimeout.value })
      toast.success('Source created')
    }
    showSourceModal.value = false; await loadSources()
  } catch (e) { toast.error(e instanceof Error ? e.message : 'Save failed') }
  finally { saving.value = false }
}
async function deleteSource(s: Source) {
  if (!confirm(`Delete "${s.name}"? This cannot be undone.`)) return
  try {
    await api.deleteSource(s.id); toast.success('Source deleted')
    if (expandedId.value === s.id) expandedId.value = null
    await loadSources()
  } catch (e) { toast.error(e instanceof Error ? e.message : 'Delete failed') }
}

// ── Server browser ─────────────────────────────────────────────────────────────
const expandedId    = ref<number | null>(null)
const srv           = ref<NormalizedServer[]>([])
const srvPage       = ref(1); const srvTotal = ref(0); const srvTotalPages = ref(1)
const srvSearch     = ref(''); const loadingSrv = ref(false); const srvError = ref('')

async function toggleExpand(s: Source) {
  if (expandedId.value === s.id) { expandedId.value = null; return }
  expandedId.value = s.id; srvPage.value = 1; srvSearch.value = ''
  await fetchServers(s.id)
}
async function fetchServers(sourceId: number) {
  loadingSrv.value = true; srvError.value = ''
  try {
    const d = await api.listSourceServers(sourceId, srvPage.value)
    srv.value = d.servers ?? []; srvTotal.value = d.total ?? srv.value.length
    srvTotalPages.value = d.total_pages ?? 1
  } catch (e) {
    srvError.value = e instanceof Error ? e.message : 'Failed to load servers'; srv.value = []
  } finally { loadingSrv.value = false }
}
const filteredServers = computed(() => {
  const q = srvSearch.value.toLowerCase()
  return q ? srv.value.filter(s => s.name.toLowerCase().includes(q)) : srv.value
})
async function changeSrvPage(delta: number) {
  if (expandedId.value === null) return
  srvPage.value = Math.max(1, Math.min(srvTotalPages.value, srvPage.value + delta))
  await fetchServers(expandedId.value)
}

// ── Import modal ───────────────────────────────────────────────────────────────
const showImport = ref(false); const importSrv = ref<NormalizedServer | null>(null)
const importSrcId = ref<number | null>(null)
const iName = ref(''); const iMem = ref(''); const iDisk = ref(''); const iCpu = ref('')
const importing = ref(false)

interface DropItem { id: number; label: string }
const spells = ref<DropItem[]>([]); const nodes = ref<DropItem[]>([]); const allocs = ref<DropItem[]>([])
const spellSel = ref<DropItem | null>(null); const nodeSel = ref<DropItem | null>(null); const allocSel = ref<DropItem | null>(null)
const spellOpen = ref(false); const nodeOpen = ref(false); const allocOpen = ref(false)
const loadingSpells = ref(false); const loadingNodes = ref(false); const loadingAllocs = ref(false)
const spellRef = ref<HTMLElement | null>(null); const nodeRef = ref<HTMLElement | null>(null); const allocRef = ref<HTMLElement | null>(null)

function closeAllImportDrops() { spellOpen.value = false; nodeOpen.value = false; allocOpen.value = false }
function handleImportClickOutside(e: MouseEvent) {
  const t = e.target as Node
  if (spellRef.value && !spellRef.value.contains(t)) spellOpen.value = false
  if (nodeRef.value  && !nodeRef.value.contains(t))  nodeOpen.value  = false
  if (allocRef.value && !allocRef.value.contains(t)) allocOpen.value = false
}

async function loadSpells() {
  loadingSpells.value = true; spells.value = []
  try {
    let all: DropItem[] = [], page = 1, totalPages = 1
    do {
      const r = await fetch(`/api/admin/spells?page=${page}&limit=100`, { credentials: 'include', headers: { Accept: 'application/json' } })
      const j = await r.json()
      ;(j.data?.spells ?? []).forEach((s: any) => all.push({ id: s.id, label: s.name }))
      totalPages = j.data?.pagination?.total_pages ?? 1; page++
    } while (page <= totalPages && all.length < 300)
    spells.value = all
  } catch { /* silent */ } finally { loadingSpells.value = false }
}
async function loadNodes() {
  loadingNodes.value = true; nodes.value = []
  try {
    let all: DropItem[] = [], page = 1, totalPages = 1
    do {
      const r = await fetch(`/api/admin/nodes?page=${page}&limit=100`, { credentials: 'include', headers: { Accept: 'application/json' } })
      const j = await r.json()
      ;(j.data?.nodes ?? []).forEach((n: any) => all.push({ id: n.id, label: n.name }))
      totalPages = j.data?.pagination?.total_pages ?? 1; page++
    } while (page <= totalPages && all.length < 300)
    nodes.value = all
  } catch { /* silent */ } finally { loadingNodes.value = false }
}
async function pickNode(n: DropItem) {
  nodeSel.value = n; nodeOpen.value = false; allocSel.value = null; allocs.value = []
  loadingAllocs.value = true
  try {
    const r = await fetch(`/api/admin/allocations?node_id=${n.id}&not_used=1&limit=100`, { credentials: 'include', headers: { Accept: 'application/json' } })
    const j = await r.json()
    allocs.value = (j.data?.allocations ?? []).map((a: any) => ({
      id: a.id, label: (a.ip_alias || a.ip) + ':' + a.port + (a.notes ? '  (' + a.notes + ')' : ''),
    }))
  } catch { /* silent */ } finally { loadingAllocs.value = false }
}
function openImport(s: NormalizedServer, sourceId: number) {
  importSrv.value = s; importSrcId.value = sourceId; iName.value = ''
  iMem.value = String(s.memory || ''); iDisk.value = String(s.disk || ''); iCpu.value = String(s.cpu || '')
  spellSel.value = null; nodeSel.value = null; allocSel.value = null
  spells.value = []; nodes.value = []; allocs.value = []
  closeAllImportDrops(); showImport.value = true
  document.addEventListener('mousedown', handleImportClickOutside)
  loadSpells(); loadNodes()
}
function closeImport() { showImport.value = false; document.removeEventListener('mousedown', handleImportClickOutside) }
async function doImport() {
  if (!spellSel.value || !nodeSel.value || !allocSel.value) { toast.warning('Please select Spell, Node and Allocation'); return }
  importing.value = true
  try {
    await api.importServer({
      source_id: importSrcId.value!, server_id: importSrv.value!.id,
      name: iName.value.trim() || undefined, spell_id: spellSel.value.id,
      node_id: nodeSel.value.id, allocation_id: allocSel.value.id,
      memory: iMem.value ? parseInt(iMem.value) : undefined,
      disk: iDisk.value ? parseInt(iDisk.value) : undefined,
      cpu: iCpu.value ? parseInt(iCpu.value) : undefined,
    })
    toast.success(`"${importSrv.value!.name}" imported!`); closeImport()
  } catch (e) { toast.error(e instanceof Error ? e.message : 'Import failed') }
  finally { importing.value = false }
}

// ── Remote Servers ─────────────────────────────────────────────────────────────
const remotes      = ref<RemoteServer[]>([])
const loadingRemotes = ref(false)
const remoteStatus   = ref<Record<number, ServerStatus | null>>({})
const pinningId      = ref<number | null>(null)
let   statusInterval: ReturnType<typeof setInterval> | null = null

async function loadRemotes() {
  loadingRemotes.value = true
  try { remotes.value = await api.listRemoteServers() }
  catch (e) { toast.error(e instanceof Error ? e.message : 'Failed to load remote servers') }
  finally { loadingRemotes.value = false }
}

async function pinServer(s: NormalizedServer, sourceId: number) {
  pinningId.value = Number(s.id)
  try {
    await api.addRemoteServer({
      source_id: sourceId,
      remote_server_id: String(s.id),
      remote_server_identifier: s.identifier ?? null,
      name: s.name,
    })
    toast.success(`"${s.name}" pinned to Remote Servers`)
    if (activeTab.value === 'remote') await loadRemotes()
  } catch (e) { toast.error(e instanceof Error ? e.message : 'Pin failed') }
  finally { pinningId.value = null }
}

async function unpinServer(r: RemoteServer) {
  if (!confirm(`Remove "${r.name}" from Remote Servers?`)) return
  try {
    await api.removeRemoteServer(r.id); toast.success('Removed')
    remotes.value = remotes.value.filter(x => x.id !== r.id)
    delete remoteStatus.value[r.id]
  } catch (e) { toast.error(e instanceof Error ? e.message : 'Remove failed') }
}

async function pollStatuses() {
  for (const r of remotes.value) {
    try { remoteStatus.value[r.id] = await api.getServerStatus(r.id) }
    catch { remoteStatus.value[r.id] = null }
  }
}

async function doPower(r: RemoteServer, action: 'start' | 'stop' | 'restart' | 'kill') {
  try {
    await api.remotePowerAction(r.id, action)
    toast.success(`${action} sent to "${r.name}"`)
    setTimeout(() => pollStatuses(), 2000)
  } catch (e) { toast.error(e instanceof Error ? e.message : 'Power action failed') }
}

function startPolling() {
  stopPolling()
  pollStatuses()
  statusInterval = setInterval(pollStatuses, 5000)
}
function stopPolling() {
  if (statusInterval) { clearInterval(statusInterval); statusInterval = null }
}

// ── Control Panel modal ────────────────────────────────────────────────────────
const ctrlServer    = ref<RemoteServer | null>(null)
const ctrlTab       = ref<'overview' | 'files' | 'console' | 'allocations' | 'schedules' | 'backups' | 'databases'>('overview')
const ctrlResources = ref<ServerStatus | null>(null)
const ctrlLoadingRes = ref(false)
let   ctrlResInterval: ReturnType<typeof setInterval> | null = null

// Files
const ctrlDir       = ref('/')
const ctrlDirStack  = ref<string[]>([])
const ctrlFiles     = ref<FileEntry[]>([])
const ctrlLoadFiles = ref(false)
const ctrlOpenFile  = ref<FileEntry | null>(null)
const ctrlFileContent = ref('')
const ctrlSavingFile  = ref(false)

// Console
const ctrlCommand   = ref('')
const ctrlSending   = ref(false)

// Allocations, Schedules, Backups, Databases
const ctrlAllocs    = ref<Allocation[]>([])
const ctrlSchedules = ref<Schedule[]>([])
const ctrlBackups   = ref<Backup[]>([])
const ctrlDbs       = ref<Database[]>([])
const ctrlCreatingBackup = ref(false)
const ctrlTabLoading     = ref(false)

async function openControl(r: RemoteServer) {
  ctrlServer.value = r; ctrlTab.value = 'overview'
  ctrlDir.value = '/'; ctrlDirStack.value = []
  ctrlFiles.value = []; ctrlOpenFile.value = null; ctrlFileContent.value = ''
  ctrlCommand.value = ''; ctrlResources.value = remoteStatus.value[r.id] ?? null
  await fetchCtrlResources()
  ctrlResInterval = setInterval(fetchCtrlResources, 5000)
}
function closeControl() {
  ctrlServer.value = null
  if (ctrlResInterval) { clearInterval(ctrlResInterval); ctrlResInterval = null }
}
async function fetchCtrlResources() {
  if (!ctrlServer.value) return
  ctrlLoadingRes.value = true
  try { ctrlResources.value = await api.getServerStatus(ctrlServer.value.id) }
  catch { /* silent */ } finally { ctrlLoadingRes.value = false }
}

async function switchCtrlTab(tab: typeof ctrlTab.value) {
  ctrlTab.value = tab; ctrlTabLoading.value = true
  try {
    const id = ctrlServer.value!.id
    if (tab === 'files') await loadCtrlFiles()
    else if (tab === 'allocations') ctrlAllocs.value = await api.remoteListAllocations(id)
    else if (tab === 'schedules')  ctrlSchedules.value = await api.remoteListSchedules(id)
    else if (tab === 'backups')    ctrlBackups.value = await api.remoteListBackups(id)
    else if (tab === 'databases')  ctrlDbs.value = await api.remoteListDatabases(id)
  } catch (e) { toast.error(e instanceof Error ? e.message : 'Load failed') }
  finally { ctrlTabLoading.value = false }
}

async function loadCtrlFiles() {
  if (!ctrlServer.value) return
  ctrlLoadFiles.value = true; ctrlOpenFile.value = null
  try { ctrlFiles.value = await api.remoteListFiles(ctrlServer.value.id, ctrlDir.value) }
  catch (e) { toast.error(e instanceof Error ? e.message : 'File load failed') }
  finally { ctrlLoadFiles.value = false }
}

async function openDir(name: string) {
  ctrlDirStack.value.push(ctrlDir.value)
  ctrlDir.value = (ctrlDir.value.replace(/\/$/, '') + '/' + name).replace(/\/\//g, '/')
  await loadCtrlFiles()
}
async function goUpDir() {
  if (!ctrlDirStack.value.length) return
  ctrlDir.value = ctrlDirStack.value.pop()!
  await loadCtrlFiles()
}
async function openFile(f: FileEntry) {
  if (!ctrlServer.value) return
  ctrlOpenFile.value = f; ctrlFileContent.value = ''
  try {
    const path = (ctrlDir.value.replace(/\/$/, '') + '/' + f.name).replace(/^\/\//, '/')
    ctrlFileContent.value = await api.remoteGetFileContent(ctrlServer.value.id, path)
  } catch (e) { toast.error(e instanceof Error ? e.message : 'Failed to read file') }
}
async function saveCtrlFile() {
  if (!ctrlServer.value || !ctrlOpenFile.value) return
  ctrlSavingFile.value = true
  try {
    const path = (ctrlDir.value.replace(/\/$/, '') + '/' + ctrlOpenFile.value.name).replace(/^\/\//, '/')
    await api.remoteWriteFile(ctrlServer.value.id, path, ctrlFileContent.value)
    toast.success('File saved')
  } catch (e) { toast.error(e instanceof Error ? e.message : 'Save failed') }
  finally { ctrlSavingFile.value = false }
}

async function sendCtrlCommand() {
  if (!ctrlServer.value || !ctrlCommand.value.trim()) return
  ctrlSending.value = true
  try {
    await api.remoteSendCommand(ctrlServer.value.id, ctrlCommand.value.trim())
    toast.success('Command sent'); ctrlCommand.value = ''
  } catch (e) { toast.error(e instanceof Error ? e.message : 'Send failed') }
  finally { ctrlSending.value = false }
}

async function createCtrlBackup() {
  if (!ctrlServer.value) return
  ctrlCreatingBackup.value = true
  try {
    const b = await api.remoteCreateBackup(ctrlServer.value.id)
    ctrlBackups.value.unshift(b); toast.success('Backup created')
  } catch (e) { toast.error(e instanceof Error ? e.message : 'Backup failed') }
  finally { ctrlCreatingBackup.value = false }
}
async function deleteCtrlBackup(b: Backup) {
  if (!ctrlServer.value || !b.uuid) return
  if (!confirm(`Delete backup "${b.name}"?`)) return
  try {
    await api.remoteDeleteBackup(ctrlServer.value.id, b.uuid)
    ctrlBackups.value = ctrlBackups.value.filter(x => x.uuid !== b.uuid)
    toast.success('Backup deleted')
  } catch (e) { toast.error(e instanceof Error ? e.message : 'Delete failed') }
}

// ── Utils ──────────────────────────────────────────────────────────────────────
function fmtMem(mb: number) { return mb >= 1024 ? (mb / 1024).toFixed(1) + ' GB' : mb + ' MB' }
function fmtBytes(b: number) {
  if (b >= 1073741824) return (b / 1073741824).toFixed(1) + ' GB'
  return (b / 1048576).toFixed(1) + ' MB'
}
function statusCls(status: string) {
  const s = (status || '').toLowerCase()
  if (s === 'running' || s === 'online') return 'text-green-500'
  if (s === 'suspended') return 'text-yellow-500'
  if (s === 'offline' || s === 'stopped') return 'text-red-500'
  return 'text-muted-foreground'
}
function stateDot(state?: string) {
  const s = (state || '').toLowerCase()
  if (s === 'running' || s === 'online') return 'bg-green-500'
  if (s === 'starting') return 'bg-yellow-500'
  if (s === 'stopping') return 'bg-orange-500'
  if (s === 'offline' || s === 'stopped') return 'bg-red-500'
  return 'bg-muted-foreground'
}

onMounted(() => {
  loadSources()
})
onUnmounted(() => {
  stopPolling()
  if (ctrlResInterval) clearInterval(ctrlResInterval)
})

function switchTab(tab: 'sources' | 'remote') {
  activeTab.value = tab
  if (tab === 'remote') {
    loadRemotes().then(startPolling)
  } else {
    stopPolling()
  }
}
</script>

<template>
  <div class="w-full min-h-full overflow-auto p-4">
    <div class="mx-auto max-w-4xl space-y-4">

      <!-- Header -->
      <div class="flex items-start justify-between flex-wrap gap-3">
        <div>
          <div class="flex items-center gap-2">
            <Link class="h-5 w-5 text-primary" />
            <h1 class="text-2xl font-semibold">Apichan</h1>
          </div>
          <p class="text-sm text-muted-foreground mt-1">Connect external panels and control remote servers.</p>
        </div>
        <div class="flex gap-2">
          <button v-if="activeTab === 'sources'"
            class="inline-flex items-center gap-1.5 text-sm border border-border rounded-md px-3 py-1.5 hover:bg-muted transition-colors disabled:opacity-50"
            :disabled="loading" @click="loadSources">
            <RefreshCw class="h-3.5 w-3.5" :class="loading ? 'animate-spin' : ''" />
            Refresh
          </button>
          <button v-if="activeTab === 'sources'"
            class="inline-flex items-center gap-1.5 text-sm bg-primary text-primary-foreground rounded-md px-3 py-1.5 hover:opacity-90"
            @click="openCreateModal">
            <Plus class="h-3.5 w-3.5" />Add source
          </button>
          <button v-if="activeTab === 'remote'"
            class="inline-flex items-center gap-1.5 text-sm border border-border rounded-md px-3 py-1.5 hover:bg-muted transition-colors disabled:opacity-50"
            :disabled="loadingRemotes" @click="loadRemotes().then(startPolling)">
            <RefreshCw class="h-3.5 w-3.5" :class="loadingRemotes ? 'animate-spin' : ''" />
            Refresh
          </button>
        </div>
      </div>

      <!-- Tab bar -->
      <div class="flex gap-1 border-b border-border">
        <button @click="switchTab('sources')"
          :class="['px-4 py-2 text-sm font-medium transition-colors -mb-px border-b-2',
            activeTab === 'sources' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground']">
          Sources
        </button>
        <button @click="switchTab('remote')"
          :class="['px-4 py-2 text-sm font-medium transition-colors -mb-px border-b-2',
            activeTab === 'remote' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground']">
          Remote Servers
          <span v-if="remotes.length" class="ml-1.5 text-[10px] bg-primary/15 text-primary rounded-full px-1.5 py-0.5">{{ remotes.length }}</span>
        </button>
      </div>

      <!-- ═══════════════ SOURCES TAB ═══════════════════ -->
      <template v-if="activeTab === 'sources'">
        <div v-if="loading && !sources.length" class="flex justify-center py-20">
          <Loader2 class="h-8 w-8 animate-spin text-muted-foreground" />
        </div>
        <div v-else-if="!sources.length"
          class="flex flex-col items-center justify-center py-20 border border-dashed rounded-lg text-muted-foreground">
          <ExternalLink class="h-10 w-10 mb-3 opacity-40" />
          <p class="font-medium">No sources yet</p>
          <p class="text-xs mt-1">Add a source to start importing or pinning servers</p>
        </div>
        <div v-else class="space-y-3">
          <div v-for="source in sources" :key="source.id" class="border border-border rounded-xl overflow-hidden">
            <div class="flex items-center gap-3 px-4 py-3 bg-card">
              <button class="flex-1 flex items-center gap-3 text-left min-w-0" @click="toggleExpand(source)">
                <span class="text-[11px] font-semibold px-2 py-0.5 rounded bg-primary/10 text-primary shrink-0">{{ TYPE_LABELS[source.type] ?? source.type }}</span>
                <span class="font-semibold text-sm truncate">{{ source.name }}</span>
                <span class="text-xs text-muted-foreground truncate hidden sm:block">{{ source.url }}</span>
                <component :is="expandedId === source.id ? ChevronUp : ChevronDown" class="h-4 w-4 text-muted-foreground shrink-0 ml-auto" />
              </button>
              <button class="border border-border rounded px-2 py-1 hover:bg-muted transition-colors shrink-0" title="Edit" @click.stop="openEditModal(source)">
                <Pencil class="h-3.5 w-3.5" />
              </button>
              <button class="border border-border rounded px-2 py-1 hover:bg-muted transition-colors shrink-0" title="Delete" @click.stop="deleteSource(source)">
                <Trash2 class="h-3.5 w-3.5 text-destructive" />
              </button>
            </div>

            <!-- Server browser -->
            <div v-if="expandedId === source.id" class="border-t border-border bg-muted/10">
              <div class="p-4 space-y-3">
                <div class="flex items-center gap-2 flex-wrap">
                  <input v-model="srvSearch" type="text" placeholder="Filter by name…"
                    class="flex-1 min-w-40 rounded-md border border-border px-3 py-1.5 text-sm bg-background focus:outline-none focus:ring-2 focus:ring-primary" />
                  <button class="text-xs border border-border rounded px-2.5 py-1.5 hover:bg-muted disabled:opacity-40"
                    :disabled="srvPage <= 1 || loadingSrv" @click="changeSrvPage(-1)">← Prev</button>
                  <span class="text-xs text-muted-foreground whitespace-nowrap">{{ srvPage }}/{{ srvTotalPages }} ({{ srvTotal }})</span>
                  <button class="text-xs border border-border rounded px-2.5 py-1.5 hover:bg-muted disabled:opacity-40"
                    :disabled="srvPage >= srvTotalPages || loadingSrv" @click="changeSrvPage(1)">Next →</button>
                </div>
                <div v-if="loadingSrv" class="flex justify-center py-8"><Loader2 class="h-6 w-6 animate-spin text-muted-foreground" /></div>
                <div v-else-if="srvError" class="text-sm text-destructive bg-destructive/10 border border-destructive/20 rounded-lg px-4 py-3">{{ srvError }}</div>
                <div v-else-if="!filteredServers.length" class="text-center py-8 text-muted-foreground text-sm">
                  <Server class="h-8 w-8 mx-auto mb-2 opacity-30" />No servers found.
                </div>
                <div v-else class="rounded-lg border overflow-hidden">
                  <table class="w-full text-xs">
                    <thead class="bg-muted/50 border-b">
                      <tr>
                        <th class="text-left px-3 py-2 font-medium text-muted-foreground">Name</th>
                        <th class="text-left px-3 py-2 font-medium text-muted-foreground hidden sm:table-cell">RAM</th>
                        <th class="text-left px-3 py-2 font-medium text-muted-foreground hidden sm:table-cell">Disk</th>
                        <th class="text-left px-3 py-2 font-medium text-muted-foreground hidden md:table-cell">CPU</th>
                        <th class="text-left px-3 py-2 font-medium text-muted-foreground">Status</th>
                        <th class="w-24"></th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="s in filteredServers" :key="String(s.id)" class="border-b last:border-0 hover:bg-muted/20 transition-colors">
                        <td class="px-3 py-2 font-medium max-w-[160px] truncate">{{ s.name }}</td>
                        <td class="px-3 py-2 text-muted-foreground hidden sm:table-cell">{{ fmtMem(s.memory) }}</td>
                        <td class="px-3 py-2 text-muted-foreground hidden sm:table-cell">{{ fmtMem(s.disk) }}</td>
                        <td class="px-3 py-2 text-muted-foreground hidden md:table-cell">{{ s.cpu }}%</td>
                        <td class="px-3 py-2"><span :class="['capitalize text-[11px] font-medium', statusCls(s.status)]">{{ s.status }}</span></td>
                        <td class="px-2 py-2">
                          <div class="flex gap-1 justify-end">
                            <!-- Pin button -->
                            <button
                              :disabled="pinningId === Number(s.id)"
                              class="flex items-center justify-center w-7 h-7 rounded-md bg-purple-600 text-white hover:bg-purple-700 disabled:opacity-50"
                              title="Pin to Remote Servers" @click="pinServer(s, source.id)">
                              <Loader2 v-if="pinningId === Number(s.id)" class="h-3.5 w-3.5 animate-spin" />
                              <Pin v-else class="h-3.5 w-3.5" />
                            </button>
                            <!-- Import button -->
                            <button
                              class="flex items-center justify-center w-7 h-7 rounded-md bg-primary text-primary-foreground hover:opacity-80"
                              title="Import to this panel" @click="openImport(s, source.id)">
                              <Plus class="h-3.5 w-3.5" />
                            </button>
                          </div>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div v-if="sources.length" class="rounded-lg border bg-muted/20 p-4 text-sm text-muted-foreground">
          <p class="font-medium text-foreground mb-1">Tip</p>
          <p>Click a source to browse servers. <strong class="text-foreground">Pin</strong> (purple) to control remotely, <strong class="text-foreground">+</strong> (blue) to import into this panel.</p>
        </div>
      </template>

      <!-- ═══════════════ REMOTE SERVERS TAB ═══════════════════ -->
      <template v-else>
        <div class="text-center py-8 text-gray-400">Remote server management coming soon...</div>
      </template>

    </div>
  </div>

  <!-- ── Source modal ──────────────────────────────────────────────────────── -->
  <Teleport to="body">
    <div v-if="showSourceModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,0.55);backdrop-filter:blur(6px)" @click.self="showSourceModal = false">
      <div class="bg-card border border-border rounded-xl w-full max-w-lg shadow-2xl">
        <div class="flex items-center justify-between px-5 py-4 border-b border-border">
          <h2 class="font-semibold text-base">{{ editingSource ? 'Edit source' : 'Add source' }}</h2>
          <button class="text-muted-foreground hover:text-foreground" @click="showSourceModal = false"><X class="h-4 w-4" /></button>
        </div>
        <div class="p-5 space-y-4">
          <div>
            <label class="block text-xs font-medium text-muted-foreground mb-1.5">Name</label>
            <input v-model="sName" type="text" placeholder="My Panel" class="w-full rounded-md border border-border px-3 py-2 text-sm bg-background focus:outline-none focus:ring-2 focus:ring-primary" />
          </div>
          <div>
            <label class="block text-xs font-medium text-muted-foreground mb-1.5">Panel type</label>
            <div ref="typeDropRef" class="relative">
              <button type="button" class="w-full flex items-center justify-between rounded-md border border-border px-3 py-2 text-sm bg-background hover:bg-muted transition-colors" @click="typeDropOpen = !typeDropOpen">
                <span>{{ TYPE_LABELS[sType] }}</span>
                <ChevronDown class="h-4 w-4 text-muted-foreground transition-transform" :class="typeDropOpen ? 'rotate-180' : ''" />
              </button>
              <div v-show="typeDropOpen" class="absolute top-full left-0 right-0 z-30 mt-1 bg-card border border-border rounded-lg shadow-xl overflow-hidden">
                <button v-for="opt in TYPE_OPTIONS" :key="opt.value" type="button" class="w-full text-left px-3 py-2.5 text-sm flex items-center justify-between hover:bg-muted transition-colors" @click="pickType(opt.value)">
                  <span>{{ opt.label }}</span><Check v-if="sType === opt.value" class="h-3.5 w-3.5 text-primary" />
                </button>
              </div>
            </div>
          </div>
          <div>
            <label class="block text-xs font-medium text-muted-foreground mb-1.5">Panel URL</label>
            <input v-model="sUrl" type="url" placeholder="https://panel.example.com" class="w-full rounded-md border border-border px-3 py-2 text-sm bg-background focus:outline-none focus:ring-2 focus:ring-primary" @input="testResult = null" />
          </div>
          <div>
            <label class="block text-xs font-medium text-muted-foreground mb-1.5">API key <span v-if="editingSource" class="ml-1 font-normal">(leave blank to keep current)</span></label>
            <input v-model="sKey" type="password" placeholder="ptla_… / client token" autocomplete="new-password" class="w-full rounded-md border border-border px-3 py-2 text-sm bg-background focus:outline-none focus:ring-2 focus:ring-primary" @input="testResult = null" />
            <p class="text-xs text-muted-foreground mt-1">For remote control (power/files/console), use a <strong>client API key</strong>. Encrypted at rest with AES-256-CBC.</p>
          </div>
          <div>
            <label class="block text-xs font-medium text-muted-foreground mb-1.5">Timeout (seconds)</label>
            <input v-model.number="sTimeout" type="number" min="5" max="120" class="w-full rounded-md border border-border px-3 py-2 text-sm bg-background focus:outline-none focus:ring-2 focus:ring-primary" />
          </div>
          <div v-if="testResult" :class="['rounded-md px-3 py-2 text-xs flex items-center gap-2', testResult.ok ? 'bg-green-500/10 border border-green-500/25 text-green-600 dark:text-green-400' : 'bg-destructive/10 border border-destructive/25 text-destructive']">
            <component :is="testResult.ok ? Wifi : WifiOff" class="h-3.5 w-3.5 shrink-0" />{{ testResult.msg }}
          </div>
        </div>
        <div class="flex items-center justify-between gap-2 px-5 py-4 border-t border-border">
          <button class="inline-flex items-center gap-1.5 px-3 py-2 rounded-md text-sm border border-border hover:bg-muted disabled:opacity-50" :disabled="testing" @click="testConnection">
            <Loader2 v-if="testing" class="h-3.5 w-3.5 animate-spin" /><Wifi v-else class="h-3.5 w-3.5" />{{ testing ? 'Testing…' : 'Test connection' }}
          </button>
          <div class="flex gap-2">
            <button class="px-4 py-2 rounded-md text-sm border border-border hover:bg-muted" @click="showSourceModal = false">Cancel</button>
            <button class="inline-flex items-center gap-1.5 px-4 py-2 rounded-md text-sm bg-primary text-primary-foreground hover:opacity-90 disabled:opacity-50" :disabled="saving" @click="saveSource">
              <Loader2 v-if="saving" class="h-3.5 w-3.5 animate-spin" />{{ editingSource ? 'Save changes' : 'Add source' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>

  <!-- ── Import modal ──────────────────────────────────────────────────────── -->
  <Teleport to="body">
    <div v-if="showImport && importSrv" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,0.6);backdrop-filter:blur(6px)" @click.self="closeImport">
      <div class="bg-card border border-border rounded-xl w-full max-w-lg shadow-2xl">
        <div class="flex items-center justify-between px-5 py-4 border-b border-border">
          <div><h2 class="font-semibold text-base">Import server</h2><p class="text-xs text-muted-foreground mt-0.5">{{ importSrv.name }}</p></div>
          <button class="text-muted-foreground hover:text-foreground" @click="closeImport"><X class="h-4 w-4" /></button>
        </div>
        <div class="mx-5 mt-4 rounded-lg bg-muted/30 border border-border px-4 py-3 text-xs grid grid-cols-3 gap-2 text-muted-foreground">
          <div><span class="block font-medium text-foreground mb-0.5">RAM</span>{{ fmtMem(importSrv.memory) }}</div>
          <div><span class="block font-medium text-foreground mb-0.5">Disk</span>{{ fmtMem(importSrv.disk) }}</div>
          <div><span class="block font-medium text-foreground mb-0.5">CPU</span>{{ importSrv.cpu }}%</div>
        </div>
        <div class="p-5 space-y-3">
          <div>
            <label class="block text-xs font-medium text-muted-foreground mb-1.5">Name override <span class="font-normal">(optional)</span></label>
            <input v-model="iName" type="text" :placeholder="importSrv.name" class="w-full rounded-md border border-border px-3 py-2 text-sm bg-background focus:outline-none focus:ring-2 focus:ring-primary" />
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-muted-foreground mb-1.5">Spell / Egg <span class="text-destructive">*</span></label>
              <div ref="spellRef" class="relative">
                <button type="button" class="w-full flex items-center justify-between rounded-md border border-border px-3 py-2 text-sm bg-background hover:bg-muted transition-colors min-h-[38px]" @click="spellOpen = !spellOpen; nodeOpen = false; allocOpen = false">
                  <span :class="spellSel ? 'text-foreground' : 'text-muted-foreground'">{{ spellSel ? spellSel.label : (loadingSpells ? 'Loading…' : '— Select spell —') }}</span>
                  <ChevronDown class="h-4 w-4 text-muted-foreground shrink-0 transition-transform" :class="spellOpen ? 'rotate-180' : ''" />
                </button>
                <div v-show="spellOpen" class="absolute top-full left-0 right-0 z-30 mt-1 bg-card border border-border rounded-lg shadow-xl overflow-y-auto max-h-48">
                  <div v-if="loadingSpells" class="px-3 py-2 text-xs text-muted-foreground">Loading…</div>
                  <div v-else-if="!spells.length" class="px-3 py-2 text-xs text-muted-foreground italic">No spells found</div>
                  <button v-for="sp in spells" :key="sp.id" type="button" class="w-full text-left px-3 py-2 text-sm flex items-center justify-between hover:bg-muted transition-colors" @click="spellSel = sp; spellOpen = false">
                    <span>{{ sp.label }}</span><Check v-if="spellSel?.id === sp.id" class="h-3.5 w-3.5 text-primary shrink-0" />
                  </button>
                </div>
              </div>
            </div>
            <div>
              <label class="block text-xs font-medium text-muted-foreground mb-1.5">Node <span class="text-destructive">*</span></label>
              <div ref="nodeRef" class="relative">
                <button type="button" class="w-full flex items-center justify-between rounded-md border border-border px-3 py-2 text-sm bg-background hover:bg-muted transition-colors min-h-[38px]" @click="nodeOpen = !nodeOpen; spellOpen = false; allocOpen = false">
                  <span :class="nodeSel ? 'text-foreground' : 'text-muted-foreground'">{{ nodeSel ? nodeSel.label : (loadingNodes ? 'Loading…' : '— Select node —') }}</span>
                  <ChevronDown class="h-4 w-4 text-muted-foreground shrink-0 transition-transform" :class="nodeOpen ? 'rotate-180' : ''" />
                </button>
                <div v-show="nodeOpen" class="absolute top-full left-0 right-0 z-30 mt-1 bg-card border border-border rounded-lg shadow-xl overflow-y-auto max-h-48">
                  <div v-if="loadingNodes" class="px-3 py-2 text-xs text-muted-foreground">Loading…</div>
                  <div v-else-if="!nodes.length" class="px-3 py-2 text-xs text-muted-foreground italic">No nodes found</div>
                  <button v-for="nd in nodes" :key="nd.id" type="button" class="w-full text-left px-3 py-2 text-sm flex items-center justify-between hover:bg-muted transition-colors" @click="pickNode(nd)">
                    <span>{{ nd.label }}</span><Check v-if="nodeSel?.id === nd.id" class="h-3.5 w-3.5 text-primary shrink-0" />
                  </button>
                </div>
              </div>
            </div>
            <div class="col-span-2">
              <label class="block text-xs font-medium text-muted-foreground mb-1.5">Allocation <span class="text-destructive">*</span><span v-if="!nodeSel" class="font-normal"> — select a node first</span></label>
              <div ref="allocRef" class="relative">
                <button type="button" :disabled="!nodeSel" class="w-full flex items-center justify-between rounded-md border border-border px-3 py-2 text-sm bg-background transition-colors min-h-[38px] disabled:opacity-50 disabled:cursor-not-allowed hover:bg-muted" @click="allocOpen = !allocOpen; spellOpen = false; nodeOpen = false">
                  <span :class="allocSel ? 'text-foreground' : 'text-muted-foreground'">{{ allocSel ? allocSel.label : (loadingAllocs ? 'Loading…' : (nodeSel ? '— Select allocation —' : '— Select a node first —')) }}</span>
                  <ChevronDown class="h-4 w-4 text-muted-foreground shrink-0 transition-transform" :class="allocOpen ? 'rotate-180' : ''" />
                </button>
                <div v-show="allocOpen && nodeSel" class="absolute top-full left-0 right-0 z-30 mt-1 bg-card border border-border rounded-lg shadow-xl overflow-y-auto max-h-48">
                  <div v-if="loadingAllocs" class="px-3 py-2 text-xs text-muted-foreground">Loading…</div>
                  <div v-else-if="!allocs.length" class="px-3 py-2 text-xs text-muted-foreground italic">No free allocations on this node</div>
                  <button v-for="al in allocs" :key="al.id" type="button" class="w-full text-left px-3 py-2 text-sm flex items-center justify-between hover:bg-muted transition-colors" @click="allocSel = al; allocOpen = false">
                    <span>{{ al.label }}</span><Check v-if="allocSel?.id === al.id" class="h-3.5 w-3.5 text-primary shrink-0" />
                  </button>
                </div>
              </div>
            </div>
          </div>
          <div>
            <label class="block text-xs font-medium text-muted-foreground mb-1.5">Resources <span class="font-normal">(pre-filled from source)</span></label>
            <div class="grid grid-cols-3 gap-3">
              <div><span class="block text-[11px] text-muted-foreground mb-1">RAM (MB)</span><input v-model="iMem" type="number" min="0" class="w-full rounded-md border border-border px-3 py-2 text-sm bg-background focus:outline-none focus:ring-2 focus:ring-primary" /></div>
              <div><span class="block text-[11px] text-muted-foreground mb-1">Disk (MB)</span><input v-model="iDisk" type="number" min="0" class="w-full rounded-md border border-border px-3 py-2 text-sm bg-background focus:outline-none focus:ring-2 focus:ring-primary" /></div>
              <div><span class="block text-[11px] text-muted-foreground mb-1">CPU (%)</span><input v-model="iCpu" type="number" min="0" class="w-full rounded-md border border-border px-3 py-2 text-sm bg-background focus:outline-none focus:ring-2 focus:ring-primary" /></div>
            </div>
          </div>
          <p class="text-xs text-muted-foreground bg-yellow-500/10 border border-yellow-500/20 rounded-md px-3 py-2">
            ⚠ Only server configuration is imported — files are not transferred. Reinstall after import.
          </p>
        </div>
        <div class="flex justify-end gap-2 px-5 py-4 border-t border-border">
          <button class="px-4 py-2 rounded-md text-sm border border-border hover:bg-muted" @click="closeImport">Cancel</button>
          <button class="inline-flex items-center gap-1.5 px-4 py-2 rounded-md text-sm bg-green-600 text-white hover:bg-green-700 disabled:opacity-50" :disabled="importing" @click="doImport">
            <Loader2 v-if="importing" class="h-3.5 w-3.5 animate-spin" /><Plus v-else class="h-3.5 w-3.5" />{{ importing ? 'Importing…' : 'Import server' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>

  <!-- ── Control Panel modal ───────────────────────────────────────────────── -->
  <Teleport to="body">
    <div v-if="ctrlServer" class="fixed inset-0 z-50 flex flex-col" style="background:rgba(0,0,0,0.7);backdrop-filter:blur(6px)">
      <div class="flex flex-col flex-1 min-h-0 m-4 bg-card border border-border rounded-xl shadow-2xl overflow-hidden">
        <!-- Header -->
        <div class="flex items-center gap-3 px-5 py-3 border-b border-border shrink-0">
          <div :class="['w-2.5 h-2.5 rounded-full shrink-0', stateDot(ctrlResources?.state)]"></div>
          <h2 class="font-semibold text-base flex-1 truncate">{{ ctrlServer.name }}</h2>
          <span class="text-xs text-muted-foreground hidden sm:block">{{ ctrlServer.source_name }}</span>
          <!-- Power buttons -->
          <div class="flex gap-1">
            <button class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-md text-xs bg-green-600/15 text-green-600 hover:bg-green-600/25" @click="doPower(ctrlServer!, 'start')"><Play class="h-3 w-3" />Start</button>
            <button class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-md text-xs bg-red-600/15 text-red-600 hover:bg-red-600/25" @click="doPower(ctrlServer!, 'stop')"><Square class="h-3 w-3" />Stop</button>
            <button class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-md text-xs bg-yellow-600/15 text-yellow-600 hover:bg-yellow-600/25" @click="doPower(ctrlServer!, 'restart')"><RotateCcw class="h-3 w-3" />Restart</button>
            <button class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-md text-xs bg-orange-600/15 text-orange-600 hover:bg-orange-600/25" @click="doPower(ctrlServer!, 'kill')"><Zap class="h-3 w-3" />Kill</button>
          </div>
          <button class="text-muted-foreground hover:text-foreground ml-2" @click="closeControl"><X class="h-5 w-5" /></button>
        </div>

        <!-- Inner tab bar -->
        <div class="flex gap-1 border-b border-border px-4 shrink-0 overflow-x-auto">
          <button v-for="t in ['overview', 'files', 'console', 'allocations', 'schedules', 'backups', 'databases'] as const" :key="t"
            :class="['px-3 py-2 text-xs font-medium whitespace-nowrap transition-colors -mb-px border-b-2 capitalize', ctrlTab === t ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground']"
            @click="switchCtrlTab(t)">{{ t }}</button>
        </div>

        <!-- Tab content -->
        <div class="flex-1 min-h-0 overflow-auto p-5">
          <div v-if="ctrlTabLoading" class="flex justify-center py-16"><Loader2 class="h-7 w-7 animate-spin text-muted-foreground" /></div>

          <!-- Overview -->
          <template v-else-if="ctrlTab === 'overview'">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <div v-for="[label, pct, val] in [
                ['CPU', Math.min(ctrlResources?.cpu ?? 0, 100), (ctrlResources?.cpu ?? 0).toFixed(1) + '%'],
                ['Memory', ctrlResources ? Math.min((ctrlResources.memory_mb / 2048) * 100, 100) : 0, (ctrlResources?.memory_mb ?? 0).toFixed(0) + ' MB'],
                ['Disk', ctrlResources ? Math.min((ctrlResources.disk_mb / 10240) * 100, 100) : 0, (ctrlResources?.disk_mb ?? 0).toFixed(0) + ' MB'],
              ]" :key="label" class="rounded-lg border border-border p-4">
                <div class="flex items-center justify-between mb-2">
                  <span class="text-sm font-medium">{{ label }}</span>
                  <span class="text-sm text-muted-foreground">{{ val }}</span>
                </div>
                <div class="h-2 rounded-full bg-muted overflow-hidden">
                  <div class="h-full rounded-full bg-primary transition-all" :style="{ width: pct + '%' }"></div>
                </div>
              </div>
            </div>
            <div v-if="ctrlResources" class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs text-muted-foreground">
              <div class="rounded-lg border border-border p-3"><p class="font-medium text-foreground mb-1">State</p><span :class="['capitalize font-medium', stateDot(ctrlResources.state).replace('bg-', 'text-')]">{{ ctrlResources.state }}</span></div>
              <div class="rounded-lg border border-border p-3"><p class="font-medium text-foreground mb-1">Uptime</p>{{ ctrlResources.uptime > 0 ? Math.floor(ctrlResources.uptime / 60) + 'm' : '—' }}</div>
              <div class="rounded-lg border border-border p-3"><p class="font-medium text-foreground mb-1">Net ↓</p>{{ ctrlResources.net_rx_mb.toFixed(1) }} MB</div>
              <div class="rounded-lg border border-border p-3"><p class="font-medium text-foreground mb-1">Net ↑</p>{{ ctrlResources.net_tx_mb.toFixed(1) }} MB</div>
            </div>
            <div v-else class="mt-4 text-sm text-muted-foreground flex items-center gap-2"><Loader2 class="h-4 w-4 animate-spin" />Loading resources…</div>
          </template>

          <!-- Files -->
          <template v-else-if="ctrlTab === 'files'">
            <template v-if="!ctrlOpenFile">
              <!-- Breadcrumb -->
              <div class="flex items-center gap-1 text-xs text-muted-foreground mb-3 flex-wrap">
                <button v-if="ctrlDirStack.length" class="hover:text-foreground" @click="goUpDir">←</button>
                <span class="font-mono">{{ ctrlDir }}</span>
              </div>
              <div v-if="ctrlLoadFiles" class="flex justify-center py-10"><Loader2 class="h-6 w-6 animate-spin text-muted-foreground" /></div>
              <div v-else-if="!ctrlFiles.length" class="text-center py-10 text-muted-foreground text-sm">Empty directory</div>
              <div v-else class="rounded-lg border overflow-hidden">
                <table class="w-full text-xs">
                  <thead class="bg-muted/50 border-b">
                    <tr>
                      <th class="text-left px-3 py-2 font-medium text-muted-foreground">Name</th>
                      <th class="text-left px-3 py-2 font-medium text-muted-foreground hidden sm:table-cell">Size</th>
                      <th class="text-left px-3 py-2 font-medium text-muted-foreground hidden sm:table-cell">Modified</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="f in ctrlFiles" :key="f.name" class="border-b last:border-0 hover:bg-muted/20 transition-colors cursor-pointer" @click="f.is_file ? openFile(f) : openDir(f.name)">
                      <td class="px-3 py-2">
                        <div class="flex items-center gap-2">
                          <component :is="f.is_file ? File : Folder" :class="['h-3.5 w-3.5 shrink-0', f.is_file ? 'text-muted-foreground' : 'text-yellow-500']" />
                          <span class="font-mono truncate max-w-[200px]">{{ f.name }}</span>
                        </div>
                      </td>
                      <td class="px-3 py-2 text-muted-foreground hidden sm:table-cell">{{ f.is_file ? fmtBytes(f.size) : '—' }}</td>
                      <td class="px-3 py-2 text-muted-foreground hidden sm:table-cell">{{ f.modified_at ? new Date(f.modified_at).toLocaleDateString() : '—' }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </template>
            <!-- File editor -->
            <template v-else>
              <div class="flex items-center gap-2 mb-3">
                <button class="text-xs border border-border rounded px-2.5 py-1.5 hover:bg-muted" @click="ctrlOpenFile = null">← Back</button>
                <span class="text-xs font-mono text-muted-foreground flex-1 truncate">{{ ctrlOpenFile.name }}</span>
                <button class="inline-flex items-center gap-1.5 text-xs px-3 py-1.5 rounded-md bg-primary text-primary-foreground hover:opacity-90 disabled:opacity-50" :disabled="ctrlSavingFile" @click="saveCtrlFile">
                  <Loader2 v-if="ctrlSavingFile" class="h-3 w-3 animate-spin" />{{ ctrlSavingFile ? 'Saving…' : 'Save' }}
                </button>
              </div>
              <textarea v-model="ctrlFileContent" class="w-full h-[calc(100vh-380px)] min-h-64 font-mono text-xs rounded-lg border border-border p-3 bg-background focus:outline-none focus:ring-2 focus:ring-primary resize-none" spellcheck="false"></textarea>
            </template>
          </template>

          <!-- Console -->
          <template v-else-if="ctrlTab === 'console'">
            <div class="rounded-lg border border-border bg-black/50 p-4 mb-4 min-h-32 flex items-center justify-center">
              <p class="text-xs text-muted-foreground text-center">Live console output requires a WebSocket connection.<br>Use the command box below to send commands.</p>
            </div>
            <div class="flex gap-2">
              <input v-model="ctrlCommand" type="text" placeholder="Enter command…" class="flex-1 rounded-md border border-border px-3 py-2 text-sm bg-background font-mono focus:outline-none focus:ring-2 focus:ring-primary"
                @keydown.enter="sendCtrlCommand" />
              <button class="inline-flex items-center gap-1.5 px-4 py-2 rounded-md text-sm bg-primary text-primary-foreground hover:opacity-90 disabled:opacity-50" :disabled="ctrlSending" @click="sendCtrlCommand">
                <Loader2 v-if="ctrlSending" class="h-3.5 w-3.5 animate-spin" /><Terminal v-else class="h-3.5 w-3.5" />Send
              </button>
            </div>
          </template>

          <!-- Allocations -->
          <template v-else-if="ctrlTab === 'allocations'">
            <div v-if="!ctrlAllocs.length" class="text-center py-10 text-muted-foreground text-sm">No allocations found</div>
            <div v-else class="rounded-lg border overflow-hidden">
              <table class="w-full text-xs">
                <thead class="bg-muted/50 border-b">
                  <tr>
                    <th class="text-left px-3 py-2 font-medium text-muted-foreground">IP</th>
                    <th class="text-left px-3 py-2 font-medium text-muted-foreground">Port</th>
                    <th class="text-left px-3 py-2 font-medium text-muted-foreground">Notes</th>
                    <th class="text-left px-3 py-2 font-medium text-muted-foreground">Default</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="a in ctrlAllocs" :key="String(a.id)" class="border-b last:border-0 hover:bg-muted/10">
                    <td class="px-3 py-2 font-mono">{{ a.ip_alias || a.ip }}</td>
                    <td class="px-3 py-2 font-mono">{{ a.port }}</td>
                    <td class="px-3 py-2 text-muted-foreground">{{ a.notes || '—' }}</td>
                    <td class="px-3 py-2"><Check v-if="a.is_default" class="h-3.5 w-3.5 text-green-500" /></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </template>

          <!-- Schedules -->
          <template v-else-if="ctrlTab === 'schedules'">
            <div v-if="!ctrlSchedules.length" class="text-center py-10 text-muted-foreground text-sm">No schedules found</div>
            <div v-else class="space-y-2">
              <div v-for="s in ctrlSchedules" :key="String(s.id)" class="rounded-lg border border-border p-3">
                <div class="flex items-center justify-between">
                  <p class="font-medium text-sm">{{ s.name }}</p>
                  <span :class="['text-xs px-1.5 py-0.5 rounded', s.is_active ? 'bg-green-500/15 text-green-600' : 'bg-muted text-muted-foreground']">{{ s.is_active ? 'Active' : 'Paused' }}</span>
                </div>
                <p class="text-xs text-muted-foreground mt-1 font-mono">{{ s.cron_minute }} {{ s.cron_hour }} {{ s.cron_day_of_month }} {{ s.cron_month }} {{ s.cron_day_of_week }}</p>
                <p v-if="s.next_run_at" class="text-xs text-muted-foreground mt-0.5">Next: {{ new Date(s.next_run_at).toLocaleString() }}</p>
              </div>
            </div>
          </template>

          <!-- Backups -->
          <template v-else-if="ctrlTab === 'backups'">
            <div class="flex justify-end mb-3">
              <button class="inline-flex items-center gap-1.5 text-sm px-3 py-1.5 rounded-md bg-primary text-primary-foreground hover:opacity-90 disabled:opacity-50" :disabled="ctrlCreatingBackup" @click="createCtrlBackup">
                <Loader2 v-if="ctrlCreatingBackup" class="h-3.5 w-3.5 animate-spin" /><Plus v-else class="h-3.5 w-3.5" />Create Backup
              </button>
            </div>
            <div v-if="!ctrlBackups.length" class="text-center py-10 text-muted-foreground text-sm">No backups found</div>
            <div v-else class="space-y-2">
              <div v-for="b in ctrlBackups" :key="String(b.uuid)" class="flex items-center gap-3 rounded-lg border border-border p-3">
                <div class="flex-1 min-w-0">
                  <p class="font-medium text-sm truncate">{{ b.name }}</p>
                  <p class="text-xs text-muted-foreground">{{ b.bytes > 0 ? fmtBytes(b.bytes) : '—' }} · {{ b.created_at ? new Date(b.created_at).toLocaleString() : '—' }}</p>
                </div>
                <span :class="['text-xs px-1.5 py-0.5 rounded', b.is_successful ? 'bg-green-500/15 text-green-600' : 'bg-yellow-500/15 text-yellow-600']">{{ b.is_successful ? 'Done' : 'Pending' }}</span>
                <button class="text-destructive hover:text-destructive/80" @click="deleteCtrlBackup(b)"><Trash2 class="h-4 w-4" /></button>
              </div>
            </div>
          </template>

          <!-- Databases -->
          <template v-else-if="ctrlTab === 'databases'">
            <div v-if="!ctrlDbs.length" class="text-center py-10 text-muted-foreground text-sm">No databases found</div>
            <div v-else class="rounded-lg border overflow-hidden">
              <table class="w-full text-xs">
                <thead class="bg-muted/50 border-b">
                  <tr>
                    <th class="text-left px-3 py-2 font-medium text-muted-foreground">Name</th>
                    <th class="text-left px-3 py-2 font-medium text-muted-foreground hidden sm:table-cell">Username</th>
                    <th class="text-left px-3 py-2 font-medium text-muted-foreground hidden md:table-cell">Host</th>
                    <th class="text-left px-3 py-2 font-medium text-muted-foreground">Port</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="d in ctrlDbs" :key="String(d.id)" class="border-b last:border-0 hover:bg-muted/10">
                    <td class="px-3 py-2 font-mono">{{ d.name }}</td>
                    <td class="px-3 py-2 text-muted-foreground hidden sm:table-cell font-mono">{{ d.username }}</td>
                    <td class="px-3 py-2 text-muted-foreground hidden md:table-cell font-mono">{{ d.host }}</td>
                    <td class="px-3 py-2 font-mono">{{ d.port }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </template>

        </div>
      </div>
    </div>
  </Teleport>
</template>
