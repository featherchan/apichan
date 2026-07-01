export function hasPermission(permission: string, userPermissions: string[]): boolean {
  if (userPermissions.includes('*')) {
    return true
  }

  return userPermissions.includes(permission)
}

export function canAccessFiles(userPermissions: string[]): boolean {
  return hasPermission('file.read', userPermissions)
}

export function canEditFiles(userPermissions: string[]): boolean {
  return hasPermission('file.write', userPermissions)
}

export function canDeleteFiles(userPermissions: string[]): boolean {
  return hasPermission('file.delete', userPermissions)
}

export function canManageBackups(userPermissions: string[]): boolean {
  return hasPermission('backup.create', userPermissions)
}

export function canManageDatabases(userPermissions: string[]): boolean {
  return hasPermission('database.create', userPermissions)
}

export function canControlPower(userPermissions: string[]): boolean {
  return hasPermission('control.start', userPermissions) || 
         hasPermission('control.stop', userPermissions) || 
         hasPermission('control.restart', userPermissions)
}
