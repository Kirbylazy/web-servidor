import { useEffect, useRef } from 'react'
import { loadGoogleMapsScript } from '../utils/googleMaps'

export default function CityAutocomplete({ value, onChange, placeholder, name, required }) {
  const inputRef = useRef(null)
  const autocompleteRef = useRef(null)
  const apiKey = import.meta.env.VITE_GOOGLE_MAPS_API_KEY

  useEffect(() => {
    if (!apiKey) return

    loadGoogleMapsScript(apiKey).then(() => {
      if (!inputRef.current || autocompleteRef.current) return

      autocompleteRef.current = new window.google.maps.places.Autocomplete(inputRef.current, {
        types: ['(cities)'],
        componentRestrictions: { country: 'es' },
        fields: ['name', 'formatted_address'],
      })

      autocompleteRef.current.addListener('place_changed', () => {
        const place = autocompleteRef.current.getPlace()
        const city = place.name || place.formatted_address || ''
        onChange({ target: { name, value: city } })
      })
    })

    return () => {
      if (autocompleteRef.current) {
        window.google?.maps?.event?.clearInstanceListeners(autocompleteRef.current)
        autocompleteRef.current = null
      }
    }
  }, [apiKey])

  return (
    <input
      ref={inputRef}
      name={name}
      value={value}
      onChange={onChange}
      placeholder={placeholder}
      required={required}
      autoComplete="off"
    />
  )
}
