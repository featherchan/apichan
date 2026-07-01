export function validateFileName(name: string): string | null {
  if (!name || name.trim() === '') {
    return 'File name cannot be empty'
  }

  if (name.includes('/') || name.includes('\\')) {
    return 'File name cannot contain / or \\'
  }

  if (name === '.' || name === '..') {
    return 'Invalid file name'
  }

  if (name.length > 255) {
    return 'File name is too long (max 255 characters)'
  }

  return null
}

export function validatePath(path: string): string | null {
  if (!path) {
    return 'Path cannot be empty'
  }

  if (!path.startsWith('/')) {
    return 'Path must start with /'
  }

  return null
}

export function validateCronExpression(minute: string, hour: string, dayOfMonth: string, month: string, dayOfWeek: string): string | null {
  const validateField = (value: string, min: number, max: number, name: string): string | null => {
    if (value === '*') return null

    if (value.includes(',')) {
      const parts = value.split(',')
      for (const part of parts) {
        const error = validateField(part.trim(), min, max, name)
        if (error) return error
      }
      return null
    }

    if (value.includes('/')) {
      const [range, step] = value.split('/')
      if (range !== '*') {
        const error = validateField(range, min, max, name)
        if (error) return error
      }
      const stepNum = parseInt(step)
      if (isNaN(stepNum) || stepNum < 1) {
        return `Invalid step value in ${name}`
      }
      return null
    }

    if (value.includes('-')) {
      const [start, end] = value.split('-')
      const startNum = parseInt(start)
      const endNum = parseInt(end)
      if (isNaN(startNum) || isNaN(endNum) || startNum < min || endNum > max || startNum > endNum) {
        return `Invalid range in ${name}`
      }
      return null
    }

    const num = parseInt(value)
    if (isNaN(num) || num < min || num > max) {
      return `${name} must be between ${min} and ${max}`
    }

    return null
  }

  const minuteError = validateField(minute, 0, 59, 'minute')
  if (minuteError) return minuteError

  const hourError = validateField(hour, 0, 23, 'hour')
  if (hourError) return hourError

  const dayOfMonthError = validateField(dayOfMonth, 1, 31, 'day of month')
  if (dayOfMonthError) return dayOfMonthError

  const monthError = validateField(month, 1, 12, 'month')
  if (monthError) return monthError

  const dayOfWeekError = validateField(dayOfWeek, 0, 6, 'day of week')
  if (dayOfWeekError) return dayOfWeekError

  return null
}

export function validateDatabaseName(name: string): string | null {
  if (!name || name.trim() === '') {
    return 'Database name cannot be empty'
  }

  if (!/^[a-zA-Z0-9_]+$/.test(name)) {
    return 'Database name can only contain letters, numbers, and underscores'
  }

  if (name.length > 64) {
    return 'Database name is too long (max 64 characters)'
  }

  return null
}

export function validateRemoteHost(host: string): string | null {
  if (!host || host.trim() === '') {
    return 'Remote host cannot be empty'
  }

  if (host.includes(' ')) {
    return 'Remote host cannot contain spaces'
  }

  return null
}
