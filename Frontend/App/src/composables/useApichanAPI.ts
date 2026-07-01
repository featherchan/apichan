export type SourceType = 'pterodactyl' | 'featherpanel' | 'pelican' | 'calagopus'

export interface Source {
  id: number
  name: string
  type: SourceType
  url: string
  timeout: number
  created_by: number
  created_at: string
}

export interface NormalizedServer {
  id: string | number
  name: string
  identifier: string | null
  description: string
  node: string | number | null
  memory: number
  disk: number
  cpu: number
  swap: number
  status: string
  user: string | number | null
  egg_id: string | number | null
  docker_image: string
  startup: string
}

export interface RemoteServer {
  id: number
  source_id: number
  source_name: string
  source_type: string
  remote_server_id: string
  remote_server_identifier: string | null
  name: string
  created_at: string
}

export interface ServerStatus {
  state: string
  cpu: number
  memory_mb: number
  disk_mb: number
  net_rx_mb: number
  net_tx_mb: number
  uptime: number
}

export interface FileEntry {
  name: string
  mode: string
  size: number
  is_file: boolean
  is_symlink: boolean
  mimetype: string
  created_at: string
  modified_at: string
}

export interface Allocation {
  id: number | null
  ip: string
  ip_alias: string | null
  port: number
  notes: string | null
  is_default: boolean
}

export interface Schedule {
  id: number | null
  name: string
  cron_day_of_week: string
  cron_month: string
  cron_day_of_month: string
  cron_hour: string
  cron_minute: string
  is_active: boolean
  is_processing: boolean
  last_run_at: string | null
  next_run_at: string | null
}

export interface Backup {
  uuid: string | null
  name: string
  bytes: number
  sha256_hash: string | null
  is_successful: boolean
  is_locked: boolean
  created_at: string
  completed_at: string | null
}

export interface Database {
  id: number | null
  name: string
  username: string
  host: string
  port: number
  connections_from: string
}

export interface StartupVariable {
  name: string
  description: string
  env_variable: string
  default_value: string
  server_value: string
  is_editable: boolean
  rules: string
}

export interface StartupConfig {
  startup_command: string
  variables: StartupVariable[]
}

export interface ImportPayload {
  source_id: number
  server_id: string | number
  name?: string
  spell_id: number
  node_id: number
  allocation_id: number
  memory?: number
  disk?: number
  cpu?: number
}

interface ApiResp<T = unknown> {
  success: boolean
  data?: T
  message?: string
  error_message?: string
}

async function call<T>(method: string, path: string, body?: unknown): Promise<T> {
  const r = await fetch(path, {
    method,
    credentials: 'include',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: body !== undefined ? JSON.stringify(body) : undefined,
  })
  const j: ApiResp<T> = await r.json()
  if (!j.success) {
    throw new Error(j.message ?? j.error_message ?? 'API error')
  }
  return j.data as T
}

export function useApichanAPI() {
  async function listSources(): Promise<Source[]> {
    const d = await call<{ sources: Source[] }>('GET', '/api/apichan/sources')
    return d.sources
  }

  async function createSource(payload: {
    name: string; type: string; url: string; api_key: string; timeout: number
  }): Promise<Source> {
    const d = await call<{ source: Source }>('POST', '/api/apichan/sources', payload)
    return d.source
  }

  async function updateSource(
    id: number,
    payload: Partial<{ name: string; type: string; url: string; api_key: string; timeout: number }>
  ): Promise<Source> {
    const d = await call<{ source: Source }>('PATCH', `/api/apichan/sources/${id}`, payload)
    return d.source
  }

  async function deleteSource(id: number): Promise<void> {
    await call('DELETE', `/api/apichan/sources/${id}`)
  }

  async function listSourceServers(sourceId: number, page = 1): Promise<{
    servers: NormalizedServer[]
    total: number
    current_page: number
    total_pages: number
  }> {
    return await call<{
      servers: NormalizedServer[]
      total: number
      current_page: number
      total_pages: number
    }>('GET', '/api/apichan/sources/' + sourceId + '/servers?page=' + page)
  }

  async function importServer(payload: ImportPayload): Promise<unknown> {
    return await call('POST', '/api/apichan/import', payload)
  }

  async function listRemoteServers(): Promise<RemoteServer[]> {
    const d = await call<{ servers: RemoteServer[] }>('GET', '/api/apichan/remote-servers')
    return d.servers
  }

  async function addRemoteServer(payload: {
    source_id: number
    remote_server_id: string
    remote_server_identifier: string | null
    name: string
  }): Promise<{ id: number }> {
    return await call<{ id: number }>('POST', '/api/apichan/remote-servers', payload)
  }

  async function removeRemoteServer(id: number): Promise<void> {
    await call('DELETE', `/api/apichan/remote-servers/${id}`)
  }

  async function getServerStatus(id: number): Promise<ServerStatus> {
    const d = await call<{ resources: ServerStatus }>('GET', `/api/apichan/remote-servers/${id}/status`)
    return d.resources
  }

  async function remotePowerAction(id: number, action: 'start' | 'stop' | 'restart' | 'kill'): Promise<void> {
    await call('POST', `/api/apichan/remote-servers/${id}/power`, { action })
  }

  async function remoteSendCommand(id: number, command: string): Promise<void> {
    await call('POST', `/api/apichan/remote-servers/${id}/command`, { command })
  }

  async function remoteListFiles(id: number, directory: string): Promise<FileEntry[]> {
    const d = await call<{ files: FileEntry[] }>('GET', `/api/apichan/remote-servers/${id}/files?directory=${encodeURIComponent(directory)}`)
    return d.files
  }

  async function remoteGetFileContent(id: number, file: string): Promise<string> {
    const d = await call<{ content: string }>('GET', `/api/apichan/remote-servers/${id}/files/content?file=${encodeURIComponent(file)}`)
    return d.content
  }

  async function remoteWriteFile(id: number, file: string, content: string): Promise<void> {
    await call('POST', `/api/apichan/remote-servers/${id}/files/write`, { file, content })
  }

  async function remoteListAllocations(id: number): Promise<Allocation[]> {
    const d = await call<{ allocations: Allocation[] }>('GET', `/api/apichan/remote-servers/${id}/allocations`)
    return d.allocations
  }

  async function remoteListSchedules(id: number): Promise<Schedule[]> {
    const d = await call<{ schedules: Schedule[] }>('GET', `/api/apichan/remote-servers/${id}/schedules`)
    return d.schedules
  }

  async function remoteListBackups(id: number): Promise<Backup[]> {
    const d = await call<{ backups: Backup[] }>('GET', `/api/apichan/remote-servers/${id}/backups`)
    return d.backups
  }

  async function remoteCreateBackup(id: number): Promise<Backup> {
    const d = await call<{ backup: Backup }>('POST', `/api/apichan/remote-servers/${id}/backups`)
    return d.backup
  }

  async function remoteDeleteBackup(id: number, backupId: string): Promise<void> {
    await call('DELETE', `/api/apichan/remote-servers/${id}/backups/${backupId}`)
  }

  async function remoteListDatabases(id: number): Promise<Database[]> {
    const d = await call<{ databases: Database[] }>('GET', `/api/apichan/remote-servers/${id}/databases`)
    return d.databases
  }

  async function testConnection(payload: {
    type: string; url: string; api_key: string; timeout?: number
  }): Promise<{ server_count: number }> {
    return await call<{ server_count: number }>('POST', '/api/apichan/sources/test', payload)
  }

  // ── User-owned source management (auth-only, per-user) ────────────────────────
  async function listUserSources(): Promise<Source[]> {
    const d = await call<{ sources: Source[] }>('GET', '/api/apichan/user/sources')
    return d.sources
  }
  async function createUserSource(payload: { name: string; type: string; url: string; api_key: string; timeout: number }): Promise<Source> {
    const d = await call<{ source: Source }>('POST', '/api/apichan/user/sources', payload)
    return d.source
  }
  async function updateUserSource(id: number, payload: Partial<{ name: string; type: string; url: string; api_key: string; timeout: number }>): Promise<Source> {
    const d = await call<{ source: Source }>('PATCH', `/api/apichan/user/sources/${id}`, payload)
    return d.source
  }
  async function deleteUserSource(id: number): Promise<void> {
    await call('DELETE', `/api/apichan/user/sources/${id}`)
  }
  async function listUserSourceServers(sourceId: number, page = 1): Promise<{ servers: NormalizedServer[]; total: number; current_page: number; total_pages: number }> {
    return await call<{ servers: NormalizedServer[]; total: number; current_page: number; total_pages: number }>('GET', `/api/apichan/user/sources/${sourceId}/servers?page=${page}`)
  }
  async function testUserConnection(payload: { type: string; url: string; api_key: string; timeout?: number }): Promise<{ server_count: number }> {
    return await call<{ server_count: number }>('POST', '/api/apichan/user/sources/test', payload)
  }

  // ── File operations ────────────────────────────────────────────────────────────
  async function remoteDeleteFiles(id: number, root: string, files: string[]): Promise<void> {
    await call('POST', `/api/apichan/remote-servers/${id}/files/delete`, { root, files })
  }
  async function remoteRenameFile(id: number, root: string, from: string, to: string): Promise<void> {
    await call('PUT', `/api/apichan/remote-servers/${id}/files/rename`, { root, from, to })
  }
  async function remoteCreateFolder(id: number, root: string, name: string): Promise<void> {
    await call('POST', `/api/apichan/remote-servers/${id}/files/mkdir`, { root, name })
  }
  async function remoteCompressFiles(id: number, root: string, files: string[]): Promise<FileEntry> {
    const d = await call<{ file: FileEntry }>('POST', `/api/apichan/remote-servers/${id}/files/compress`, { root, files })
    return d.file
  }
  async function remoteDecompressFile(id: number, root: string, file: string): Promise<void> {
    await call('POST', `/api/apichan/remote-servers/${id}/files/decompress`, { root, file })
  }

  // ── Startup ────────────────────────────────────────────────────────────────────
  async function remoteGetStartup(id: number): Promise<StartupConfig> {
    return await call<StartupConfig>('GET', `/api/apichan/remote-servers/${id}/startup`)
  }
  async function remoteUpdateStartupVariable(id: number, key: string, value: string): Promise<{ env_variable: string; server_value: string }> {
    return await call<{ env_variable: string; server_value: string }>('PUT', `/api/apichan/remote-servers/${id}/startup/variable`, { key, value })
  }

  // ── Console WebSocket credentials ──────────────────────────────────────────────
  async function getRemoteWebsocket(id: number): Promise<{ token: string; socket: string }> {
    return await call<{ token: string; socket: string }>('GET', `/api/apichan/remote-servers/${id}/websocket`)
  }

  async function getRemoteServer(id: string): Promise<any> {
    return await call('GET', `/api/apichan/remote-servers/${id}`)
  }

  async function getRemoteServerDetails(id: string): Promise<any> {
    return await call('GET', `/api/apichan/remote-servers/${id}/details`)
  }

  async function getRemoteServerStats(id: string): Promise<any> {
    return await call('GET', `/api/apichan/remote-servers/${id}/resources`)
  }

  async function sendRemoteServerPower(id: string, action: string): Promise<void> {
    await call('POST', `/api/apichan/remote-servers/${id}/power`, { action })
  }

  async function sendRemoteServerCommand(id: string, command: string): Promise<void> {
    await call('POST', `/api/apichan/remote-servers/${id}/command`, { command })
  }

  async function getRemoteServerFiles(id: string, directory: string): Promise<any[]> {
    const d = await call<{ files: any[] }>('GET', `/api/apichan/remote-servers/${id}/files/list?directory=${encodeURIComponent(directory)}`)
    return d.files
  }

  async function getRemoteServerFileContent(id: string, file: string): Promise<any> {
    return await call('GET', `/api/apichan/remote-servers/${id}/files/contents?file=${encodeURIComponent(file)}`)
  }

  async function writeRemoteServerFile(id: string, file: string, content: string): Promise<void> {
    await call('POST', `/api/apichan/remote-servers/${id}/files/write`, { file, content })
  }

  async function createRemoteServerFolder(id: string, root: string, name: string): Promise<void> {
    await call('POST', `/api/apichan/remote-servers/${id}/files/create-folder`, { root, name })
  }

  async function deleteRemoteServerFile(id: string, root: string, files: string[]): Promise<void> {
    await call('POST', `/api/apichan/remote-servers/${id}/files/delete`, { root, files })
  }

  async function renameRemoteServerFile(id: string, root: string, from: string, to: string): Promise<void> {
    await call('POST', `/api/apichan/remote-servers/${id}/files/rename`, { root, from, to })
  }

  async function copyRemoteServerFile(id: string, location: string, files: string[]): Promise<void> {
    await call('POST', `/api/apichan/remote-servers/${id}/files/copy`, { location, files })
  }

  async function compressRemoteServerFiles(id: string, root: string, files: string[]): Promise<void> {
    await call('POST', `/api/apichan/remote-servers/${id}/files/compress`, { root, files })
  }

  async function decompressRemoteServerFile(id: string, root: string, file: string): Promise<void> {
    await call('POST', `/api/apichan/remote-servers/${id}/files/decompress`, { root, file })
  }

  async function uploadRemoteServerFile(id: string, directory: string, file: File, onProgress?: (percent: number) => void): Promise<void> {
    const formData = new FormData()
    formData.append('files[]', file)
    
    const xhr = new XMLHttpRequest()
    
    return new Promise((resolve, reject) => {
      xhr.upload.addEventListener('progress', (e) => {
        if (e.lengthComputable && onProgress) {
          onProgress(Math.round((e.loaded / e.total) * 100))
        }
      })
      
      xhr.addEventListener('load', () => {
        if (xhr.status >= 200 && xhr.status < 300) {
          resolve()
        } else {
          reject(new Error('Upload failed'))
        }
      })
      
      xhr.addEventListener('error', () => reject(new Error('Upload failed')))
      
      xhr.open('POST', `/api/apichan/remote-servers/${id}/files/upload?directory=${encodeURIComponent(directory)}`)
      xhr.withCredentials = true
      xhr.send(formData)
    })
  }

  async function downloadRemoteServerFile(id: string, file: string): Promise<{ download_url: string }> {
    return await call('GET', `/api/apichan/remote-servers/${id}/files/download?file=${encodeURIComponent(file)}`)
  }

  async function setRemoteServerFilePermissions(id: string, file: string, mode: string): Promise<void> {
    await call('POST', `/api/apichan/remote-servers/${id}/files/chmod`, { file, mode })
  }

  async function getRemoteServerBackups(id: string, params?: { page?: number; per_page?: number }): Promise<{ data: any[]; meta: { total: number } }> {
    const query = new URLSearchParams()
    if (params?.page) query.append('page', params.page.toString())
    if (params?.per_page) query.append('per_page', params.per_page.toString())
    return await call('GET', `/api/apichan/remote-servers/${id}/backups?${query}`)
  }

  async function createRemoteServerBackup(id: string, params: any): Promise<void> {
    await call('POST', `/api/apichan/remote-servers/${id}/backups`, params)
  }

  async function deleteRemoteServerBackup(id: string, backupUuid: string): Promise<void> {
    await call('DELETE', `/api/apichan/remote-servers/${id}/backups/${backupUuid}`)
  }

  async function restoreRemoteServerBackup(id: string, params: { backup_uuid: string; truncate?: boolean }): Promise<void> {
    await call('POST', `/api/apichan/remote-servers/${id}/backups/restore`, params)
  }

  async function toggleRemoteServerBackupLock(id: string, backupUuid: string): Promise<void> {
    await call('POST', `/api/apichan/remote-servers/${id}/backups/${backupUuid}/lock`)
  }

  async function downloadRemoteServerBackup(id: string, backupUuid: string): Promise<{ download_url: string }> {
    return await call('GET', `/api/apichan/remote-servers/${id}/backups/${backupUuid}/download`)
  }

  async function getRemoteServerDatabases(id: string): Promise<{ data: any[] }> {
    return await call('GET', `/api/apichan/remote-servers/${id}/databases`)
  }

  async function createRemoteServerDatabase(id: string, params: any): Promise<void> {
    await call('POST', `/api/apichan/remote-servers/${id}/databases`, params)
  }

  async function deleteRemoteServerDatabase(id: string, databaseId: string): Promise<void> {
    await call('DELETE', `/api/apichan/remote-servers/${id}/databases/${databaseId}`)
  }

  async function rotateRemoteServerDatabasePassword(id: string, params: { database_id: string }): Promise<void> {
    await call('POST', `/api/apichan/remote-servers/${id}/databases/${params.database_id}/rotate-password`)
  }

  async function getRemoteServerSchedules(id: string): Promise<{ data: any[] }> {
    return await call('GET', `/api/apichan/remote-servers/${id}/schedules`)
  }

  async function createRemoteServerSchedule(id: string, params: any): Promise<void> {
    await call('POST', `/api/apichan/remote-servers/${id}/schedules`, params)
  }

  async function updateRemoteServerSchedule(id: string, scheduleId: number, params: any): Promise<void> {
    await call('PATCH', `/api/apichan/remote-servers/${id}/schedules/${scheduleId}`, params)
  }

  async function deleteRemoteServerSchedule(id: string, scheduleId: number): Promise<void> {
    await call('DELETE', `/api/apichan/remote-servers/${id}/schedules/${scheduleId}`)
  }

  async function triggerRemoteServerSchedule(id: string, scheduleId: number): Promise<void> {
    await call('POST', `/api/apichan/remote-servers/${id}/schedules/${scheduleId}/execute`)
  }

  async function createRemoteServerScheduleTask(id: string, scheduleId: number, params: any): Promise<void> {
    await call('POST', `/api/apichan/remote-servers/${id}/schedules/${scheduleId}/tasks`, params)
  }

  async function updateRemoteServerScheduleTask(id: string, scheduleId: number, taskId: number, params: any): Promise<void> {
    await call('PATCH', `/api/apichan/remote-servers/${id}/schedules/${scheduleId}/tasks/${taskId}`, params)
  }

  async function deleteRemoteServerScheduleTask(id: string, scheduleId: number, taskId: number): Promise<void> {
    await call('DELETE', `/api/apichan/remote-servers/${id}/schedules/${scheduleId}/tasks/${taskId}`)
  }

  return {
    listSources, createSource, updateSource, deleteSource, listSourceServers, importServer, testConnection,
    listUserSources, createUserSource, updateUserSource, deleteUserSource, listUserSourceServers, testUserConnection,
    listRemoteServers, addRemoteServer, removeRemoteServer,
    getServerStatus, remotePowerAction, remoteSendCommand,
    remoteListFiles, remoteGetFileContent, remoteWriteFile,
    remoteDeleteFiles, remoteRenameFile, remoteCreateFolder, remoteCompressFiles, remoteDecompressFile,
    remoteListAllocations, remoteListSchedules,
    remoteListBackups, remoteCreateBackup, remoteDeleteBackup,
    remoteListDatabases,
    remoteGetStartup, remoteUpdateStartupVariable,
    getRemoteWebsocket,
    getRemoteServer,
    getRemoteServerDetails,
    getRemoteServerStats,
    sendRemoteServerPower,
    sendRemoteServerCommand,
    getRemoteServerFiles,
    getRemoteServerFileContent,
    writeRemoteServerFile,
    createRemoteServerFolder,
    deleteRemoteServerFile,
    renameRemoteServerFile,
    copyRemoteServerFile,
    compressRemoteServerFiles,
    decompressRemoteServerFile,
    uploadRemoteServerFile,
    downloadRemoteServerFile,
    setRemoteServerFilePermissions,
    getRemoteServerBackups,
    createRemoteServerBackup,
    deleteRemoteServerBackup,
    restoreRemoteServerBackup,
    toggleRemoteServerBackupLock,
    downloadRemoteServerBackup,
    getRemoteServerDatabases,
    createRemoteServerDatabase,
    deleteRemoteServerDatabase,
    rotateRemoteServerDatabasePassword,
    getRemoteServerSchedules,
    createRemoteServerSchedule,
    updateRemoteServerSchedule,
    deleteRemoteServerSchedule,
    triggerRemoteServerSchedule,
    createRemoteServerScheduleTask,
    updateRemoteServerScheduleTask,
    deleteRemoteServerScheduleTask,
  }
}
