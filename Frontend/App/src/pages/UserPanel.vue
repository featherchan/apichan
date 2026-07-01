<script setup lang="ts">
import { ref, computed, nextTick, onMounted, onUnmounted, watch } from 'vue'
import { useToast } from 'vue-toastification'
import {
  useApichanAPI,
  type Source, type SourceType, type NormalizedServer,
  type RemoteServer, type ServerStatus, type FileEntry,
  type Allocation, type Schedule, type Backup, type Database,
  type StartupConfig, type StartupVariable,
} from '@/composables/useApichanAPI'
import {
  Link, Plus, Trash2, Pencil, RefreshCw, Loader2, ExternalLink,
  ChevronDown, ChevronUp, Server, X, Wifi, WifiOff, Check,
  Play, Square, RotateCcw, Zap, Folder, File, Terminal,
  Pin, PinOff, Send, LayoutDashboard, HardDrive, Cpu,
  MemoryStick, FolderPlus, FileArchive, ArrowLeft, FolderOpen,
  Settings, Shield, Clock, Database as DatabaseIcon, Network,
  ChevronRight, Power, MoreVertical, Eye, EyeOff,
} from 'lucide-vue-next'
import SharedRemoteServerView from './SharedRemoteServerView.vue'

const toast = useToast()
const api   = useApichanAPI()

// ── Main tabs ──────────────────────────────────────────────────────────────────
const mainTab = ref<'sources' | 'remote'>('sources')

const TYPE_OPTIONS: { value: SourceType; label: string }[] = [
  { value: 'pterodactyl', label: 'Pterodactyl' },
  { value: 'featherpanel', label: 'FeatherPanel' },
  { value: 'pelican', label: 'Pelican' },
  { value: 'calagopus', label: 'Calagopus' },
]
const TYPE_LABELS = Object.fromEntries(TYPE_OPTIONS.map(o => [o.value, o.label]))

// ── User Sources ───────────────────────────────────────────────────────────────
const sources    = ref<Source[]>([])
const loadingSrc = ref(false)
const expandedId = ref<number | null>(null)
const srv        = ref<NormalizedServer[]>([])
const srvPage    = ref(1); const srvTotal = ref(0); const srvTotalPages = ref(1)
const srvSearch  = ref(''); const loadingSrv = ref(false); const srvError = ref('')
const pinningId  = ref<number | null>(null)

async function loadSources() {
  loadingSrc.value = true
  try { sources.value = await api.listUserSources() }
  catch (e) { toast.error(e instanceof Error ? e.message : 'Failed to load sources') }
  finally { loadingSrc.value = false }
}
async function toggleExpand(s: Source) {
  if (expandedId.value === s.id) { expandedId.value = null; return }
  expandedId.value = s.id; srvPage.value = 1; srvSearch.value = ''
  await fetchServers(s.id)
}
async function fetchServers(sourceId: number) {
  loadingSrv.value = true; srvError.value = ''
  try {
    const d = await api.listUserSourceServers(sourceId, srvPage.value)
    srv.value = d.servers ?? []; srvTotal.value = d.total ?? srv.value.length
    srvTotalPages.value = d.total_pages ?? 1
  } catch (e) {
    srvError.value = e instanceof Error ? e.message : 'Failed'; srv.value = []
  } finally { loadingSrv.value = false }
}
const filteredSrv = computed(() => {
  const q = srvSearch.value.toLowerCase()
  return q ? srv.value.filter(s => s.name.toLowerCase().includes(q)) : srv.value
})
async function changeSrvPage(delta: number) {
  if (expandedId.value === null) return
  srvPage.value = Math.max(1, Math.min(srvTotalPages.value, srvPage.value + delta))
  await fetchServers(expandedId.value)
}
async function pinServer(s: NormalizedServer, sourceId: number) {
  pinningId.value = Number(s.id)
  try {
    await api.addRemoteServer({ source_id: sourceId, remote_server_id: String(s.id), remote_server_identifier: s.identifier ?? null, name: s.name })
    toast.success(`"${s.name}" pinned to Remote Servers`)
  } catch (e) { toast.error(e instanceof Error ? e.message : 'Pin failed') }
  finally { pinningId.value = null }
}

// ── Source modal ───────────────────────────────────────────────────────────────
const showSrcModal = ref(false)
const editingSrc   = ref<Source | null>(null)
const sName = ref(''); const sType = ref<SourceType>('pterodactyl')
const sUrl  = ref(''); const sKey  = ref(''); const sTimeout = ref(15)
const saving = ref(false); const testing = ref(false)
const testResult = ref<{ ok: boolean; msg: string } | null>(null)
const showKey = ref(false)
const typeDropOpen = ref(false); const typeDropRef = ref<HTMLElement | null>(null)

function handleClickOutsideType(e: MouseEvent) {
  if (typeDropRef.value && !typeDropRef.value.contains(e.target as Node)) typeDropOpen.value = false
}
onMounted(() => {
  document.addEventListener('mousedown', handleClickOutsideType)
  loadSources()
})
onUnmounted(() => {
  document.removeEventListener('mousedown', handleClickOutsideType)
  stopPolling()
  if (ctrlResInterval) clearInterval(ctrlResInterval)
  disconnectConsole()
})

function openCreateModal() {
  editingSrc.value = null; sName.value = ''; sType.value = 'pterodactyl'
  sUrl.value = ''; sKey.value = ''; sTimeout.value = 15; testResult.value = null; showKey.value = false
  showSrcModal.value = true
}
function openEditModal(s: Source) {
  editingSrc.value = s; sName.value = s.name; sType.value = s.type as SourceType
  sUrl.value = s.url; sKey.value = ''; sTimeout.value = s.timeout; testResult.value = null; showKey.value = false
  showSrcModal.value = true
}
async function testConnection() {
  if (!sUrl.value.trim() || !sKey.value.trim()) { toast.warning('Enter URL and API key first'); return }
  testing.value = true; testResult.value = null
  try {
    const d = await api.testUserConnection({ type: sType.value, url: sUrl.value.trim(), api_key: sKey.value.trim(), timeout: sTimeout.value })
    testResult.value = { ok: true, msg: `Connected — ${d.server_count} server(s)` }
  } catch (e) {
    testResult.value = { ok: false, msg: e instanceof Error ? e.message : 'Failed' }
  } finally { testing.value = false }
}
async function saveSrc() {
  if (!sName.value.trim() || !sUrl.value.trim()) { toast.warning('Name and URL are required'); return }
  if (!editingSrc.value && !sKey.value.trim()) { toast.warning('API key is required'); return }
  saving.value = true
  try {
    if (editingSrc.value) {
      const p: Parameters<typeof api.updateUserSource>[1] = { name: sName.value.trim(), type: sType.value, url: sUrl.value.trim(), timeout: sTimeout.value }
      if (sKey.value.trim()) p.api_key = sKey.value.trim()
      await api.updateUserSource(editingSrc.value.id, p); toast.success('Source updated')
    } else {
      await api.createUserSource({ name: sName.value.trim(), type: sType.value, url: sUrl.value.trim(), api_key: sKey.value.trim(), timeout: sTimeout.value })
      toast.success('Source added')
    }
    showSrcModal.value = false; await loadSources()
  } catch (e) { toast.error(e instanceof Error ? e.message : 'Save failed') }
  finally { saving.value = false }
}
async function deleteSrc(s: Source) {
  if (!confirm(`Delete source "${s.name}"?`)) return
  try {
    await api.deleteUserSource(s.id); toast.success('Deleted')
    if (expandedId.value === s.id) expandedId.value = null
    await loadSources()
  } catch (e) { toast.error(e instanceof Error ? e.message : 'Delete failed') }
}

// ── Remote Servers ─────────────────────────────────────────────────────────────
const remotes    = ref<RemoteServer[]>([])
const loadingRem = ref(false)
const remStatus  = ref<Record<number, ServerStatus | null>>({})
let   statusInterval: ReturnType<typeof setInterval> | null = null

async function loadRemotes() {
  loadingRem.value = true
  try { remotes.value = await api.listRemoteServers() }
  catch (e) { toast.error(e instanceof Error ? e.message : 'Failed') }
  finally { loadingRem.value = false }
}
async function pollStatuses() {
  for (const r of remotes.value) {
    try { remStatus.value[r.id] = await api.getServerStatus(r.id) } catch { remStatus.value[r.id] = null }
  }
}
function startPolling() { stopPolling(); pollStatuses(); statusInterval = setInterval(pollStatuses, 5000) }
function stopPolling() { if (statusInterval) { clearInterval(statusInterval); statusInterval = null } }

async function unpinServer(r: RemoteServer) {
  if (!confirm(`Remove "${r.name}" from remote servers?`)) return
  try {
    await api.removeRemoteServer(r.id); remotes.value = remotes.value.filter(x => x.id !== r.id)
    delete remStatus.value[r.id]; toast.success('Removed')
  } catch (e) { toast.error(e instanceof Error ? e.message : 'Failed') }
}
async function doPower(r: RemoteServer, action: 'start' | 'stop' | 'restart' | 'kill') {
  try { await api.remotePowerAction(r.id, action); toast.success(`${action} sent`); setTimeout(pollStatuses, 2000) }
  catch (e) { toast.error(e instanceof Error ? e.message : 'Failed') }
}

function switchMainTab(tab: 'sources' | 'remote') {
  mainTab.value = tab
  if (tab === 'remote') loadRemotes().then(startPolling)
  else stopPolling()
}

// ── Control Panel ──────────────────────────────────────────────────────────────
type CtrlTab = 'overview' | 'console' | 'files' | 'startup' | 'allocations' | 'schedules' | 'backups' | 'databases'

const ctrlServer    = ref<RemoteServer | null>(null)
const ctrlTab       = ref<CtrlTab>('overview')
const ctrlResources = ref<ServerStatus | null>(null)
const ctrlTabLoading = ref(false)
let   ctrlResInterval: ReturnType<typeof setInterval> | null = null

const CTRL_TABS: { id: CtrlTab; label: string; icon: any }[] = [
  { id: 'overview',    label: 'Overview',    icon: LayoutDashboard },
  { id: 'console',     label: 'Console',     icon: Terminal },
  { id: 'files',       label: 'Files',       icon: FolderOpen },
  { id: 'startup',     label: 'Startup',     icon: Settings },
  { id: 'allocations', label: 'Network',     icon: Network },
  { id: 'schedules',   label: 'Schedules',   icon: Clock },
  { id: 'backups',     label: 'Backups',     icon: HardDrive },
  { id: 'databases',   label: 'Databases',   icon: DatabaseIcon },
]

async function openControl(r: RemoteServer) {
  ctrlServer.value = r; ctrlTab.value = 'overview'
  resetFileState(); resetConsoleState(); resetStartupState()
  ctrlResources.value = remStatus.value[r.id] ?? null
  ctrlAllocs.value = []; ctrlSchedules.value = []; ctrlBackups.value = []; ctrlDbs.value = []
  if (ctrlResInterval) clearInterval(ctrlResInterval)
  ctrlResInterval = setInterval(fetchCtrlResources, 5000)
  await fetchCtrlResources()
}
function closeControl() {
  ctrlServer.value = null
  if (ctrlResInterval) { clearInterval(ctrlResInterval); ctrlResInterval = null }
  disconnectConsole()
}
async function fetchCtrlResources() {
  if (!ctrlServer.value) return
  try { ctrlResources.value = await api.getServerStatus(ctrlServer.value.id) } catch { /* silent */ }
}
async function switchCtrlTab(tab: CtrlTab) {
  if (tab !== 'console') disconnectConsole()
  ctrlTab.value = tab; ctrlTabLoading.value = true
  try {
    const id = ctrlServer.value!.id
    if (tab === 'console')     { ctrlTabLoading.value = false; connectConsole(); return }
    if (tab === 'files')       { await loadCtrlFiles(); return }
    if (tab === 'startup')     { ctrlStartup.value = await api.remoteGetStartup(id); return }
    if (tab === 'allocations') { ctrlAllocs.value   = await api.remoteListAllocations(id); return }
    if (tab === 'schedules')   { ctrlSchedules.value = await api.remoteListSchedules(id); return }
    if (tab === 'backups')     { ctrlBackups.value   = await api.remoteListBackups(id); return }
    if (tab === 'databases')   { ctrlDbs.value       = await api.remoteListDatabases(id); return }
  } catch (e) { toast.error(e instanceof Error ? e.message : 'Load failed') }
  finally { ctrlTabLoading.value = false }
}

// ── Console ────────────────────────────────────────────────────────────────────
const terminalEl        = ref<HTMLDivElement | null>(null)
const consoleLines      = ref<string[]>([])
const consoleWs         = ref<WebSocket | null>(null)
const consoleConnected  = ref(false)
const consoleConnecting = ref(false)
const ctrlCommand       = ref('')
const ctrlSending       = ref(false)

// Ping (round-trip latency to the remote panel over the console WebSocket)
const consolePingMs   = ref<number | null>(null)
const pingSentAt      = ref<number | null>(null)
const pingIntervalId  = ref<ReturnType<typeof setInterval> | null>(null)

function startPingLoop(ws: WebSocket) {
  stopPingLoop()
  pingIntervalId.value = setInterval(() => {
    if (ws.readyState !== WebSocket.OPEN) return
    pingSentAt.value = performance.now()
    ws.send(JSON.stringify({ event: 'send stats', args: [null] }))
  }, 5000)
}
function stopPingLoop() {
  if (pingIntervalId.value !== null) { clearInterval(pingIntervalId.value); pingIntervalId.value = null }
  pingSentAt.value = null
  consolePingMs.value = null
}

function resetConsoleState() {
  disconnectConsole(); consoleLines.value = []; ctrlCommand.value = ''
}

const COLOR_MAP: Record<string, string> = {
  '0': '</span>', '1': '<span style="font-weight:700">',
  '2': '<span style="opacity:.6">',
  '30': '<span style="color:#555f6e">', '31': '<span style="color:#f87171">',
  '32': '<span style="color:#4ade80">', '33': '<span style="color:#fbbf24">',
  '34': '<span style="color:#60a5fa">', '35': '<span style="color:#c084fc">',
  '36': '<span style="color:#34d399">', '37': '<span style="color:#f1f5f9">',
  '90': '<span style="color:#6b7280">', '91': '<span style="color:#fca5a5">',
  '92': '<span style="color:#86efac">', '93': '<span style="color:#fde68a">',
  '94': '<span style="color:#93c5fd">', '95': '<span style="color:#d8b4fe">',
  '96': '<span style="color:#6ee7b7">', '97': '<span style="color:#ffffff">',
}

function ansiToHtml(line: string): string {
  const escaped = line.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
  return escaped.replace(/\x1b\[(\d+(?:;\d+)*)m/g, (_, codes: string) =>
    codes.split(';').map(c => COLOR_MAP[c] ?? '').join('')
  )
}
function appendLine(raw: string) {
  consoleLines.value.push(ansiToHtml(raw))
  if (consoleLines.value.length > 1000) consoleLines.value.splice(0, 300)
  nextTick(() => { if (terminalEl.value) terminalEl.value.scrollTop = terminalEl.value.scrollHeight })
}

function connectConsole() {
  if (consoleConnected.value || consoleConnecting.value) return
  consoleConnecting.value = true
  appendLine('\x1b[90m[Apichan] Connecting…\x1b[0m')
  api.getRemoteWebsocket(ctrlServer.value!.id).then(({ token, socket }) => {
    if (!socket) throw new Error('No WebSocket URL returned by panel')
    const ws = new WebSocket(socket)
    consoleWs.value = ws
    ws.onopen = () => ws.send(JSON.stringify({ event: 'auth', args: [token] }))
    ws.onmessage = (ev) => {
      try {
        const msg = JSON.parse(ev.data)
        if (msg.event === 'auth success') {
          consoleConnected.value = true; consoleConnecting.value = false
          appendLine('\x1b[32m[Apichan] Connected.\x1b[0m')
          ws.send(JSON.stringify({ event: 'send logs', args: [null] }))
          ws.send(JSON.stringify({ event: 'send stats', args: [null] }))
          startPingLoop(ws)
        } else if (msg.event === 'console output') {
          const lines: string[] = Array.isArray(msg.args) ? msg.args : []
          lines.forEach(l => appendLine(l))
        } else if (msg.event === 'stats') {
          if (pingSentAt.value !== null) {
            consolePingMs.value = Math.round(performance.now() - pingSentAt.value)
            pingSentAt.value = null
          }
          try {
            const s = JSON.parse(Array.isArray(msg.args) ? msg.args[0] : msg.args)
            if (s && ctrlResources.value) {
              ctrlResources.value = {
                state:     s.state ?? ctrlResources.value.state,
                cpu:       parseFloat(s.cpu_absolute ?? s.cpu ?? 0),
                memory_mb: Math.round((s.memory_bytes ?? 0) / 1048576),
                disk_mb:   Math.round((s.disk_bytes ?? 0) / 1048576),
                net_rx_mb: Math.round((s.network?.rx_bytes ?? 0) / 1048576),
                net_tx_mb: Math.round((s.network?.tx_bytes ?? 0) / 1048576),
                uptime:    s.uptime ?? 0,
              }
            }
          } catch { /* ignore malformed stats */ }
        } else if (msg.event === 'status') {
          const newState = Array.isArray(msg.args) ? msg.args[0] : msg.args
          if (ctrlResources.value && newState) ctrlResources.value.state = newState
        } else if (msg.event === 'token expiring') {
          ws.send(JSON.stringify({ event: 'auth', args: [token] }))
        }
      } catch { /* ignore non-JSON */ }
    }
    ws.onerror = () => {
      consoleConnecting.value = false; consoleConnected.value = false
      stopPingLoop()
      appendLine('\x1b[31m[Apichan] WebSocket error.\x1b[0m')
    }
    ws.onclose = (ev) => {
      consoleConnecting.value = false; consoleConnected.value = false
      stopPingLoop()
      if (ev.code !== 1000) appendLine('\x1b[90m[Apichan] Disconnected.\x1b[0m')
    }
  }).catch(e => {
    consoleConnecting.value = false
    appendLine(`\x1b[31m[Apichan] Failed: ${e instanceof Error ? e.message : 'Unknown error'}\x1b[0m`)
  })
}
function disconnectConsole() {
  if (consoleWs.value) { consoleWs.value.close(1000); consoleWs.value = null }
  consoleConnected.value = false; consoleConnecting.value = false
  stopPingLoop()
}
async function sendCtrlCommand() {
  const cmd = ctrlCommand.value.trim(); if (!cmd) return
  ctrlCommand.value = ''
  if (consoleWs.value && consoleConnected.value) {
    consoleWs.value.send(JSON.stringify({ event: 'send command', args: [cmd] }))
  } else {
    ctrlSending.value = true
    try {
      await api.remoteSendCommand(ctrlServer.value!.id, cmd)
      appendLine(`\x1b[90m[REST] Sent: ${cmd}\x1b[0m`)
    } catch (e) { toast.error(e instanceof Error ? e.message : 'Send failed') }
    finally { ctrlSending.value = false }
  }
}

// ── Files ──────────────────────────────────────────────────────────────────────
const ctrlDir        = ref('/')
const ctrlDirStack   = ref<string[]>([])
const ctrlFiles      = ref<FileEntry[]>([])
const ctrlLoadFiles  = ref(false)
const ctrlOpenFile   = ref<FileEntry | null>(null)
const ctrlFilePath   = ref('')
const ctrlFileContent = ref('')
const ctrlSavingFile = ref(false)
const selectedFiles  = ref<Set<string>>(new Set())
const renameTarget   = ref<string | null>(null)
const renameValue    = ref('')
const renamingFile   = ref(false)
const newFolderMode     = ref(false)
const newFolderName     = ref('')
const creatingFolder    = ref(false)
const newFolderInputRef = ref<HTMLInputElement | null>(null)

function resetFileState() {
  ctrlDir.value = '/'; ctrlDirStack.value = []; ctrlFiles.value = []
  ctrlOpenFile.value = null; ctrlFilePath.value = ''; ctrlFileContent.value = ''
  selectedFiles.value = new Set(); renameTarget.value = null; newFolderMode.value = false
}

function joinPath(base: string, name: string): string {
  return (base.replace(/\/$/, '') + '/' + name).replace(/\/\//g, '/')
}

async function loadCtrlFiles() {
  if (!ctrlServer.value) return
  ctrlLoadFiles.value = true; selectedFiles.value = new Set(); renameTarget.value = null
  try {
    ctrlFiles.value = await api.remoteListFiles(ctrlServer.value.id, ctrlDir.value)
    ctrlFiles.value.sort((a, b) => {
      if (!a.is_file && b.is_file) return -1
      if (a.is_file && !b.is_file) return 1
      return a.name.localeCompare(b.name)
    })
  } catch (e) { toast.error(e instanceof Error ? e.message : 'Load failed') }
  finally { ctrlLoadFiles.value = false; ctrlTabLoading.value = false }
}
async function enterDir(name: string) {
  ctrlDirStack.value.push(ctrlDir.value)
  ctrlDir.value = joinPath(ctrlDir.value, name)
  await loadCtrlFiles()
}
async function goUpDir() {
  if (!ctrlDirStack.value.length) return
  ctrlDir.value = ctrlDirStack.value.pop()!; await loadCtrlFiles()
}
async function goToStack(idx: number) {
  const path = ctrlDirStack.value[idx]
  ctrlDirStack.value = ctrlDirStack.value.slice(0, idx)
  ctrlDir.value = path; await loadCtrlFiles()
}
async function openFile(f: FileEntry) {
  ctrlOpenFile.value = f; ctrlFileContent.value = ''
  ctrlFilePath.value = joinPath(ctrlDir.value, f.name)
  try {
    ctrlFileContent.value = await api.remoteGetFileContent(ctrlServer.value!.id, ctrlFilePath.value)
  } catch (e) { toast.error(e instanceof Error ? e.message : 'Failed to read file') }
}
async function saveCtrlFile() {
  if (!ctrlServer.value || !ctrlOpenFile.value) return
  ctrlSavingFile.value = true
  try {
    await api.remoteWriteFile(ctrlServer.value.id, ctrlFilePath.value, ctrlFileContent.value)
    toast.success('File saved')
  } catch (e) { toast.error(e instanceof Error ? e.message : 'Save failed') }
  finally { ctrlSavingFile.value = false }
}

function toggleSelect(name: string) {
  const s = new Set(selectedFiles.value)
  s.has(name) ? s.delete(name) : s.add(name)
  selectedFiles.value = s
}
function toggleSelectAll() {
  if (selectedFiles.value.size === ctrlFiles.value.length) {
    selectedFiles.value = new Set()
  } else {
    selectedFiles.value = new Set(ctrlFiles.value.map(f => f.name))
  }
}

async function deleteSelected() {
  if (!ctrlServer.value || !selectedFiles.value.size) return
  const names = [...selectedFiles.value]
  if (!confirm(`Delete ${names.length} item(s)?`)) return
  try {
    await api.remoteDeleteFiles(ctrlServer.value.id, ctrlDir.value, names)
    toast.success(`Deleted ${names.length} item(s)`)
    await loadCtrlFiles()
  } catch (e) { toast.error(e instanceof Error ? e.message : 'Delete failed') }
}

function startRename(name: string) {
  renameTarget.value = name; renameValue.value = name
  nextTick(() => {
    const el = document.getElementById('rename-input')
    if (el) { (el as HTMLInputElement).focus(); (el as HTMLInputElement).select() }
  })
}
async function confirmRename() {
  if (!ctrlServer.value || !renameTarget.value || !renameValue.value.trim()) { renameTarget.value = null; return }
  const from = renameTarget.value; const to = renameValue.value.trim()
  if (from === to) { renameTarget.value = null; return }
  renamingFile.value = true
  try {
    await api.remoteRenameFile(ctrlServer.value.id, ctrlDir.value, from, to)
    toast.success('Renamed'); renameTarget.value = null; await loadCtrlFiles()
  } catch (e) { toast.error(e instanceof Error ? e.message : 'Rename failed') }
  finally { renamingFile.value = false }
}

async function confirmNewFolder() {
  if (!ctrlServer.value || !newFolderName.value.trim()) { newFolderMode.value = false; return }
  creatingFolder.value = true
  try {
    await api.remoteCreateFolder(ctrlServer.value.id, ctrlDir.value, newFolderName.value.trim())
    toast.success('Folder created'); newFolderMode.value = false; newFolderName.value = ''; await loadCtrlFiles()
  } catch (e) { toast.error(e instanceof Error ? e.message : 'Create failed') }
  finally { creatingFolder.value = false }
}

async function compressSelected() {
  if (!ctrlServer.value || !selectedFiles.value.size) return
  const names = [...selectedFiles.value]
  try {
    const f = await api.remoteCompressFiles(ctrlServer.value.id, ctrlDir.value, names)
    toast.success(`Compressed → ${f.name ?? 'archive'}`)
    await loadCtrlFiles()
  } catch (e) { toast.error(e instanceof Error ? e.message : 'Compress failed') }
}

async function decompressFile(name: string) {
  if (!ctrlServer.value) return
  try {
    await api.remoteDecompressFile(ctrlServer.value.id, ctrlDir.value, name)
    toast.success('Decompressed'); await loadCtrlFiles()
  } catch (e) { toast.error(e instanceof Error ? e.message : 'Decompress failed') }
}

// ── Startup ────────────────────────────────────────────────────────────────────
const ctrlStartup    = ref<StartupConfig | null>(null)
const startupEdits   = ref<Record<string, string>>({})
const savingVar      = ref<Record<string, boolean>>({})

function resetStartupState() { ctrlStartup.value = null; startupEdits.value = {}; savingVar.value = {} }

watch(ctrlStartup, (s) => {
  if (!s) return
  const edits: Record<string, string> = {}
  s.variables.forEach(v => { edits[v.env_variable] = v.server_value })
  startupEdits.value = edits
})

async function saveVariable(v: StartupVariable) {
  if (!ctrlServer.value) return
  savingVar.value[v.env_variable] = true
  try {
    const updated = await api.remoteUpdateStartupVariable(ctrlServer.value.id, v.env_variable, startupEdits.value[v.env_variable] ?? v.server_value)
    if (ctrlStartup.value) {
      const idx = ctrlStartup.value.variables.findIndex(x => x.env_variable === v.env_variable)
      if (idx >= 0) ctrlStartup.value.variables[idx].server_value = updated.server_value
    }
    toast.success(`${v.name} updated`)
  } catch (e) { toast.error(e instanceof Error ? e.message : 'Save failed') }
  finally { savingVar.value[v.env_variable] = false }
}

// ── Other tabs ─────────────────────────────────────────────────────────────────
const ctrlAllocs   = ref<Allocation[]>([])
const ctrlSchedules = ref<Schedule[]>([])
const ctrlBackups  = ref<Backup[]>([])
const ctrlDbs      = ref<Database[]>([])
const ctrlCreatingBackup = ref(false)

async function createCtrlBackup() {
  if (!ctrlServer.value) return
  ctrlCreatingBackup.value = true
  try { const b = await api.remoteCreateBackup(ctrlServer.value.id); ctrlBackups.value.unshift(b); toast.success('Backup created') }
  catch (e) { toast.error(e instanceof Error ? e.message : 'Failed') }
  finally { ctrlCreatingBackup.value = false }
}
async function deleteCtrlBackup(b: Backup) {
  if (!ctrlServer.value || !b.uuid) return
  if (!confirm(`Delete backup "${b.name}"?`)) return
  try {
    await api.remoteDeleteBackup(ctrlServer.value.id, b.uuid)
    ctrlBackups.value = ctrlBackups.value.filter(x => x.uuid !== b.uuid); toast.success('Deleted')
  } catch (e) { toast.error(e instanceof Error ? e.message : 'Failed') }
}

// ── Utils ──────────────────────────────────────────────────────────────────────
function stateDot(state?: string) {
  const s = (state ?? '').toLowerCase()
  if (s === 'running' || s === 'online') return 'bg-green-500'
  if (s === 'starting') return 'bg-yellow-400 animate-pulse'
  if (s === 'stopping') return 'bg-orange-400 animate-pulse'
  return 'bg-red-500'
}
function stateBadge(state?: string) {
  const s = (state ?? '').toLowerCase()
  if (s === 'running' || s === 'online') return 'text-green-400 bg-green-500/10 border-green-500/30'
  if (s === 'starting') return 'text-yellow-400 bg-yellow-500/10 border-yellow-500/30'
  if (s === 'stopping') return 'text-orange-400 bg-orange-500/10 border-orange-500/30'
  return 'text-red-400 bg-red-500/10 border-red-500/30'
}
function statusCls(s: string) {
  const v = s.toLowerCase()
  if (v === 'running' || v === 'online') return 'text-green-400'
  if (v === 'suspended') return 'text-yellow-400'
  return 'text-red-400'
}
function fmtBytes(b: number) { return b >= 1073741824 ? (b/1073741824).toFixed(1)+' GB' : (b/1048576).toFixed(0)+' MB' }
function fmtMem(mb: number) { return mb >= 1024 ? (mb/1024).toFixed(1)+' GB' : mb+' MB' }
function fmtUptime(s: number) {
  if (!s) return '—'
  const h = Math.floor(s / 3600); const m = Math.floor((s % 3600) / 60)
  return h > 0 ? `${h}h ${m}m` : `${m}m`
}
function isArchive(name: string) { return /\.(tar\.gz|tgz|zip|tar\.bz2|tar\.xz|rar|7z)$/i.test(name) }
const breadcrumbs = computed(() => {
  const parts = [{ label: '/', path: '/', stackIdx: -1 }]
  let acc = ''
  for (let i = 0; i < ctrlDirStack.value.length; i++) {
    const seg = ctrlDirStack.value[i+1]?.replace(ctrlDirStack.value[i], '').replace(/^\//, '') ?? ''
    if (seg) parts.push({ label: seg, path: ctrlDirStack.value[i+1], stackIdx: i+1 })
  }
  const curSeg = ctrlDir.value.split('/').filter(Boolean).pop() ?? ''
  if (curSeg && ctrlDir.value !== '/') parts.push({ label: curSeg, path: ctrlDir.value, stackIdx: -2 })
  return parts
})
</script>

<template>
  <div class="w-full min-h-full bg-background text-foreground text-sm">
    <div class="mx-auto max-w-5xl p-4 space-y-4">

      <!-- Header -->
      <div class="flex items-center justify-between flex-wrap gap-3">
        <div class="flex items-center gap-2.5">
          <div class="w-8 h-8 rounded-lg bg-primary/15 flex items-center justify-center">
            <Link class="h-4 w-4 text-primary" />
          </div>
          <div>
            <h1 class="text-base font-semibold leading-none">Apichan</h1>
            <p class="text-[11px] text-muted-foreground mt-0.5">External panel connections</p>
          </div>
        </div>
        <div class="flex gap-2">
          <button v-if="mainTab === 'sources'" class="btn-ghost" :disabled="loadingSrc" @click="loadSources">
            <RefreshCw class="h-3.5 w-3.5" :class="loadingSrc?'animate-spin':''"/>Refresh
          </button>
          <button v-if="mainTab === 'sources'" class="btn-primary" @click="openCreateModal">
            <Plus class="h-3.5 w-3.5"/>Add source
          </button>
          <button v-if="mainTab === 'remote'" class="btn-ghost" :disabled="loadingRem" @click="loadRemotes().then(startPolling)">
            <RefreshCw class="h-3.5 w-3.5" :class="loadingRem?'animate-spin':''"/>Refresh
          </button>
        </div>
      </div>

      <!-- Tab bar -->
      <div class="flex gap-0 border-b border-border">
        <button v-for="[id, label] in [['sources','Sources'],['remote','Remote Servers']] as const"
          :key="id"
          :class="['px-4 py-2.5 text-xs font-medium transition-colors -mb-px border-b-2',
            mainTab===id ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground']"
          @click="switchMainTab(id as any)">
          {{ label }}
          <span v-if="id==='remote' && remotes.length"
            class="ml-1.5 text-[10px] bg-primary/20 text-primary rounded-full px-1.5 py-0.5">{{ remotes.length }}</span>
        </button>
      </div>

      <!-- ═ SOURCES TAB ══════════════════════════════════════════════════════════ -->
      <template v-if="mainTab==='sources'">
        <div v-if="loadingSrc && !sources.length" class="flex justify-center py-16">
          <Loader2 class="h-6 w-6 animate-spin text-muted-foreground"/>
        </div>
        <div v-else-if="!sources.length"
          class="flex flex-col items-center py-16 text-muted-foreground border border-dashed border-border rounded-xl">
          <ExternalLink class="h-8 w-8 mb-3 opacity-30"/>
          <p class="font-medium text-sm">No sources yet</p>
          <p class="text-xs mt-1 opacity-70">Add an external panel to browse and pin servers</p>
          <button class="btn-primary mt-4" @click="openCreateModal"><Plus class="h-3.5 w-3.5"/>Add source</button>
        </div>
        <div v-else class="space-y-2">
          <div v-for="source in sources" :key="source.id" class="card overflow-hidden">
            <div class="flex items-center gap-2.5 px-4 py-3">
              <button class="flex-1 flex items-center gap-2.5 text-left min-w-0" @click="toggleExpand(source)">
                <span class="type-badge">{{ TYPE_LABELS[source.type] ?? source.type }}</span>
                <span class="font-medium truncate">{{ source.name }}</span>
                <span class="text-xs text-muted-foreground truncate hidden sm:block">{{ source.url }}</span>
                <component :is="expandedId===source.id ? ChevronUp : ChevronDown" class="h-4 w-4 text-muted-foreground shrink-0 ml-auto"/>
              </button>
              <button class="icon-btn" title="Edit" @click.stop="openEditModal(source)"><Pencil class="h-3.5 w-3.5"/></button>
              <button class="icon-btn text-red-400" title="Delete" @click.stop="deleteSrc(source)"><Trash2 class="h-3.5 w-3.5"/></button>
            </div>

            <!-- Server browser -->
            <div v-if="expandedId===source.id" class="border-t border-border bg-muted/5">
              <div class="p-3 space-y-2.5">
                <div class="flex gap-2 flex-wrap items-center">
                  <input v-model="srvSearch" type="text" placeholder="Search servers…"
                    class="input flex-1 min-w-32"/>
                  <span class="text-xs text-muted-foreground">{{ srvPage }}/{{ srvTotalPages }}</span>
                  <button class="btn-ghost text-xs py-1" :disabled="srvPage<=1||loadingSrv" @click="changeSrvPage(-1)">← Prev</button>
                  <button class="btn-ghost text-xs py-1" :disabled="srvPage>=srvTotalPages||loadingSrv" @click="changeSrvPage(1)">Next →</button>
                </div>
                <div v-if="loadingSrv" class="flex justify-center py-6"><Loader2 class="h-5 w-5 animate-spin text-muted-foreground"/></div>
                <div v-else-if="srvError" class="text-xs text-red-400 bg-red-500/10 border border-red-500/20 rounded-lg p-3">{{ srvError }}</div>
                <div v-else-if="!filteredSrv.length" class="text-center py-6 text-xs text-muted-foreground">No servers found</div>
                <div v-else class="rounded-lg border border-border overflow-hidden">
                  <table class="w-full text-xs">
                    <thead class="bg-muted/40 border-b border-border">
                      <tr>
                        <th class="th">Name</th>
                        <th class="th hidden sm:table-cell">RAM</th>
                        <th class="th">Status</th>
                        <th class="th w-20"></th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="s in filteredSrv" :key="String(s.id)"
                        class="border-b border-border last:border-0 hover:bg-muted/20 transition-colors">
                        <td class="td font-medium truncate max-w-[160px]">{{ s.name }}</td>
                        <td class="td text-muted-foreground hidden sm:table-cell">{{ fmtMem(s.memory) }}</td>
                        <td class="td"><span :class="['font-medium capitalize', statusCls(s.status)]">{{ s.status }}</span></td>
                        <td class="td">
                          <button :disabled="pinningId===Number(s.id)"
                            class="inline-flex items-center gap-1 text-[10px] px-2 py-1 rounded-md bg-violet-600 text-white hover:bg-violet-700 disabled:opacity-50 transition-colors"
                            @click="pinServer(s, source.id)">
                            <Loader2 v-if="pinningId===Number(s.id)" class="h-3 w-3 animate-spin"/>
                            <Pin v-else class="h-3 w-3"/>Pin
                          </button>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      </template>

      <!-- ═ REMOTE SERVERS TAB ═══════════════════════════════════════════════════ -->
      <template v-else>
        <SharedRemoteServerView />
      </template>

    </div>
  </div>

  <!-- ══ SOURCE MODAL ══════════════════════════════════════════════════════════ -->
  <Teleport to="body">
    <div v-if="showSrcModal" class="modal-overlay" @click.self="showSrcModal=false">
      <div class="modal-box max-w-lg">
        <div class="modal-header">
          <h2 class="font-semibold">{{ editingSrc ? 'Edit source' : 'Add source' }}</h2>
          <button class="icon-btn" @click="showSrcModal=false"><X class="h-4 w-4"/></button>
        </div>
        <div class="p-5 space-y-4">
          <div class="field">
            <label class="label">Name</label>
            <input v-model="sName" type="text" placeholder="My Panel" class="input"/>
          </div>
          <div class="field">
            <label class="label">Panel type</label>
            <div ref="typeDropRef" class="relative">
              <button type="button" class="input w-full flex justify-between items-center"
                @click="typeDropOpen=!typeDropOpen">
                <span>{{ TYPE_LABELS[sType] }}</span>
                <ChevronDown class="h-4 w-4 text-muted-foreground" :class="typeDropOpen?'rotate-180':''"/>
              </button>
              <div v-show="typeDropOpen"
                class="absolute top-full left-0 right-0 z-30 mt-1 bg-card border border-border rounded-lg shadow-xl overflow-hidden">
                <button v-for="opt in TYPE_OPTIONS" :key="opt.value" type="button"
                  class="w-full text-left px-3 py-2.5 text-sm flex items-center justify-between hover:bg-muted transition-colors"
                  @click="sType=opt.value;typeDropOpen=false;testResult=null">
                  <span>{{ opt.label }}</span><Check v-if="sType===opt.value" class="h-3.5 w-3.5 text-primary"/>
                </button>
              </div>
            </div>
          </div>
          <div class="field">
            <label class="label">Panel URL</label>
            <input v-model="sUrl" type="url" placeholder="https://panel.example.com" class="input" @input="testResult=null"/>
          </div>
          <div class="field">
            <label class="label">
              API key
              <span v-if="editingSrc" class="font-normal text-muted-foreground"> (blank = keep current)</span>
            </label>
            <div class="relative">
              <input v-model="sKey" :type="showKey?'text':'password'" autocomplete="new-password"
                placeholder="ptlc_… / client token"
                class="input w-full pr-9" @input="testResult=null"/>
              <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                @click="showKey=!showKey">
                <component :is="showKey?EyeOff:Eye" class="h-4 w-4"/>
              </button>
            </div>
            <p class="text-[11px] text-muted-foreground mt-1">Use a <strong>client</strong> API key for full remote control. Keys are encrypted with AES-256-CBC.</p>
          </div>
          <div class="field">
            <label class="label">Timeout (seconds)</label>
            <input v-model.number="sTimeout" type="number" min="5" max="120" class="input"/>
          </div>
          <div v-if="testResult" :class="['rounded-lg px-3 py-2.5 text-xs flex items-center gap-2 border',
            testResult.ok ? 'bg-green-500/10 border-green-500/20 text-green-400' : 'bg-red-500/10 border-red-500/20 text-red-400']">
            <component :is="testResult.ok ? Wifi : WifiOff" class="h-3.5 w-3.5 shrink-0"/>{{ testResult.msg }}
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn-ghost" :disabled="testing" @click="testConnection">
            <Loader2 v-if="testing" class="h-3.5 w-3.5 animate-spin"/><Wifi v-else class="h-3.5 w-3.5"/>
            {{ testing ? 'Testing…' : 'Test connection' }}
          </button>
          <div class="flex gap-2">
            <button class="btn-ghost" @click="showSrcModal=false">Cancel</button>
            <button class="btn-primary" :disabled="saving" @click="saveSrc">
              <Loader2 v-if="saving" class="h-3.5 w-3.5 animate-spin"/>{{ editingSrc ? 'Save changes' : 'Add source' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>

  <!-- ══ CONTROL PANEL ═════════════════════════════════════════════════════════ -->
  <Teleport to="body">
    <div v-if="ctrlServer" class="fixed inset-0 z-50 flex" style="background:rgba(0,0,0,0.85);backdrop-filter:blur(4px)">
      <div class="flex flex-col flex-1 m-2 sm:m-3 bg-card rounded-xl border border-border shadow-2xl overflow-hidden min-h-0">

        <!-- Top bar -->
        <div class="flex items-center gap-3 px-4 py-3 border-b border-border shrink-0 bg-muted/20">
          <div :class="['w-2.5 h-2.5 rounded-full shrink-0', stateDot(ctrlResources?.state)]"></div>
          <div class="flex-1 min-w-0">
            <h2 class="font-semibold text-sm truncate">{{ ctrlServer.name }}</h2>
            <p class="text-[11px] text-muted-foreground">{{ ctrlServer.source_name }}</p>
          </div>
          <span v-if="ctrlResources?.state"
            :class="['text-[10px] font-medium px-2 py-0.5 rounded-full border capitalize hidden sm:block', stateBadge(ctrlResources.state)]">
            {{ ctrlResources.state }}
          </span>
          <div class="flex gap-1 items-center">
            <button class="power-btn green text-[11px]" @click="doPower(ctrlServer!,'start')">
              <Play class="h-3 w-3"/>Start
            </button>
            <button class="power-btn red text-[11px]" @click="doPower(ctrlServer!,'stop')">
              <Square class="h-3 w-3"/>Stop
            </button>
            <button class="power-btn yellow text-[11px]" @click="doPower(ctrlServer!,'restart')">
              <RotateCcw class="h-3 w-3"/>Restart
            </button>
            <button class="power-btn orange text-[11px]" @click="doPower(ctrlServer!,'kill')">
              <Zap class="h-3 w-3"/>Kill
            </button>
            <button class="icon-btn ml-1" @click="closeControl"><X class="h-4 w-4"/></button>
          </div>
        </div>

        <!-- Body: sidebar + content -->
        <div class="flex flex-1 min-h-0">

          <!-- Left sidebar -->
          <nav class="w-14 sm:w-44 border-r border-border shrink-0 py-2 flex flex-col gap-0.5 bg-muted/10 overflow-y-auto">
            <button v-for="t in CTRL_TABS" :key="t.id"
              :class="['sidebar-tab', ctrlTab===t.id ? 'active' : '']"
              @click="switchCtrlTab(t.id)">
              <component :is="t.icon" class="h-4 w-4 shrink-0"/>
              <span class="hidden sm:block text-xs">{{ t.label }}</span>
            </button>
          </nav>

          <!-- Content area -->
          <div class="flex-1 min-w-0 min-h-0 flex flex-col">

            <div v-if="ctrlTabLoading && ctrlTab !== 'console'" class="flex-1 flex items-center justify-center">
              <Loader2 class="h-7 w-7 animate-spin text-muted-foreground"/>
            </div>

            <!-- ── OVERVIEW ─────────────────────────────────────────────────────── -->
            <div v-else-if="ctrlTab==='overview'" class="flex-1 overflow-y-auto p-4 space-y-4">
              <div class="grid grid-cols-3 gap-3">
                <div v-for="[label, val, pct, icon] in [
                  ['CPU', (ctrlResources?.cpu??0).toFixed(1)+'%', Math.min(ctrlResources?.cpu??0,100), Cpu],
                  ['Memory', fmtMem(ctrlResources?.memory_mb??0), ctrlResources ? Math.min((ctrlResources.memory_mb/2048)*100,100) : 0, MemoryStick],
                  ['Disk', fmtMem(ctrlResources?.disk_mb??0), ctrlResources ? Math.min((ctrlResources.disk_mb/20480)*100,100) : 0, HardDrive],
                ]" :key="String(label)" class="card p-4">
                  <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
                      <component :is="icon" class="h-3.5 w-3.5"/>{{ label }}
                    </div>
                    <span class="text-sm font-semibold">{{ val }}</span>
                  </div>
                  <div class="h-1.5 rounded-full bg-border overflow-hidden">
                    <div class="h-full rounded-full bg-primary transition-all duration-700"
                      :style="{width:Math.max(pct as number,0)+'%'}"></div>
                  </div>
                </div>
              </div>
              <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="card p-3">
                  <p class="text-[11px] text-muted-foreground mb-1">State</p>
                  <p class="font-medium capitalize text-sm">{{ ctrlResources?.state ?? '—' }}</p>
                </div>
                <div class="card p-3">
                  <p class="text-[11px] text-muted-foreground mb-1">Uptime</p>
                  <p class="font-medium text-sm">{{ fmtUptime(ctrlResources?.uptime ?? 0) }}</p>
                </div>
                <div class="card p-3">
                  <p class="text-[11px] text-muted-foreground mb-1">Net ↓</p>
                  <p class="font-medium text-sm">{{ (ctrlResources?.net_rx_mb ?? 0).toFixed(1) }} MB</p>
                </div>
                <div class="card p-3">
                  <p class="text-[11px] text-muted-foreground mb-1">Net ↑</p>
                  <p class="font-medium text-sm">{{ (ctrlResources?.net_tx_mb ?? 0).toFixed(1) }} MB</p>
                </div>
              </div>
            </div>

            <!-- ── CONSOLE ──────────────────────────────────────────────────────── -->
            <div v-else-if="ctrlTab==='console'" class="flex-1 min-h-0 flex flex-col p-3 gap-2">
              <!-- Terminal -->
              <div ref="terminalEl"
                class="flex-1 min-h-0 overflow-y-auto rounded-lg border border-border/60 p-3 font-mono text-[12px] leading-5 select-text"
                style="background:#0d1117;color:#c9d1d9">
                <div v-if="!consoleLines.length" class="text-[#6e7681] italic">Waiting for console output…</div>
                <div v-for="(line,i) in consoleLines" :key="i" v-html="line||'&nbsp;'" class="whitespace-pre-wrap break-all"></div>
              </div>
              <!-- Status bar + controls -->
              <div class="flex items-center gap-2 shrink-0">
                <div class="flex items-center gap-1.5 text-xs">
                  <div :class="['w-2 h-2 rounded-full shrink-0',
                    consoleConnected ? 'bg-green-500' : consoleConnecting ? 'bg-yellow-400 animate-pulse' : 'bg-red-500']"></div>
                  <span class="text-muted-foreground">
                    {{ consoleConnected ? 'Connected' : consoleConnecting ? 'Connecting…' : 'Disconnected' }}
                  </span>
                  <span v-if="consoleConnected && consolePingMs !== null"
                    :class="['font-mono',
                      consolePingMs < 150 ? 'text-green-500' : consolePingMs < 400 ? 'text-yellow-400' : 'text-red-500']">
                    · {{ consolePingMs }}ms
                  </span>
                </div>
                <button v-if="!consoleConnected && !consoleConnecting" class="btn-ghost text-xs py-1 ml-auto" @click="connectConsole">
                  Reconnect
                </button>
                <button v-if="consoleConnected" class="btn-ghost text-xs py-1 ml-auto" @click="disconnectConsole">
                  Disconnect
                </button>
              </div>
              <div class="flex gap-2 shrink-0"
                style="background:#0d1117;border:1px solid #30363d;border-radius:8px;padding:6px 10px">
                <span style="color:#6e7681;font-family:monospace;font-size:12px">$</span>
                <input v-model="ctrlCommand" type="text" placeholder="Enter command…"
                  style="flex:1;background:transparent;font-family:monospace;font-size:12px;color:#c9d1d9;outline:none;border:none"
                  @keydown.enter="sendCtrlCommand"/>
                <button class="btn-primary text-xs py-1 px-3" :disabled="ctrlSending||!ctrlCommand.trim()" @click="sendCtrlCommand">
                  <Send class="h-3.5 w-3.5"/>Send
                </button>
              </div>
            </div>

            <!-- ── FILES ───────────────────────────────────────────────────────── -->
            <div v-else-if="ctrlTab==='files'" class="flex-1 min-h-0 flex flex-col">
              <!-- File editor -->
              <template v-if="ctrlOpenFile">
                <div class="flex items-center gap-2 px-4 py-2.5 border-b border-border shrink-0">
                  <button class="btn-ghost text-xs py-1" @click="ctrlOpenFile=null"><ArrowLeft class="h-3.5 w-3.5"/>Back</button>
                  <span class="font-mono text-xs text-muted-foreground flex-1 truncate">{{ ctrlFilePath }}</span>
                  <button class="btn-primary text-xs py-1 px-3" :disabled="ctrlSavingFile" @click="saveCtrlFile">
                    <Loader2 v-if="ctrlSavingFile" class="h-3.5 w-3.5 animate-spin"/>{{ ctrlSavingFile?'Saving…':'Save' }}
                  </button>
                </div>
                <textarea v-model="ctrlFileContent"
                  class="flex-1 min-h-0 font-mono text-xs p-4 resize-none focus:outline-none"
                  style="background:#0d1117;color:#c9d1d9;border:none"
                  spellcheck="false"></textarea>
              </template>

              <!-- File browser -->
              <template v-else>
                <!-- Toolbar -->
                <div class="flex items-center gap-2 flex-wrap px-4 py-2.5 border-b border-border shrink-0">
                  <!-- Breadcrumb -->
                  <div class="flex items-center gap-1 text-xs text-muted-foreground flex-1 min-w-0 overflow-hidden">
                    <button class="hover:text-foreground font-medium" @click="ctrlDir='/';ctrlDirStack=[];loadCtrlFiles()">/</button>
                    <template v-for="(seg,i) in ctrlDir.split('/').filter(Boolean)" :key="i">
                      <ChevronRight class="h-3 w-3 shrink-0"/>
                      <button class="hover:text-foreground truncate max-w-[80px]"
                        @click="()=>{ const p='/'+ctrlDir.split('/').filter(Boolean).slice(0,i+1).join('/'); ctrlDirStack=ctrlDirStack.slice(0,i); ctrlDir=p; loadCtrlFiles() }">
                        {{ seg }}
                      </button>
                    </template>
                  </div>
                  <!-- Actions when items selected -->
                  <template v-if="selectedFiles.size > 0">
                    <button v-if="selectedFiles.size===1" class="btn-ghost text-xs py-1"
                      @click="startRename([...selectedFiles][0])">
                      <Pencil class="h-3 w-3"/>Rename
                    </button>
                    <button class="btn-ghost text-xs py-1" @click="compressSelected">
                      <FileArchive class="h-3 w-3"/>Archive
                    </button>
                    <button class="btn-ghost text-xs py-1 text-red-400 hover:text-red-300" @click="deleteSelected">
                      <Trash2 class="h-3 w-3"/>Delete ({{ selectedFiles.size }})
                    </button>
                  </template>
                  <!-- Normal actions -->
                  <template v-if="!newFolderMode">
                    <button class="btn-ghost text-xs py-1" @click="newFolderMode=true;newFolderName='';nextTick(()=>{ const el=newFolderInputRef; if(el) el.focus() })">
                      <FolderPlus class="h-3 w-3"/>New folder
                    </button>
                    <button class="btn-ghost text-xs py-1" :disabled="ctrlLoadFiles" @click="loadCtrlFiles">
                      <RefreshCw class="h-3 w-3" :class="ctrlLoadFiles?'animate-spin':''"/>Refresh
                    </button>
                  </template>
                </div>

                <!-- New folder input row -->
                <div v-if="newFolderMode" class="flex items-center gap-2 px-4 py-2 border-b border-border shrink-0 bg-muted/10">
                  <FolderPlus class="h-4 w-4 text-yellow-400 shrink-0"/>
                  <input ref="newFolderInputRef" v-model="newFolderName" type="text" placeholder="Folder name…"
                    class="input flex-1 text-xs py-1"
                    @keydown.enter="confirmNewFolder" @keydown.esc="newFolderMode=false"/>
                  <button class="btn-primary text-xs py-1 px-3" :disabled="creatingFolder||!newFolderName.trim()" @click="confirmNewFolder">
                    <Loader2 v-if="creatingFolder" class="h-3 w-3 animate-spin"/>Create
                  </button>
                  <button class="btn-ghost text-xs py-1" @click="newFolderMode=false">Cancel</button>
                </div>

                <!-- File list -->
                <div class="flex-1 overflow-y-auto">
                  <div v-if="ctrlLoadFiles" class="flex justify-center py-12">
                    <Loader2 class="h-6 w-6 animate-spin text-muted-foreground"/>
                  </div>
                  <div v-else-if="!ctrlFiles.length" class="flex flex-col items-center py-12 text-muted-foreground">
                    <Folder class="h-8 w-8 mb-2 opacity-30"/>
                    <p class="text-xs">Empty directory</p>
                  </div>
                  <table v-else class="w-full text-xs">
                    <thead class="border-b border-border sticky top-0 bg-card z-10">
                      <tr>
                        <th class="w-10 px-3 py-2.5">
                          <input type="checkbox" class="rounded"
                            :checked="selectedFiles.size===ctrlFiles.length && ctrlFiles.length>0"
                            :indeterminate="selectedFiles.size>0 && selectedFiles.size<ctrlFiles.length"
                            @change="toggleSelectAll"/>
                        </th>
                        <th class="th text-left">Name</th>
                        <th class="th text-left hidden sm:table-cell">Size</th>
                        <th class="th text-left hidden md:table-cell">Modified</th>
                        <th class="w-24"></th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-if="ctrlDirStack.length" class="border-b border-border hover:bg-muted/10 transition-colors cursor-pointer"
                        @click="goUpDir">
                        <td class="px-3 py-2.5"></td>
                        <td class="px-3 py-2.5" colspan="4">
                          <div class="flex items-center gap-2 text-muted-foreground">
                            <ArrowLeft class="h-3.5 w-3.5"/><span>.. (go up)</span>
                          </div>
                        </td>
                      </tr>
                      <tr v-for="f in ctrlFiles" :key="f.name"
                        :class="['border-b border-border hover:bg-muted/10 transition-colors', selectedFiles.has(f.name)?'bg-primary/5':'']">
                        <td class="px-3 py-2.5" @click.stop>
                          <input type="checkbox" class="rounded" :checked="selectedFiles.has(f.name)"
                            @change="toggleSelect(f.name)"/>
                        </td>
                        <td class="px-3 py-2.5 cursor-pointer"
                          @click="f.is_file ? openFile(f) : enterDir(f.name)">
                          <div class="flex items-center gap-2">
                            <component :is="f.is_file ? File : Folder"
                              :class="['h-4 w-4 shrink-0', f.is_file ? 'text-muted-foreground' : 'text-yellow-400']"/>
                            <span v-if="renameTarget===f.name" @click.stop>
                              <input id="rename-input" v-model="renameValue" type="text"
                                class="input text-xs py-0.5 w-48"
                                @keydown.enter="confirmRename" @keydown.esc="renameTarget=null"
                                @blur="renameTarget=null"/>
                            </span>
                            <span v-else class="font-mono truncate max-w-[180px]">{{ f.name }}</span>
                          </div>
                        </td>
                        <td class="px-3 py-2.5 text-muted-foreground hidden sm:table-cell">
                          {{ f.is_file ? fmtBytes(f.size) : '—' }}
                        </td>
                        <td class="px-3 py-2.5 text-muted-foreground hidden md:table-cell">
                          {{ f.modified_at ? new Date(f.modified_at).toLocaleDateString() : '—' }}
                        </td>
                        <td class="px-2 py-2.5">
                          <div class="flex items-center gap-1 justify-end">
                            <button class="icon-btn-sm" title="Rename" @click.stop="startRename(f.name)">
                              <Pencil class="h-3 w-3"/>
                            </button>
                            <button v-if="f.is_file && isArchive(f.name)" class="icon-btn-sm" title="Extract"
                              @click.stop="decompressFile(f.name)">
                              <FileArchive class="h-3 w-3"/>
                            </button>
                            <button class="icon-btn-sm text-red-400" title="Delete"
                              @click.stop="api.remoteDeleteFiles(ctrlServer!.id,ctrlDir,[f.name]).then(()=>{toast.success('Deleted');loadCtrlFiles()}).catch(e=>toast.error(e instanceof Error?e.message:'Failed'))">
                              <Trash2 class="h-3 w-3"/>
                            </button>
                          </div>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </template>
            </div>

            <!-- ── STARTUP ──────────────────────────────────────────────────────── -->
            <div v-else-if="ctrlTab==='startup'" class="flex-1 overflow-y-auto p-4 space-y-4">
              <div v-if="!ctrlStartup" class="flex justify-center py-12">
                <Loader2 class="h-6 w-6 animate-spin text-muted-foreground"/>
              </div>
              <template v-else>
                <div class="card p-4">
                  <p class="text-xs text-muted-foreground mb-2 font-medium uppercase tracking-wide">Startup command</p>
                  <pre class="font-mono text-xs rounded-lg p-3 bg-muted/30 text-muted-foreground overflow-x-auto whitespace-pre-wrap break-all">{{ ctrlStartup.startup_command || '—' }}</pre>
                </div>
                <div v-if="!ctrlStartup.variables.length" class="text-center py-6 text-xs text-muted-foreground">
                  No variables defined
                </div>
                <div v-else class="space-y-3">
                  <h3 class="text-xs font-medium text-muted-foreground uppercase tracking-wide">Variables</h3>
                  <div v-for="v in ctrlStartup.variables" :key="v.env_variable" class="card p-4">
                    <div class="flex items-start justify-between gap-3 mb-2">
                      <div>
                        <p class="font-medium text-sm">{{ v.name }}</p>
                        <p v-if="v.description" class="text-xs text-muted-foreground mt-0.5">{{ v.description }}</p>
                      </div>
                      <code class="text-[10px] font-mono bg-muted/40 px-1.5 py-0.5 rounded text-muted-foreground shrink-0">{{ v.env_variable }}</code>
                    </div>
                    <div class="flex items-center gap-2 mt-3">
                      <input v-if="v.is_editable" v-model="startupEdits[v.env_variable]" type="text"
                        class="input flex-1 text-xs font-mono"
                        :placeholder="v.default_value || '(empty)'"/>
                      <code v-else class="flex-1 text-xs font-mono bg-muted/30 px-3 py-2 rounded-md text-muted-foreground">{{ v.server_value || v.default_value || '(empty)' }}</code>
                      <button v-if="v.is_editable" class="btn-primary text-xs py-1.5 px-3 shrink-0"
                        :disabled="savingVar[v.env_variable] || startupEdits[v.env_variable]===v.server_value"
                        @click="saveVariable(v)">
                        <Loader2 v-if="savingVar[v.env_variable]" class="h-3 w-3 animate-spin"/>
                        <Check v-else class="h-3 w-3"/>Save
                      </button>
                    </div>
                    <p v-if="v.rules" class="text-[10px] text-muted-foreground mt-1.5">Rules: {{ v.rules }}</p>
                  </div>
                </div>
              </template>
            </div>

            <!-- ── ALLOCATIONS ──────────────────────────────────────────────────── -->
            <div v-else-if="ctrlTab==='allocations'" class="flex-1 overflow-y-auto p-4">
              <div v-if="!ctrlAllocs.length" class="empty-state"><Network class="h-8 w-8"/>No allocations</div>
              <div v-else class="card overflow-hidden">
                <table class="w-full text-xs">
                  <thead class="bg-muted/30 border-b border-border">
                    <tr>
                      <th class="th">IP / Alias</th><th class="th">Port</th>
                      <th class="th hidden sm:table-cell">Notes</th><th class="th">Default</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="a in ctrlAllocs" :key="String(a.id)" class="border-b border-border last:border-0 hover:bg-muted/10">
                      <td class="td font-mono">{{ a.ip_alias || a.ip }}</td>
                      <td class="td font-mono">{{ a.port }}</td>
                      <td class="td text-muted-foreground hidden sm:table-cell">{{ a.notes || '—' }}</td>
                      <td class="td"><span v-if="a.is_default" class="text-green-400 text-xs font-medium">Default</span></td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- ── SCHEDULES ────────────────────────────────────────────────────── -->
            <div v-else-if="ctrlTab==='schedules'" class="flex-1 overflow-y-auto p-4 space-y-2">
              <div v-if="!ctrlSchedules.length" class="empty-state"><Clock class="h-8 w-8"/>No schedules</div>
              <div v-else v-for="s in ctrlSchedules" :key="String(s.id)" class="card p-4">
                <div class="flex items-center justify-between mb-1.5">
                  <p class="font-medium">{{ s.name }}</p>
                  <span :class="['text-[10px] font-medium px-2 py-0.5 rounded-full border',
                    s.is_active ? 'text-green-400 bg-green-500/10 border-green-500/20' : 'text-muted-foreground bg-muted border-border']">
                    {{ s.is_active ? 'Active' : 'Paused' }}
                  </span>
                </div>
                <code class="text-[11px] font-mono text-muted-foreground">
                  {{ s.cron_minute }} {{ s.cron_hour }} {{ s.cron_day_of_month }} {{ s.cron_month }} {{ s.cron_day_of_week }}
                </code>
                <p v-if="s.next_run_at" class="text-[11px] text-muted-foreground mt-1">Next: {{ new Date(s.next_run_at).toLocaleString() }}</p>
              </div>
            </div>

            <!-- ── BACKUPS ──────────────────────────────────────────────────────── -->
            <div v-else-if="ctrlTab==='backups'" class="flex-1 overflow-y-auto p-4 space-y-3">
              <div class="flex justify-end">
                <button class="btn-primary" :disabled="ctrlCreatingBackup" @click="createCtrlBackup">
                  <Loader2 v-if="ctrlCreatingBackup" class="h-3.5 w-3.5 animate-spin"/><Plus v-else class="h-3.5 w-3.5"/>
                  Create backup
                </button>
              </div>
              <div v-if="!ctrlBackups.length" class="empty-state"><HardDrive class="h-8 w-8"/>No backups</div>
              <div v-else v-for="b in ctrlBackups" :key="String(b.uuid)"
                class="card flex items-center gap-3 p-3.5">
                <HardDrive class="h-4 w-4 text-muted-foreground shrink-0"/>
                <div class="flex-1 min-w-0">
                  <p class="font-medium text-sm truncate">{{ b.name }}</p>
                  <p class="text-xs text-muted-foreground">{{ b.bytes > 0 ? fmtBytes(b.bytes) : 'Pending' }}</p>
                </div>
                <span :class="['text-[10px] font-medium px-2 py-0.5 rounded-full border',
                  b.is_successful ? 'text-green-400 bg-green-500/10 border-green-500/20' : 'text-yellow-400 bg-yellow-500/10 border-yellow-500/20']">
                  {{ b.is_successful ? 'Complete' : 'Processing' }}
                </span>
                <button class="icon-btn text-red-400" @click="deleteCtrlBackup(b)"><Trash2 class="h-4 w-4"/></button>
              </div>
            </div>

            <!-- ── DATABASES ────────────────────────────────────────────────────── -->
            <div v-else-if="ctrlTab==='databases'" class="flex-1 overflow-y-auto p-4">
              <div v-if="!ctrlDbs.length" class="empty-state"><DatabaseIcon class="h-8 w-8"/>No databases</div>
              <div v-else class="card overflow-hidden">
                <table class="w-full text-xs">
                  <thead class="bg-muted/30 border-b border-border">
                    <tr>
                      <th class="th">Name</th><th class="th hidden sm:table-cell">Username</th>
                      <th class="th">Host</th><th class="th">Port</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="d in ctrlDbs" :key="String(d.id)" class="border-b border-border last:border-0 hover:bg-muted/10">
                      <td class="td font-mono">{{ d.name }}</td>
                      <td class="td font-mono hidden sm:table-cell text-muted-foreground">{{ d.username }}</td>
                      <td class="td font-mono text-muted-foreground">{{ d.host }}</td>
                      <td class="td font-mono">{{ d.port }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

          </div><!-- /content -->
        </div><!-- /body -->
      </div><!-- /modal box -->
    </div>
  </Teleport>
</template>

<style scoped>
/* Buttons */
.btn-primary {
  display: inline-flex; align-items: center; gap: 0.375rem;
  padding: 0.5rem 0.75rem; border-radius: 0.5rem;
  font-size: 0.75rem; font-weight: 500;
  background: hsl(var(--primary)); color: hsl(var(--primary-foreground));
  transition: opacity 0.15s; cursor: pointer; border: none;
}
.btn-primary:hover { opacity: 0.88; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }

.btn-ghost {
  display: inline-flex; align-items: center; gap: 0.375rem;
  padding: 0.5rem 0.75rem; border-radius: 0.5rem;
  font-size: 0.75rem; font-weight: 500;
  border: 1px solid hsl(var(--border)); background: transparent;
  color: hsl(var(--foreground)); transition: background 0.15s; cursor: pointer;
}
.btn-ghost:hover { background: hsl(var(--muted)); }
.btn-ghost:disabled { opacity: 0.5; cursor: not-allowed; }

.icon-btn {
  width: 1.75rem; height: 1.75rem; border-radius: 0.375rem;
  border: 1px solid hsl(var(--border)); display: flex;
  align-items: center; justify-content: center;
  color: hsl(var(--muted-foreground)); background: transparent;
  transition: background 0.15s; cursor: pointer;
}
.icon-btn:hover { background: hsl(var(--muted)); }

.icon-btn-sm {
  width: 1.5rem; height: 1.5rem; border-radius: 0.25rem;
  display: flex; align-items: center; justify-content: center;
  color: hsl(var(--muted-foreground)); background: transparent;
  transition: background 0.15s; cursor: pointer; border: none;
}
.icon-btn-sm:hover { background: hsl(var(--muted)); }

.power-btn {
  display: inline-flex; align-items: center; gap: 0.25rem;
  padding: 0.375rem 0.5rem; border-radius: 0.375rem;
  font-size: 0.6875rem; font-weight: 500;
  transition: background 0.15s; cursor: pointer; border: none;
}
.power-btn.green  { background: rgba(22,163,74,.12); color: #4ade80; }
.power-btn.green:hover { background: rgba(22,163,74,.22); }
.power-btn.red    { background: rgba(220,38,38,.12); color: #f87171; }
.power-btn.red:hover { background: rgba(220,38,38,.22); }
.power-btn.yellow { background: rgba(202,138,4,.12); color: #fbbf24; }
.power-btn.yellow:hover { background: rgba(202,138,4,.22); }
.power-btn.orange { background: rgba(234,88,12,.12); color: #fb923c; }
.power-btn.orange:hover { background: rgba(234,88,12,.22); }

/* Cards & inputs */
.card { background: hsl(var(--card)); border: 1px solid hsl(var(--border)); border-radius: 0.75rem; }
.input {
  width: 100%; border-radius: 0.5rem; border: 1px solid hsl(var(--border));
  background: hsl(var(--background)); padding: 0.5rem 0.75rem;
  font-size: 0.875rem; outline: none; transition: box-shadow 0.15s;
  color: hsl(var(--foreground));
}
.input:focus { box-shadow: 0 0 0 2px hsl(var(--primary) / 0.35); }
.field { display: flex; flex-direction: column; gap: 0.375rem; }
.label { font-size: 0.75rem; font-weight: 500; color: hsl(var(--muted-foreground)); }
.type-badge {
  font-size: 0.625rem; font-weight: 700; padding: 0.125rem 0.375rem;
  border-radius: 0.375rem; background: hsl(var(--primary) / 0.12);
  color: hsl(var(--primary)); flex-shrink: 0;
}

/* Table */
.th { padding: 0.625rem 0.75rem; text-align: left; font-size: 0.75rem; font-weight: 500; color: hsl(var(--muted-foreground)); }
.td { padding: 0.625rem 0.75rem; }

/* Sidebar */
.sidebar-tab {
  display: flex; align-items: center; gap: 0.625rem;
  margin: 0 0.5rem; padding: 0.625rem; border-radius: 0.5rem;
  color: hsl(var(--muted-foreground));
  transition: background 0.15s, color 0.15s; cursor: pointer; border: none; background: transparent;
}
.sidebar-tab:hover { background: hsl(var(--muted)); color: hsl(var(--foreground)); }
.sidebar-tab.active { background: hsl(var(--primary) / 0.12); color: hsl(var(--primary)); }
.sidebar-tab.active:hover { background: hsl(var(--primary) / 0.18); }

/* Modal */
.modal-overlay {
  position: fixed; inset: 0; z-index: 50;
  display: flex; align-items: center; justify-content: center; padding: 1rem;
  background: rgba(0,0,0,0.7); backdrop-filter: blur(6px);
}
.modal-box {
  background: hsl(var(--card)); border: 1px solid hsl(var(--border));
  border-radius: 0.75rem; width: 100%; box-shadow: 0 25px 50px rgba(0,0,0,0.5);
  max-height: 90vh; display: flex; flex-direction: column;
}
.modal-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 1rem 1.25rem; border-bottom: 1px solid hsl(var(--border));
}
.modal-footer {
  display: flex; align-items: center; justify-content: space-between; gap: 0.5rem;
  padding: 1rem 1.25rem; border-top: 1px solid hsl(var(--border));
}

/* Empty state */
.empty-state {
  display: flex; flex-direction: column; align-items: center; gap: 0.5rem;
  padding: 4rem 0; color: hsl(var(--muted-foreground)); opacity: 0.5; font-size: 0.75rem;
}
</style>
