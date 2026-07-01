export interface RemoteActivity {
  id: string
  batch: string | null
  event: string
  ip: string
  description: string | null
  properties: Record<string, any>
  has_additional_metadata: boolean
  timestamp: string
  relationships?: {
    actor?: {
      uuid: string
      username: string
      email: string
    }
  }
}

export type ActivityEventType = 
  | 'server:power'
  | 'server:console'
  | 'server:backup'
  | 'server:file'
  | 'server:database'
  | 'server:schedule'
  | 'server:task'
  | 'server:subuser'
  | 'server:allocation'
  | 'server:settings'
  | 'server:startup'

export interface ActivityFilters {
  event?: string
  search?: string
  page?: number
  per_page?: number
}
