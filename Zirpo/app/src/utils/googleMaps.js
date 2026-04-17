let scriptLoaded = false
let scriptLoading = false
const callbacks = []

export function loadGoogleMapsScript(apiKey) {
  if (window.google?.maps) return Promise.resolve()
  if (scriptLoaded) return Promise.resolve()
  if (scriptLoading) return new Promise(resolve => callbacks.push(resolve))

  scriptLoading = true
  return new Promise((resolve) => {
    callbacks.push(resolve)
    const script = document.createElement('script')
    script.src = `https://maps.googleapis.com/maps/api/js?key=${apiKey}&libraries=places,geometry&language=es`
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
