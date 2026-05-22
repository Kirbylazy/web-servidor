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

// --- Nominatim geocoding ---

const NOMINATIM_URL = 'https://nominatim.openstreetmap.org'

export async function searchCities(query, limit = 5) {
  if (!query || query.length < 2) return []
  const params = new URLSearchParams({
    q: query,
    format: 'json',
    addressdetails: '1',
    limit: String(limit),
    countrycodes: 'es',
    'accept-language': 'es',
  })
  const res = await fetch(`${NOMINATIM_URL}/search?${params}`, {
    headers: { 'User-Agent': 'Zirpo-App' },
  })
  const data = await res.json()
  return data
    .filter(r => ['city', 'town', 'village', 'municipality'].includes(r.type) ||
      r.class === 'place' || r.class === 'boundary')
    .map(r => ({
      name: r.address?.city || r.address?.town || r.address?.village || r.address?.municipality || r.display_name.split(',')[0],
      displayName: r.display_name,
      lat: parseFloat(r.lat),
      lng: parseFloat(r.lon),
    }))
}

export async function reverseGeocode(lat, lng) {
  const params = new URLSearchParams({
    lat: String(lat),
    lon: String(lng),
    format: 'json',
    addressdetails: '1',
    'accept-language': 'es',
  })
  const res = await fetch(`${NOMINATIM_URL}/reverse?${params}`, {
    headers: { 'User-Agent': 'Zirpo-App' },
  })
  const data = await res.json()
  return {
    address: data.display_name || '',
    city: data.address?.city || data.address?.town || data.address?.village || '',
    lat: parseFloat(data.lat),
    lng: parseFloat(data.lon),
  }
}

export async function searchAddress(query, lat, lng) {
  if (!query || query.length < 3) return []
  const params = new URLSearchParams({
    q: query,
    format: 'json',
    limit: '5',
    countrycodes: 'es',
    'accept-language': 'es',
  })
  if (lat && lng) {
    params.set('viewbox', `${lng - 0.1},${lat + 0.1},${lng + 0.1},${lat - 0.1}`)
    params.set('bounded', '0')
  }
  const res = await fetch(`${NOMINATIM_URL}/search?${params}`, {
    headers: { 'User-Agent': 'Zirpo-App' },
  })
  const data = await res.json()
  return data.map(r => ({
    name: r.display_name.split(',')[0],
    address: r.display_name,
    lat: parseFloat(r.lat),
    lng: parseFloat(r.lon),
  }))
}

// --- OSRM routing ---

const OSRM_URL = 'https://router.project-osrm.org'

export async function calcRoute(waypoints) {
  // waypoints: array of { lat, lng } — minimum 2 (origin + destination)
  if (waypoints.length < 2) throw new Error('Se necesitan al menos 2 puntos')

  const coords = waypoints.map(w => `${w.lng},${w.lat}`).join(';')
  const res = await fetch(
    `${OSRM_URL}/route/v1/driving/${coords}?overview=full&geometries=polyline&steps=false&annotations=false`
  )
  const data = await res.json()
  if (data.code !== 'Ok' || !data.routes?.length) throw new Error('No se pudo calcular la ruta')

  const route = data.routes[0]
  const legs = route.legs.map((leg, i) => ({
    distanceKm: Math.round(leg.distance / 1000),
    durationMin: Math.round(leg.duration / 60),
    startLat: waypoints[i].lat,
    startLng: waypoints[i].lng,
    endLat: waypoints[i + 1].lat,
    endLng: waypoints[i + 1].lng,
  }))

  return {
    legs,
    polyline: route.geometry, // encoded polyline (precision 5)
    totalDistanceKm: Math.round(route.distance / 1000),
    totalDurationMin: Math.round(route.duration / 60),
  }
}

// --- Geocode a city name to get lat/lng ---

export async function geocodeCity(cityName) {
  const results = await searchCities(cityName + ', España', 1)
  if (!results.length) throw new Error(`No se encontró: ${cityName}`)
  return { lat: results[0].lat, lng: results[0].lng, name: results[0].name }
}

// --- Polyline decoding (Leaflet built-in doesn't have it, so we do it manually) ---

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
