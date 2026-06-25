# Zirpo — Documentacion tecnica del proyecto

## Que es Zirpo

Aplicacion web de carpooling (compartir viajes en coche) similar a BlaBlaCar. Los usuarios pueden publicar viajes como conductores o reservar plazas como pasajeros. El modelo de negocio es compartir gastos, no transporte remunerado.

**Estado**: MVP funcional desplegado en produccion.
**URL**: https://darioaguilarrodriguez.com/Zirpo/

---

## Stack tecnico

| Capa | Tecnologia |
|------|-----------|
| Frontend | React 19 + Vite 8 + React Router 7 |
| Backend | Node.js + Express 5 |
| Base de datos | MariaDB (MySQL compatible) |
| Mapas | Leaflet + OpenStreetMap tiles |
| Geocoding | Self-hosted (puerto 2322) |
| Routing | GraphHopper self-hosted (puerto 8080) |
| Realtime | Socket.io (integrado en la API) |
| Package manager | pnpm |
| Deploy | Automatico via GitHub webhook |
| Proceso manager | PM2 |
| Servidor web | Nginx (reverse proxy) |
| Hosting | Raspberry Pi (ARM64) |

---

## Estructura de carpetas

```
Zirpo/
├── api/                    # Backend Express
│   └── src/
│       ├── index.js        # Entry point, rutas, cron jobs
│       ├── db.js           # Pool MySQL2
│       ├── socket.js       # Socket.io + simulacion de viajes
│       ├── migrate.js      # Creacion/actualizacion de tablas
│       ├── controllers/
│       │   ├── authController.js
│       │   ├── tripsController.js
│       │   ├── bookingsController.js
│       │   ├── usersController.js
│       │   ├── vehiclesController.js
│       │   └── messagesController.js
│       ├── routes/
│       │   ├── auth.js
│       │   ├── trips.js
│       │   ├── bookings.js
│       │   ├── users.js
│       │   └── messages.js
│       └── middleware/
│           ├── auth.js     # JWT verification
│           └── upload.js   # Multer (fotos)
├── app/                    # Frontend React
│   └── src/
│       ├── App.jsx         # Router principal (basename=/Zirpo)
│       ├── main.jsx
│       ├── services/
│       │   └── api.js      # Fetch wrapper con JWT
│       ├── utils/
│       │   └── mapService.js  # Leaflet + geocoding + routing
│       ├── context/
│       │   └── AuthContext.jsx
│       ├── components/
│       │   ├── Navbar.jsx
│       │   ├── ProtectedRoute.jsx
│       │   └── CityAutocomplete.jsx
│       └── pages/
│           ├── Home.jsx        # Busqueda + banner viajes activos
│           ├── SearchTrips.jsx # Resultados busqueda con tramos
│           ├── TripDetail.jsx  # Detalle + mapa + reserva
│           ├── PublishTrip.jsx # Publicar viaje (3 pasos)
│           ├── MyTrips.jsx     # Conductor/pasajero tabs
│           ├── LiveTrip.jsx    # Simulacion en ruta (conductor)
│           ├── Messages.jsx    # Lista de conversaciones
│           ├── Chat.jsx        # Chat conductor-pasajero
│           ├── Profile.jsx     # Perfil + vehiculo
│           ├── Login.jsx
│           └── Register.jsx
└── deploy/
    ├── webhook.js          # Webhook GitHub para auto-deploy
    └── ecosystem.config.cjs # Config PM2 del webhook
```

---

## Base de datos (MariaDB)

### Tablas principales

**users**: id, nombre, apellidos, email, password (bcrypt), telefono, foto, valoracion_media, verificado, created_at

**trips**: id, conductor_id, origen, destino, origen_lat/lng, destino_lat/lng, fecha, hora, asientos_totales, asientos_disponibles, precio_asiento, precio_maximo, distancia_km, duracion_min, ruta_polyline, descripcion, estado (activo/completado/cancelado/en_ruta), created_at

**paradas**: id, trip_id, ciudad, lat, lng, orden, distancia_desde_origen_km, precio_desde_origen, pickup_address, created_at

**bookings**: id, trip_id, pasajero_id, tramo_origen, tramo_destino, estado (pendiente/confirmada/cancelada), created_at
- UNIQUE KEY (trip_id, pasajero_id)
- tramo_origen/tramo_destino definen el segmento que reserva el pasajero

**vehicles**: id, user_id (UNIQUE), marca, modelo, matricula, color, plazas, anio, aire_acondicionado, musica, maletero_grande, descripcion

**messages**: id, trip_id, sender_id, passenger_id, contenido, created_at

---

## Sistema de reservas por tramos

### Concepto
Un viaje puede tener paradas intermedias. Los pasajeros reservan **un tramo especifico** (origen-destino parcial), no necesariamente el viaje completo. Las plazas se gestionan **por segmento**: si un pasajero baja en una parada intermedia, su plaza queda libre para el siguiente segmento.

### Flujo
1. Conductor publica viaje con paradas intermedias (origen → parada1 → parada2 → destino)
2. Cada parada tiene: orden, distancia_desde_origen_km, precio_desde_origen
3. Pasajero busca y ve solo su tramo relevante (con precio y plazas del tramo)
4. Al reservar se guarda `tramo_origen` y `tramo_destino` en bookings
5. Disponibilidad se calcula por segmento: segmento = entre dos paradas consecutivas

### Calculo de disponibilidad (por segmento)
```
occupancy[segmento] = num bookings activas que cubren ese segmento
disponible_tramo = asientos_totales - max(occupancy[s] para s en tramo)
```

El campo `trips.asientos_disponibles` se actualiza como el minimo global (peor segmento) para compatibilidad.

### Precio del tramo
```
precio_tramo = precio_desde_origen[destino] - precio_desde_origen[origen]
```

---

## Sistema de tracking en tiempo real

### Simulacion de viaje (conductor)
- Conductor inicia simulacion desde LiveTrip.jsx
- Socket.io emite posicion cada 500ms recorriendo el polyline
- Se calcula ETA a cada parada por distancia restante / velocidad

### Pasajero ve ETA
- Pasajero se une a room `trip:{tripId}` via Socket.io
- Recibe `simulation:position` con array de ETAs por ciudad
- Muestra ETA en formato "Xh Ymin"
- **Privacidad**: pasajeros solo ven ETA, nunca ubicacion exacta del conductor

### Eventos Socket.io
- `trip:join` / `trip:leave` — unirse/salir de room
- `simulation:start` — inicia (cambia trip a en_ruta)
- `simulation:position` — posicion + ETAs
- `simulation:complete` — viaje completado
- `simulation:pause` / `simulation:resume` / `simulation:speed` / `simulation:stop`

---

## Servicios externos self-hosted

| Servicio | Puerto | Uso |
|----------|--------|-----|
| API Zirpo | 3000 | Backend principal |
| Geocoder | 2322 | Autocompletado ciudades + geocoding |
| GraphHopper | 8080 | Calculo de rutas + polylines |
| Webhook | 9000 | Deploy automatico |

Todos accesibles via Nginx reverse proxy en `darioaguilarrodriguez.com`.

---

## Deploy automatico

### Flujo
1. `git push origin main`
2. GitHub envia POST a `/Zirpo/webhook`
3. webhook.js ejecuta:
   - `git fetch origin && git reset --hard origin/main`
   - `cd app && pnpm install --frozen-lockfile && rm -rf dist && pnpm run build`
   - `cd api && pnpm install --frozen-lockfile`
   - `pm2 restart zirpo-api`
   - `pm2 restart zirpo-webhook`

### PM2 procesos
- `zirpo-api` — Backend Express (puerto 3000)
- `zirpo-geocoder` — Servicio geocoding (puerto 2322)
- `zirpo-webhook` — Webhook deploy (puerto 9000)

### Comandos en la Pi (usuario: dario)
- PM2 **sin** sudo: `pm2 restart zirpo-api`
- Git y sistema **con** sudo: `sudo git pull`
- SSH: `dario@192.168.0.21`
- Ruta proyecto: `/mnt/m2/www/default/Zirpo/`

---

## Cron jobs (api/src/index.js)

- **Cada hora**: marca como `completado` viajes cuya fecha+hora ya paso
- **Cada dia 3:00**: borra mensajes con mas de 1 mes

---

## Rutas del frontend

| Ruta | Pagina | Descripcion |
|------|--------|-------------|
| `/` | Home | Busqueda + viajes activos del pasajero |
| `/search` | SearchTrips | Resultados con tramos y plazas por segmento |
| `/trips/:id` | TripDetail | Mapa + paradas + reservar + ETA en ruta |
| `/publish` | PublishTrip | 3 pasos: ruta → paradas → confirmar |
| `/my-trips` | MyTrips | Tabs conductor/pasajero |
| `/live/:id` | LiveTrip | Panel conductor en ruta (simulacion) |
| `/messages` | Messages | Lista chats |
| `/chat/:tripId/:passengerId` | Chat | Chat conductor-pasajero |
| `/profile` | Profile | Datos + vehiculo |
| `/login` | Login | |
| `/register` | Register | |

---

## API endpoints

### Auth
- `POST /api/auth/register` — { nombre, apellidos, email, password }
- `POST /api/auth/login` — { email, password } → { token, user }

### Trips
- `GET /api/trips` — ?origen=&destino=&fecha= (busqueda publica)
- `GET /api/trips/my` — viajes del conductor autenticado (activos + historial)
- `GET /api/trips/:id` — detalle con paradas y bookings
- `POST /api/trips` — publicar viaje
- `DELETE /api/trips/:id` — cancelar viaje

### Bookings
- `GET /api/bookings` — reservas del pasajero autenticado
- `POST /api/bookings` — { trip_id, tramo_origen, tramo_destino }
- `PATCH /api/bookings/:id` — { estado: 'confirmada'|'cancelada' }

### Users
- `GET /api/users/me` — perfil
- `PUT /api/users/me` — actualizar perfil
- `POST /api/users/me/foto` — subir foto (multipart)

### Messages
- `GET /api/messages/:tripId/:passengerId` — historial chat
- `POST /api/messages` — enviar mensaje

---

## Variables de entorno

### API (.env)
```
PORT=3000
DB_HOST=localhost
DB_USER=...
DB_PASSWORD=...
DB_NAME=zirpo
JWT_SECRET=...
```

### App (.env)
```
VITE_API_URL=https://darioaguilarrodriguez.com/Zirpo/api
VITE_SOCKET_URL=https://darioaguilarrodriguez.com
VITE_GEOCODER_URL=https://darioaguilarrodriguez.com/geocoder
VITE_ROUTER_URL=https://darioaguilarrodriguez.com/graphhopper
```

---

## Funcionalidades implementadas

- [x] Registro/login con JWT
- [x] Publicar viaje con paradas intermedias
- [x] Ordenar paradas por distancia real (haversine)
- [x] Calculo de ruta con GraphHopper (polyline + distancias reales)
- [x] Busqueda de viajes por origen/destino/fecha
- [x] Reserva por tramo parcial (tramo_origen/tramo_destino)
- [x] Gestion de plazas por segmento
- [x] Precio proporcional al tramo
- [x] Confirmacion/cancelacion de reservas (conductor/pasajero)
- [x] Mapa con polyline del tramo reservado
- [x] ETA en tiempo real via Socket.io
- [x] Formato horas+minutos para duraciones y ETAs
- [x] Hora estimada de recogida por parada
- [x] Banner de viaje activo en Home con ETA live
- [x] Simulacion de viaje en ruta (conductor)
- [x] Chat conductor-pasajero
- [x] Perfil con vehiculo
- [x] Autocompletado de ciudades (geocoder propio)
- [x] Refresh dinamico (polling 15s + window focus)
- [x] Deploy automatico via webhook GitHub
- [x] Cron: completar viajes pasados + limpiar mensajes

---

## Pendiente / Ideas futuras

- [ ] Sistema de valoraciones post-viaje
- [ ] Notificaciones push (Firebase/Web Push)
- [ ] Verificacion de identidad
- [ ] App movil React Native
- [ ] Asistente IA por voz para conductor (Claude API + Whisper + TTS)
- [ ] Pagos integrados (Stripe Connect, fase 2)
- [ ] Validacion precio maximo DGT por km
- [ ] PWA / service worker para offline
