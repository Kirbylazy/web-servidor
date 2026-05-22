import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

// Fix default marker icons in Leaflet (broken by bundlers)
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png'
import markerIcon from 'leaflet/dist/images/marker-icon.png'
import markerShadow from 'leaflet/dist/images/marker-shadow.png'

delete L.Icon.Default.prototype._getIconUrl
L.Icon.Default.mergeOptions({
  iconRetinaUrl: markerIcon2x,
  iconUrl: markerIcon,
  shadowUrl: markerShadow,
})

// --- Service URLs (local self-hosted) ---

const BASE_URL = import.meta.env.VITE_API_URL?.replace('/api', '') || 'http://localhost:3000'
const GEOCODER_URL = import.meta.env.VITE_GEOCODER_URL || `${BASE_URL.replace(':3000', ':2322')}`
const ROUTER_URL = import.meta.env.VITE_ROUTER_URL || `${BASE_URL.replace(':3000', ':8080')}`

// --- Tile layer ---

const TILE_URL = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'
const TILE_ATTR = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'

export function createMap(container, options = {}) {
  const map = L.map(container, {
    zoomControl: true,
    ...options,
  })
  L.tileLayer(TILE_URL, { attribution: TILE_ATTR, maxZoom: 19 }).addTo(map)
  return map
}

// --- Local geocoder (puerto 2322) ---

export async function searchCities(query, limit = 5) {
  if (!query || query.length < 2) return []
  const params = new URLSearchParams({ q: query, limit: String(limit) })
  const res = await fetch(`${GEOCODER_URL}/search?${params}`)
  const data = await res.json()
  return data.map(r => ({
    name: r.name,
    displayName: r.name + ', España',
    lat: r.lat,
    lng: r.lng,
  }))
}

export async function reverseGeocode(lat, lng) {
  const params = new URLSearchParams({ lat: String(lat), lng: String(lng) })
  const res = await fetch(`${GEOCODER_URL}/reverse?${params}`)
  const data = await res.json()
  return {
    address: data.name + ', España',
    city: data.name || '',
    lat: data.lat,
    lng: data.lng,
  }
}

export async function searchAddress(query, lat, lng) {
  if (!query || query.length < 3) return []
  const params = new URLSearchParams({ q: query, lat: String(lat), lng: String(lng), limit: '5' })
  const res = await fetch(`${GEOCODER_URL}/address?${params}`)
  const data = await res.json()
  return data.map(r => ({
    name: r.name,
    address: r.address,
    lat: r.lat,
    lng: r.lng,
  }))
}

// --- GraphHopper routing (puerto 8080) ---

function haversineDist(lat1, lng1, lat2, lng2) {
  const toRad = v => v * Math.PI / 180
  const dLat = toRad(lat2 - lat1)
  const dLng = toRad(lng2 - lng1)
  const a = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLng / 2) ** 2
  return 6371 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a))
}

function closestPathIndex(path, lat, lng) {
  let best = 0, bestDist = Infinity
  for (let i = 0; i < path.length; i++) {
    const d = (path[i][0] - lat) ** 2 + (path[i][1] - lng) ** 2
    if (d < bestDist) { bestDist = d; best = i }
  }
  return best
}

function pathDistanceKm(path, fromIdx, toIdx) {
  let dist = 0
  for (let i = fromIdx; i < toIdx; i++) {
    dist += haversineDist(path[i][0], path[i][1], path[i + 1][0], path[i + 1][1])
  }
  return Math.round(dist)
}

export async function calcRoute(waypoints) {
  if (waypoints.length < 2) throw new Error('Se necesitan al menos 2 puntos')

  const points = waypoints.map(w => `point=${w.lat},${w.lng}`).join('&')
  const res = await fetch(
    `${ROUTER_URL}/route?${points}&profile=car&type=json&points_encoded=true`
  )
  const data = await res.json()
  if (data.message) throw new Error(data.message)
  if (!data.paths?.length) throw new Error('No se pudo calcular la ruta')

  const path = data.paths[0]
  const totalDistanceKm = Math.round(path.distance / 1000)
  const totalDurationMin = Math.round(path.time / 60000)

  let legs
  if (path.legs && path.legs.length) {
    legs = path.legs.map((leg, i) => ({
      distanceKm: Math.round(leg.distance / 1000),
      durationMin: Math.round(leg.time / 60000),
      startLat: waypoints[i].lat,
      startLng: waypoints[i].lng,
      endLat: waypoints[i + 1].lat,
      endLng: waypoints[i + 1].lng,
    }))
  } else if (waypoints.length === 2) {
    legs = [{
      distanceKm: totalDistanceKm,
      durationMin: totalDurationMin,
      startLat: waypoints[0].lat,
      startLng: waypoints[0].lng,
      endLat: waypoints[1].lat,
      endLng: waypoints[1].lng,
    }]
  } else {
    // Calculate real distances per leg using the polyline
    const decoded = decodePolyline(path.points)
    const wpIndices = waypoints.map(w => closestPathIndex(decoded, w.lat, w.lng))
    // Ensure indices are monotonically increasing
    for (let i = 1; i < wpIndices.length; i++) {
      if (wpIndices[i] <= wpIndices[i - 1]) wpIndices[i] = wpIndices[i - 1] + 1
    }

    legs = []
    for (let i = 0; i < waypoints.length - 1; i++) {
      const distKm = pathDistanceKm(decoded, wpIndices[i], wpIndices[i + 1])
      legs.push({
        distanceKm: distKm,
        durationMin: totalDurationMin > 0 ? Math.round(totalDurationMin * (distKm / totalDistanceKm)) : 0,
        startLat: waypoints[i].lat,
        startLng: waypoints[i].lng,
        endLat: waypoints[i + 1].lat,
        endLng: waypoints[i + 1].lng,
      })
    }
  }

  return {
    legs,
    polyline: path.points,
    totalDistanceKm,
    totalDurationMin,
  }
}

// --- Geocode a city name to get lat/lng ---

export async function geocodeCity(cityName) {
  const results = await searchCities(cityName, 1)
  if (!results.length) throw new Error(`No se encontró: ${cityName}`)
  return { lat: results[0].lat, lng: results[0].lng, name: results[0].name }
}

// --- Polyline decoding ---
// GraphHopper uses precision 5 by default (same as Google/OSRM)

export function decodePolyline(encoded) {
  const points = []
  let index = 0, lat = 0, lng = 0

  while (index < encoded.length) {
    let b, shift = 0, result = 0
    do {
      b = encoded.charCodeAt(index++) - 63
      result |= (b & 0x1f) << shift
      shift += 5
    } while (b >= 0x20)
    lat += (result & 1) ? ~(result >> 1) : (result >> 1)

    shift = 0; result = 0
    do {
      b = encoded.charCodeAt(index++) - 63
      result |= (b & 0x1f) << shift
      shift += 5
    } while (b >= 0x20)
    lng += (result & 1) ? ~(result >> 1) : (result >> 1)

    points.push([lat / 1e5, lng / 1e5])
  }
  return points
}

// --- Leaflet helpers ---

export function addPolyline(map, encoded, color = '#4F46E5', weight = 4) {
  const latlngs = decodePolyline(encoded)
  const polyline = L.polyline(latlngs, { color, weight }).addTo(map)
  return polyline
}

export function fitBoundsToPoints(map, points, padding = 40) {
  if (!points.length) return
  const bounds = L.latLngBounds(points.map(p => [p.lat ?? p[0], p.lng ?? p[1]]))
  map.fitBounds(bounds, { padding: [padding, padding] })
}

export { L }
