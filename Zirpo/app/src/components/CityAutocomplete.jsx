import { useState, useEffect, useRef } from 'react'
import { searchCities } from '../utils/mapService'

export default function CityAutocomplete({ value, onChange, placeholder, name, required }) {
  const [suggestions, setSuggestions] = useState([])
  const [open, setOpen] = useState(false)
  const [loading, setLoading] = useState(false)
  const timerRef = useRef(null)
  const wrapperRef = useRef(null)

  // Close dropdown on outside click
  useEffect(() => {
    const handleClick = (e) => {
      if (wrapperRef.current && !wrapperRef.current.contains(e.target)) {
        setOpen(false)
      }
    }
    document.addEventListener('mousedown', handleClick)
    return () => document.removeEventListener('mousedown', handleClick)
  }, [])

  const handleInput = (e) => {
    const val = e.target.value
    onChange({ target: { name, value: val } })

    clearTimeout(timerRef.current)
    if (val.length < 2) { setSuggestions([]); setOpen(false); return }

    setLoading(true)
    timerRef.current = setTimeout(async () => {
      try {
        const results = await searchCities(val)
        setSuggestions(results)
        setOpen(results.length > 0)
      } catch {
        setSuggestions([])
      } finally {
        setLoading(false)
      }
    }, 400)
  }

  const selectCity = (city) => {
    onChange({ target: { name, value: city.name } })
    setSuggestions([])
    setOpen(false)
  }

  return (
    <div ref={wrapperRef} className="city-autocomplete" style={{ position: 'relative' }}>
      <input
        name={name}
        value={value}
        onChange={handleInput}
        onFocus={() => suggestions.length > 0 && setOpen(true)}
        placeholder={placeholder}
        required={required}
        autoComplete="off"
      />
      {open && (
        <ul className="city-suggestions">
          {loading && <li className="city-suggestion-loading">Buscando...</li>}
          {suggestions.map((city, i) => (
            <li key={i} className="city-suggestion-item" onMouseDown={() => selectCity(city)}>
              <span className="city-suggestion-name">{city.name}</span>
              <span className="city-suggestion-detail">{city.displayName}</span>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
