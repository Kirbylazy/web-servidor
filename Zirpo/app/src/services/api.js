const BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost:3000/api'

const getHeaders = () => {
  const token = localStorage.getItem('token')
  return {
    'Content-Type': 'application/json',
    ...(token ? { Authorization: `Bearer ${token}` } : {}),
  }
}

const request = async (method, path, body) => {
  const res = await fetch(`${BASE_URL}${path}`, {
    method,
    headers: getHeaders(),
    ...(body ? { body: JSON.stringify(body) } : {}),
  })

  const data = await res.json()

  if (!res.ok) throw new Error(data.error || 'Error en la petición')

  return data
}

export const api = {
  post: (path, body) => request('POST', path, body),
  get: (path) => request('GET', path),
  put: (path, body) => request('PUT', path, body),
  patch: (path, body) => request('PATCH', path, body),
  delete: (path) => request('DELETE', path),
}
