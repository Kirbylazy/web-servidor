# Zirpo — Implementación de Mapas, GPS y Tracking en Tiempo Real

## Stack tecnológico

- **Frontend**: React Native + Expo
- **Backend**: Laravel (PHP)
- **Mapas**: Google Maps Platform
- **Tiempo real**: WebSockets (Laravel Reverb)
- **GPS**: API nativa del dispositivo (sin coste)

---

## APIs de Google Maps a utilizar

| API | Para qué se usa | Cuándo llamarla |
|---|---|---|
| **Directions API** | Calcular ruta con paradas intermedias | Solo al publicar el viaje (1 llamada) |
| **Distance Matrix API** | Calcular ETA periódico al pasajero | Cada 30-60 segundos durante el viaje |
| **Maps SDK (iOS/Android)** | Mapa interactivo en la app | Al renderizar pantallas con mapa |
| **Places API** | Autocompletar ciudades al publicar | Al escribir origen/destino/paradas |

**IMPORTANTE**: el GPS del móvil es nativo y gratuito. No usar Google Geolocation API para el tracking del conductor.

---

## Funcionalidad 1: Paradas intermedias al publicar viaje

### Flujo de usuario

1. Usuario introduce origen y destino (con autocompletado via Places API)
2. Usuario puede añadir paradas intermedias opcionales
3. Al confirmar, se hace **una sola llamada** a Directions API con todos los waypoints
4. Se guarda la ruta codificada en base de datos
5. Se calculan precios y horas estimadas por tramo

### Llamada a Directions API

```javascript
// Ejemplo: Sevilla → Córdoba → Ciudad Real → Madrid
const response = await fetch(
  `https://maps.googleapis.com/maps/api/directions/json?` +
  `origin=Sevilla,España` +
  `&destination=Madrid,España` +
  `&waypoints=Córdoba,España|Ciudad Real,España` +
  `&key=${GOOGLE_MAPS_API_KEY}`
);
```

### Estructura de base de datos

```sql
-- Tabla de viajes
CREATE TABLE viajes (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  conductor_id BIGINT NOT NULL,
  origen VARCHAR(255) NOT NULL,
  destino VARCHAR(255) NOT NULL,
  ruta_polyline TEXT,          -- Polyline codificado de Google
  distancia_total_km INT,
  precio_total DECIMAL(8,2),
  fecha_salida DATETIME,
  asientos_totales TINYINT,
  estado ENUM('activo','en_curso','finalizado','cancelado') DEFAULT 'activo',
  created_at TIMESTAMP,
  updated_at TIMESTAMP
);

-- Tabla de paradas
CREATE TABLE paradas (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  viaje_id BIGINT NOT NULL REFERENCES viajes(id),
  ciudad VARCHAR(255) NOT NULL,
  lat DECIMAL(10,8),
  lng DECIMAL(11,8),
  orden TINYINT NOT NULL,           -- 0 = origen, 1,2... = intermedias, último = destino
  hora_estimada_llegada DATETIME,
  distancia_desde_origen_km INT,
  precio_desde_origen DECIMAL(8,2),
  asientos_disponibles TINYINT,     -- Puede variar por tramo
  created_at TIMESTAMP
);

-- Tabla de reservas (pasajero reserva un tramo concreto)
CREATE TABLE reservas (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  viaje_id BIGINT NOT NULL REFERENCES viajes(id),
  pasajero_id BIGINT NOT NULL,
  parada_origen_id BIGINT NOT NULL REFERENCES paradas(id),
  parada_destino_id BIGINT NOT NULL REFERENCES paradas(id),
  precio DECIMAL(8,2),
  estado ENUM('pendiente','confirmada','cancelada') DEFAULT 'pendiente',
  created_at TIMESTAMP
);
```

### Endpoints Laravel necesarios

```
POST   /api/viajes                    → Publicar viaje (llama a Directions API)
GET    /api/viajes?origen=X&destino=Y → Buscar viajes disponibles
GET    /api/viajes/{id}               → Detalle de un viaje con paradas
POST   /api/viajes/{id}/reservas      → Reservar plaza en un tramo
DELETE /api/viajes/{id}               → Cancelar viaje (conductor)
```

---

## Funcionalidad 2: Tracking en tiempo real del conductor

### Arquitectura del sistema

```
[Móvil conductor]
    GPS nativo (gratis)
    ↓ cada 8 segundos
[WebSocket → Laravel Reverb]
    ↓ broadcast al canal del viaje
[Móvil pasajero]
    Recibe posición → actualiza mapa

[Laravel (job periódico)]
    Cada 30-60 seg → Distance Matrix API → calcula ETA real
    ↓ broadcast ETA actualizado
[Móvil pasajero]
    Muestra "El conductor llega en X minutos"
```

### Configuración de Laravel Reverb

Instalar y configurar Reverb en el backend Laravel:

```bash
php artisan install:broadcasting
# Elegir Reverb cuando pregunte
```

En `.env`:
```
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=zirpo
REVERB_APP_KEY=tu_clave
REVERB_APP_SECRET=tu_secreto
REVERB_HOST=localhost
REVERB_PORT=8080
```

### Canal de broadcasting (Laravel)

```php
// routes/channels.php
Broadcast::channel('viaje.{viajeId}', function ($user, $viajeId) {
    // Solo conductor y pasajeros con reserva pueden escuchar
    $viaje = Viaje::find($viajeId);
    return $viaje && (
        $viaje->conductor_id === $user->id ||
        $viaje->reservas()->where('pasajero_id', $user->id)->exists()
    );
});
```

### Evento de posición del conductor (Laravel)

```php
// app/Events/ConductorPosicionActualizada.php
class ConductorPosicionActualizada implements ShouldBroadcast
{
    public function __construct(
        public int $viajeId,
        public float $lat,
        public float $lng,
        public ?int $etaMinutos = null  // null si aún no se recalculó
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel("viaje.{$this->viajeId}");
    }

    public function broadcastAs(): string
    {
        return 'conductor.posicion';
    }
}
```

### Endpoint para recibir posición del conductor

```php
// app/Http/Controllers/TrackingController.php
public function actualizarPosicion(Request $request, int $viajeId)
{
    $request->validate([
        'lat' => 'required|numeric',
        'lng' => 'required|numeric',
    ]);

    $viaje = Viaje::findOrFail($viajeId);

    // Verificar que el usuario es el conductor
    abort_if($viaje->conductor_id !== auth()->id(), 403);

    // Guardar última posición en caché (Redis o DB)
    Cache::put("conductor_pos_{$viajeId}", [
        'lat' => $request->lat,
        'lng' => $request->lng,
        'timestamp' => now(),
    ], 300); // TTL 5 minutos

    // Broadcast posición a los pasajeros (sin ETA, eso se calcula aparte)
    broadcast(new ConductorPosicionActualizada($viajeId, $request->lat, $request->lng));

    return response()->json(['ok' => true]);
}
```

### Job periódico para calcular ETA (cada 45 segundos)

```php
// app/Jobs/RecalcularETA.php
class RecalcularETA implements ShouldQueue
{
    public function handle()
    {
        $viajesActivos = Viaje::where('estado', 'en_curso')->get();

        foreach ($viajesActivos as $viaje) {
            $pos = Cache::get("conductor_pos_{$viaje->id}");
            if (!$pos) continue;

            // Obtener el punto de recogida del siguiente pasajero pendiente
            $proximaParada = $viaje->paradas()
                ->whereHas('reservas', fn($q) => $q->where('estado', 'confirmada'))
                ->orderBy('orden')
                ->first();

            if (!$proximaParada) continue;

            // Llamar a Distance Matrix API
            $eta = $this->calcularETA(
                $pos['lat'], $pos['lng'],
                $proximaParada->lat, $proximaParada->lng
            );

            // Broadcast ETA actualizado
            broadcast(new ConductorPosicionActualizada(
                $viaje->id,
                $pos['lat'],
                $pos['lng'],
                $eta
            ));
        }
    }

    private function calcularETA(float $origenLat, float $origenLng, float $destLat, float $destLng): int
    {
        $url = "https://maps.googleapis.com/maps/api/distancematrix/json?" . http_build_query([
            'origins'      => "{$origenLat},{$origenLng}",
            'destinations' => "{$destLat},{$destLng}",
            'mode'         => 'driving',
            'key'          => config('services.google_maps.key'),
        ]);

        $response = Http::get($url)->json();
        $segundos = $response['rows'][0]['elements'][0]['duration']['value'] ?? 0;

        return (int) ceil($segundos / 60); // Devuelve minutos
    }
}
```

Registrar el job en el scheduler (`app/Console/Kernel.php` o `routes/console.php`):

```php
Schedule::job(new RecalcularETA)->everyMinute();
```

### Frontend Expo — enviar posición (conductor)

```javascript
// hooks/useTrackingConductor.js
import * as Location from 'expo-location';
import { useEffect, useRef } from 'react';
import axios from 'axios';

export function useTrackingConductor(viajeId) {
  const intervalRef = useRef(null);

  useEffect(() => {
    const startTracking = async () => {
      const { status } = await Location.requestForegroundPermissionsAsync();
      if (status !== 'granted') return;

      intervalRef.current = setInterval(async () => {
        const location = await Location.getCurrentPositionAsync({
          accuracy: Location.Accuracy.High,
        });

        await axios.post(`/api/viajes/${viajeId}/posicion`, {
          lat: location.coords.latitude,
          lng: location.coords.longitude,
        });
      }, 8000); // Cada 8 segundos
    };

    startTracking();

    return () => clearInterval(intervalRef.current);
  }, [viajeId]);
}
```

### Frontend Expo — recibir posición (pasajero)

```javascript
// hooks/useTrackingPasajero.js
import { useEffect, useState } from 'react';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Configurar Laravel Echo con Reverb
const echo = new Echo({
  broadcaster: 'reverb',
  key: process.env.EXPO_PUBLIC_REVERB_APP_KEY,
  wsHost: process.env.EXPO_PUBLIC_REVERB_HOST,
  wsPort: 8080,
  forceTLS: false,
});

export function useTrackingPasajero(viajeId) {
  const [conductorPos, setConductorPos] = useState(null);
  const [etaMinutos, setEtaMinutos] = useState(null);

  useEffect(() => {
    const channel = echo.channel(`viaje.${viajeId}`);

    channel.listen('.conductor.posicion', (data) => {
      setConductorPos({ lat: data.lat, lng: data.lng });
      if (data.etaMinutos !== null) {
        setEtaMinutos(data.etaMinutos);
      }
    });

    return () => echo.leaveChannel(`viaje.${viajeId}`);
  }, [viajeId]);

  return { conductorPos, etaMinutos };
}
```

### Pantalla de espera del pasajero

```javascript
// screens/EsperandoConductorScreen.jsx
import MapView, { Marker, Polyline } from 'react-native-maps';
import { useTrackingPasajero } from '../hooks/useTrackingPasajero';

export default function EsperandoConductorScreen({ route }) {
  const { viajeId, puntoRecogida } = route.params;
  const { conductorPos, etaMinutos } = useTrackingPasajero(viajeId);

  return (
    <View style={{ flex: 1 }}>
      {etaMinutos !== null && (
        <Text style={styles.eta}>
          El conductor llega en {etaMinutos} min
        </Text>
      )}
      <MapView style={{ flex: 1 }}>
        {conductorPos && (
          <Marker coordinate={conductorPos} title="Conductor" />
        )}
        <Marker coordinate={puntoRecogida} title="Tu ubicación" />
      </MapView>
    </View>
  );
}
```

---

## Variables de entorno necesarias

### Backend Laravel (`.env`)

```
GOOGLE_MAPS_API_KEY=tu_clave_aqui

REVERB_APP_ID=zirpo
REVERB_APP_KEY=tu_clave
REVERB_APP_SECRET=tu_secreto
REVERB_HOST=0.0.0.0
REVERB_PORT=8080
```

### Frontend Expo (`.env`)

```
EXPO_PUBLIC_GOOGLE_MAPS_API_KEY=tu_clave_aqui
EXPO_PUBLIC_REVERB_APP_KEY=tu_clave
EXPO_PUBLIC_REVERB_HOST=tu_dominio_o_ip
```

---

## Paquetes a instalar

### Backend Laravel

```bash
composer require laravel/reverb
php artisan install:broadcasting
```

### Frontend Expo

```bash
npx expo install expo-location
npx expo install react-native-maps
npm install laravel-echo pusher-js axios
```

### Permisos en app.json (Expo)

```json
{
  "expo": {
    "plugins": [
      [
        "expo-location",
        {
          "locationAlwaysAndWhenInUsePermission": "Zirpo necesita tu ubicación para mostrar tu posición al pasajero durante el viaje."
        }
      ]
    ],
    "android": {
      "config": {
        "googleMaps": {
          "apiKey": "tu_clave_android"
        }
      }
    },
    "ios": {
      "config": {
        "googleMapsApiKey": "tu_clave_ios"
      }
    }
  }
}
```

---

## Resumen de costes estimados (MVP, tráfico bajo)

| Concepto | Llamadas/mes estimadas | Coste |
|---|---|---|
| Directions API (publicar viaje) | ~500 | ~2 $ |
| Distance Matrix API (ETA periódico) | ~5.000 | ~5 $ |
| Maps SDK (cargas de mapa) | ~10.000 | ~7 $ |
| Places API (autocompletado) | ~3.000 | ~3 $ |
| GPS nativo del móvil | ilimitado | 0 $ |
| **Total estimado** | | **~17 $/mes** |

Muy por debajo del crédito gratuito de 200 $/mes de Google Maps Platform.
