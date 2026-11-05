# Requisitos — RutaCompartida

RutaCompartida es una web para que conductores ofrezcan plazas en su coche y pasajeros las reserven.

---

## Quién usa la app

- **Visitante** — alguien que entra sin cuenta.
- **Usuario** — alguien que se ha registrado. Puede ser conductor o pasajero según el momento.

---

## Qué puede hacer cada uno

### 1. Registrarse (visitante)
El visitante rellena un formulario con nombre, email y contraseña.
- La contraseña debe tener al menos 8 caracteres y escribirse dos veces para confirmarla.
- Si el email ya existe, se muestra un error.
- Al registrarse, la sesión se abre sola y se va a la portada.

### 2. Iniciar sesión (visitante)
El visitante pone su email y contraseña.
- Puede marcar "Recuérdame" para no tener que volver a entrar.
- Si los datos son incorrectos, se muestra un error.

### 3. Cerrar sesión (usuario)
El usuario hace clic en "Cerrar sesión" en la barra de navegación. La sesión termina.

### 4. Editar perfil (usuario)
El usuario puede cambiar: nombre, teléfono, modelo de coche y consumo en litros cada 100 km.
- También puede subir una foto de perfil (jpg o png, máximo 2 MB).

### 5. Publicar un viaje (usuario como conductor)
El conductor rellena un formulario en 4 pasos:
1. Elige origen y destino (con autocompletado en el mapa).
2. Ve la ruta dibujada en el mapa con los kilómetros y el tiempo estimado.
3. Pone la fecha y hora (no puede ser en el pasado), el número de plazas (1 a 8) y comentarios.
4. Ve un resumen y pulsa "Publicar".
- El sistema calcula un precio sugerido. El conductor puede cambiarlo entre el 70 % y el 120 % de ese precio.
- Si no hay conexión con Google Maps, puede escribir los kilómetros a mano.

### 6. Buscar un viaje (visitante o usuario)
Desde la portada o desde `/viajes` se puede buscar por origen, destino y fecha.
- Solo aparecen viajes activos y futuros, ordenados por fecha.
- Los resultados se muestran de 10 en 10.
- Si no hay resultados, se muestra un mensaje.

### 7. Reservar una plaza (usuario como pasajero)
Desde el detalle del viaje, el pasajero elige cuántas plazas quiere y pulsa "Reservar".
- No puede reservar su propio viaje.
- No puede reservar dos veces el mismo viaje.
- No puede reservar más plazas de las que quedan libres.
- Si se agotan las plazas, el viaje pasa a estado "completo".

### 8. Cancelar una reserva (pasajero)
El pasajero puede cancelar su reserva desde "Mis viajes".
- Las plazas vuelven a estar disponibles.

### 9. Cancelar un viaje (conductor)
El conductor puede cancelar su viaje desde el detalle o desde "Mis viajes".
- Todas las reservas de ese viaje también quedan canceladas.

### 10. Chatear con el conductor (pasajero y conductor)
Desde el detalle del viaje, el pasajero puede abrir un chat con el conductor.
- El chat se actualiza solo cada 3 segundos sin recargar la página.
- Cada mensaje puede tener hasta 1000 caracteres.
- Solo los dos participantes pueden ver la conversación.
- Al abrir el chat, los mensajes no leídos se marcan como leídos.

---

## Pantallas de la app

| Pantalla | Dirección | Para qué sirve |
|---|---|---|
| Inicio | `/` | Buscador principal y presentación |
| Registro | `/auth/registro` | Crear una cuenta |
| Login | `/auth/login` | Entrar con una cuenta existente |
| Perfil | `/perfil` | Ver y editar los datos personales |
| Publicar viaje | `/viajes/nuevo` | Formulario en 4 pasos para ofrecer un viaje |
| Búsqueda | `/viajes` | Lista de viajes disponibles |
| Detalle del viaje | `/viajes/<id>` | Info del viaje, reservar y contactar |
| Chat | `/chat/<id>` | Conversación entre conductor y pasajero |
| Mis viajes | `/mis-viajes` | Panel con los viajes y reservas del usuario |
