import { readFileSync } from 'fs'
import http from 'http'

const PORT = 2322
const GEONAMES_FILE = process.env.GEONAMES_FILE || '/mnt/m2/photon/ES.txt'

// Parse GeoNames TSV and keep only populated places
// Format: geonameid, name, asciiname, alternatenames, lat, lng, feature_class, feature_code, country_code, ...population(14)
const lines = readFileSync(GEONAMES_FILE, 'utf-8').split('\n').filter(Boolean)
const cities = []

for (const line of lines) {
  const cols = line.split('\t')
  const featureClass = cols[6]
  const featureCode = cols[7]
  // P = populated places (PPL, PPLA, PPLA2, PPLA3, PPLC, etc)
  if (featureClass !== 'P') continue
  const population = parseInt(cols[14]) || 0
  cities.push({
    name: cols[1],
    ascii: cols[2].toLowerCase(),
    alternates: (cols[3] || '').toLowerCase(),
    lat: parseFloat(cols[4]),
    lng: parseFloat(cols[5]),
    population,
    featureCode,
  })
}

// Sort by population descending for relevance
cities.sort((a, b) => b.population - a.population)

console.log(`Geocoder cargado: ${cities.length} ciudades de España`)

// Normalize: remove accents for fuzzy matching
const norm = s => s.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '')

// Search cities by name
function searchCities(query, limit = 5) {
  const q = norm(query)
  const results = []

  // Exact prefix first
  for (const city of cities) {
    if (results.length >= limit) break
    if (norm(city.name).startsWith(q) || city.ascii.startsWith(q)) {
      results.push(city)
    }
  }

  // Then contains
  if (results.length < limit) {
    for (const city of cities) {
      if (results.length >= limit) break
      if (results.includes(city)) continue
      if (norm(city.name).includes(q) || city.ascii.includes(q) || city.alternates.includes(q)) {
        results.push(city)
      }
    }
  }

  return results.map(c => ({
    name: c.name,
    lat: c.lat,
    lng: c.lng,
    population: c.population,
  }))
}

// Reverse geocode: find nearest city to coordinates
function reverseGeocode(lat, lng) {
  let minDist = Infinity
  let closest = null

  for (const city of cities) {
    const dLat = city.lat - lat
    const dLng = city.lng - lng
    const dist = dLat * dLat + dLng * dLng
    if (dist < minDist) {
      minDist = dist
      closest = city
    }
  }

  return closest ? { name: closest.name, lat: closest.lat, lng: closest.lng } : null
}

// Search addresses (for pickup points - returns same as search but biased by location)
function searchAddress(query, lat, lng, limit = 5) {
  const q = norm(query)
  const results = []

  for (const city of cities) {
    if (results.length >= limit * 3) break
    if (norm(city.name).includes(q) || city.ascii.includes(q)) {
      const dLat = city.lat - lat
      const dLng = city.lng - lng
      const dist = dLat * dLat + dLng * dLng
      results.push({ ...city, dist })
    }
  }

  results.sort((a, b) => a.dist - b.dist)

  return results.slice(0, limit).map(c => ({
    name: c.name,
    address: c.name + ', España',
    lat: c.lat,
    lng: c.lng,
  }))
}

// HTTP Server
const server = http.createServer((req, res) => {
  res.setHeader('Access-Control-Allow-Origin', '*')
  res.setHeader('Content-Type', 'application/json')

  const url = new URL(req.url, `http://localhost:${PORT}`)
  const path = url.pathname

  if (path === '/search') {
    const q = url.searchParams.get('q') || ''
    const limit = parseInt(url.searchParams.get('limit')) || 5
    const results = searchCities(q, limit)
    res.end(JSON.stringify(results))
  } else if (path === '/reverse') {
    const lat = parseFloat(url.searchParams.get('lat'))
    const lng = parseFloat(url.searchParams.get('lng'))
    if (isNaN(lat) || isNaN(lng)) {
      res.statusCode = 400
      res.end(JSON.stringify({ error: 'lat and lng required' }))
      return
    }
    const result = reverseGeocode(lat, lng)
    res.end(JSON.stringify(result))
  } else if (path === '/address') {
    const q = url.searchParams.get('q') || ''
    const lat = parseFloat(url.searchParams.get('lat')) || 40
    const lng = parseFloat(url.searchParams.get('lng')) || -3
    const limit = parseInt(url.searchParams.get('limit')) || 5
    const results = searchAddress(q, lat, lng, limit)
    res.end(JSON.stringify(results))
  } else if (path === '/health') {
    res.end(JSON.stringify({ status: 'ok', cities: cities.length }))
  } else {
    res.statusCode = 404
    res.end(JSON.stringify({ error: 'Not found' }))
  }
})

server.listen(PORT, () => {
  console.log(`Geocoder corriendo en http://localhost:${PORT}`)
})
