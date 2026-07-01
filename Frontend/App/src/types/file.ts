export interface RemoteFile {
  name: string
  mode: string
  mode_bits?: string
  size: number
  is_file: boolean
  is_symlink: boolean
  mimetype: string
  created_at: string
  modified_at: string
  is_editable?: boolean
}

export interface FileUploadProgress {
  file: File
  progress: number
  status: 'pending' | 'uploading' | 'completed' | 'error'
  error?: string
}

export interface FileContent {
  content: string
  encoding?: string
}

export interface FilePermissions {
  owner: string
  group: string
  mode: string
}

export interface FileHash {
  sha256: string
}

export interface ArchiveFile {
  name: string
  size: number
  is_file: boolean
}

export interface FileSearchParams {
  query: string
  path?: string
  content?: boolean
  case_sensitive?: boolean
  include_patterns?: string[]
  exclude_patterns?: string[]
  min_size?: number
  max_size?: number
}

export interface FileSearchResult {
  path: string
  matches: number
}
