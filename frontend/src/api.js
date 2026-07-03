const configuredApiBase = import.meta.env.VITE_API_BASE_URL?.trim()
const runtimeApiBase = `${window.location.protocol}//${window.location.hostname}:8000/api/v1`
const API_BASE_URL = configuredApiBase || runtimeApiBase

export const tokenStore = {
  get access() { return sessionStorage.getItem('access_token') },
  get refresh() { return sessionStorage.getItem('refresh_token') },
  save(tokens) {
    if (!tokens) return
    sessionStorage.setItem('access_token', tokens.access_token)
    sessionStorage.setItem('refresh_token', tokens.refresh_token)
  },
  clear() {
    sessionStorage.removeItem('access_token')
    sessionStorage.removeItem('refresh_token')
  }
}

export async function api(path, options = {}, retry = true) {
  const headers = new Headers(options.headers || {})
  headers.set('Accept', 'application/json')
  if (!(options.body instanceof FormData)) headers.set('Content-Type', 'application/json')
  if (tokenStore.access) headers.set('Authorization', `Bearer ${tokenStore.access}`)

  const response = await fetch(`${API_BASE_URL}${path}`, { ...options, headers })
  const contentType = response.headers.get('content-type') || ''
  const payload = contentType.includes('application/json') ? await response.json() : null

  if (response.status === 401 && retry && tokenStore.refresh && path !== '/auth/refresh') {
    const refreshed = await fetch(`${API_BASE_URL}/auth/refresh`, {
      method: 'POST',
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify({ refresh_token: tokenStore.refresh })
    })
    if (refreshed.ok) {
      const data = await refreshed.json()
      tokenStore.save(data.tokens)
      return api(path, options, false)
    }
    tokenStore.clear()
  }

  if (!response.ok) {
    const message = payload?.message || `Error HTTP ${response.status}`
    const error = new Error(message)
    error.status = response.status
    error.payload = payload
    throw error
  }

  return payload
}

export function money(value) {
  return Number(value || 0).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
