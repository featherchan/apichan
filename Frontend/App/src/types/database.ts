export interface RemoteDatabase {
  id: string
  name: string
  username: string
  remote: string
  max_connections: number
  created_at: string
  relationships?: {
    password?: {
      password: string
    }
  }
}

export interface CreateDatabaseParams {
  database: string
  remote: string
}

export interface RotateDatabasePasswordParams {
  database_id: string
}

export interface DatabaseCredentials {
  host: string
  port: number
  database: string
  username: string
  password: string
}
