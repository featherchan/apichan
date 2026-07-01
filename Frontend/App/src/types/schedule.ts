export interface RemoteSchedule {
  id: number
  name: string
  cron: {
    day_of_week: string
    day_of_month: string
    month: string
    hour: string
    minute: string
  }
  is_active: boolean
  is_processing: boolean
  only_when_online: boolean
  last_run_at: string | null
  next_run_at: string | null
  created_at: string
  updated_at: string
  relationships?: {
    tasks?: RemoteScheduleTask[]
  }
}

export interface RemoteScheduleTask {
  id: number
  sequence_id: number
  action: 'command' | 'power' | 'backup'
  payload: string
  time_offset: number
  is_queued: boolean
  continue_on_failure: boolean
  created_at: string
  updated_at: string
}

export interface CreateScheduleParams {
  name: string
  minute: string
  hour: string
  day_of_month: string
  month: string
  day_of_week: string
  is_active?: boolean
  only_when_online?: boolean
}

export interface CreateTaskParams {
  action: 'command' | 'power' | 'backup'
  payload: string
  time_offset: number
  continue_on_failure?: boolean
}
