export interface RemoteBackup {
  uuid: string
  name: string
  ignored_files: string[]
  sha256_hash: string | null
  bytes: number
  created_at: string
  completed_at: string | null
  is_successful: boolean
  is_locked: boolean
  checksum: string | null
}

export interface CreateBackupParams {
  name?: string
  ignored_files?: string[]
  is_locked?: boolean
}

export interface RestoreBackupParams {
  backup_uuid: string
  truncate?: boolean
}
