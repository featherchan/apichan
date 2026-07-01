export interface RemoteServer {
  id: number
  user_id: number
  source_id: number
  remote_server_id: string
  remote_server_identifier: string | null
  name: string
  created_at: string
}

export interface RemoteServerDetails {
  id: string
  identifier: string
  uuid: string
  name: string
  description: string
  status: 'running' | 'offline' | 'starting' | 'stopping'
  is_suspended: boolean
  is_installing: boolean
  limits: {
    memory: number
    disk: number
    cpu: number
    swap: number
    io: number
  }
  feature_limits: {
    databases: number
    allocations: number
    backups: number
  }
  relationships?: {
    allocations?: RemoteAllocation[]
  }
}

export interface RemoteAllocation {
  id: number
  ip: string
  ip_alias: string | null
  port: number
  notes: string | null
  is_default: boolean
}

export interface RemoteServerStats {
  current_state: string
  is_suspended: boolean
  resources: {
    memory_bytes: number
    memory_limit_bytes: number
    cpu_absolute: number
    network_rx_bytes: number
    network_tx_bytes: number
    uptime: number
    disk_bytes: number
  }
}

export interface PowerAction {
  action: 'start' | 'stop' | 'restart' | 'kill'
}
