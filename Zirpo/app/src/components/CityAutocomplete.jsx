import { useEffect, useRef } from 'react'

let scriptLoaded = false
let scriptLoading = false
const callbacks = []

function loadGoogleMapsScript(apiKey) {
  if (scriptLoaded) return Promise.resolve()
  if (scriptLoading) return new Promise(resolve => callbacks.push(resolve))

  scriptLoading = true
  return new Promise((resolve) => {
    callbacks.push(resolve)
    const script = document.createElement('script')
    script.src = `https://maps.googleapis.com/maps/api/js?key=${apiKey}&libraries=places&language=es`
    script.async = true
    script.onload = () => {
      scriptLoaded = true
      scriptLoading = false
      callbacks.forEach(cb => cb())
      callbacks.length = 0
    }
    document.head.appendChild(script)
  })
}

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
