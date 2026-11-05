# Plan de desarrollo — RutaCompartida

Cada tarea es una sesión de trabajo con Claude Code. Para empezar una, escribe por ejemplo:

> Lee CLAUDE.md y docs/PLAN.md. Haz la tarea 1.2.

Al terminar, Claude Code marca la casilla, anota decisiones debajo y propone el commit.
Al cerrar cada fase: etiqueta en Git (`git tag v0.1`, `v0.2`, `v1.0`).

---

## Fase 1 · Base y usuarios — Entregable 1: jueves 5 nov 2026

### [x] 1.1 Análisis y mockups (22–24 oct)
- Crear `docs/requisitos.md` con casos de uso: registrarse, iniciar sesión, editar perfil,
  publicar viaje, buscar viaje, reservar, cancelar, chatear.
- Mockups en texto o HTML estático de: inicio, registro, login, perfil, publicar viaje, búsqueda, detalle, chat.
- **Hecho cuando:** el documento existe y cubre todas las pantallas del alcance.

### [x] 1.2 Entorno y esqueleto (23–26 oct)
- `requirements.txt`: flask, flask-sqlalchemy, flask-login, flask-wtf, pymysql, python-dotenv,
  requests, waitress, pytest.
- `config.py` que lee `.env`; `.env.example`; `.gitignore` (venv, .env, __pycache__, instance/).
- `create_app()` en `app/__init__.py`, `extensions.py`, `run.py`.
- Página de inicio "Hola RutaCompartida" y ruta `/salud` que comprueba la conexión a MySQL.
- `README.md` con instalación paso a paso en Windows (XAMPP + venv).
- **Hecho cuando:** `python run.py` abre la portada y `/salud` responde OK con MySQL de XAMPP arrancado.

### [x] 1.3 Modelo de datos y BD (26–28 oct)
- Los cinco modelos de `CLAUDE.md` en `app/models.py` con relaciones y restricciones únicas.
- Comando `flask crear-bd` que crea las tablas, y `schema.sql` equivalente para phpMyAdmin.
- `seed.py` con 3 usuarios y 5 viajes de ejemplo (Sevilla, Madrid, Córdoba, Málaga, Granada).
- Test que crea un usuario, un viaje y una reserva en SQLite en memoria.
- **Hecho cuando:** las tablas aparecen en phpMyAdmin y `pytest` pasa.

### [x] 1.4 Plantilla base (27–29 oct)
- `base.html` con Bootstrap 5 + tema Bootswatch, barra de navegación (cambia según sesión),
  zona de mensajes flash y pie.
- Portada con buscador simple (origen, destino, fecha) que de momento no busca.
- Páginas 404 y 500 con el mismo estilo.
- **Hecho cuando:** todas las páginas heredan de `base.html` y se ven bien a 375 px de ancho.

### [x] 1.5 Usuarios (29 oct – 3 nov)
- Blueprint `auth`: registro, login (con "recordarme"), logout, perfil (ver y editar).
- Validaciones: email único y válido, contraseña de 8+ caracteres con confirmación, consumo entre 2 y 20.
- Subida de avatar (jpg/png, máx. 2 MB) a `static/img/avatares/` con nombre seguro.
- Rutas protegidas con `@login_required`.
- Tests: registro correcto, email duplicado, login correcto e incorrecto, acceso protegido sin sesión.
- **Hecho cuando:** el recorrido registro → login → editar perfil → logout funciona y los tests pasan.

### [x] 1.6 Cierre E1 (4–5 nov)
- Revisar errores, limpiar código, actualizar README.
- `docs/entregable1.md`: qué incluye, capturas de pantalla, cómo probarlo.
- `git tag v0.1`.

---

## Fase 2 · Viajes, mapa y búsqueda — Entregable 2: jueves 19 nov 2026

### [ ] 2.1 Google Cloud (6 nov) — *lo hace el usuario, Claude Code guía*
- Crear proyecto, activar facturación, activar Maps JavaScript API, Places API (New) y Routes API.
- Crear clave, restringirla a esas APIs y a `http://localhost:5000/*`; cuota diaria baja y alerta de presupuesto.
- Guardar la clave en `.env` como `GOOGLE_MAPS_KEY`.
- **Hecho cuando:** una página de prueba muestra un mapa centrado en Sevilla.

### [ ] 2.2 Asistente de creación de viaje (6–10 nov)
- `/viajes/nuevo` en 4 pasos en una sola página (pestañas o secciones que se muestran por JS):
  1. Origen y destino con `PlaceAutocompleteElement`; marcadores en el mapa.
  2. Ruta dibujada (se conecta en 2.3).
  3. Fecha y hora (no en el pasado), plazas (1–8), comentarios.
  4. Resumen y botón "Publicar".
- JS en `static/js/mapa.js`, sin frameworks.
- Si falta la clave de Google: aviso y campos manuales de km.
- **Hecho cuando:** se pueden elegir origen y destino con autocompletado y se ven en el mapa.

### [ ] 2.3 Ruta, km y precio (9–12 nov)
- `services/rutas.py`: `calcular_ruta(origen, destino)` → `{distancia_km, duracion_min, polyline}`.
- `services/precio.py`: `precio_sugerido(km, consumo, plazas)` con los tests de `CLAUDE.md`.
- Endpoint `POST /api/ruta` (JSON, con CSRF) que devuelve km, duración, polyline y precio.
- El mapa dibuja la ruta y el paso 3 muestra el precio sugerido editable (70 %–120 %).
- Guardar el viaje con todos los campos; validar precio también en servidor.
- Tests: precio (incluido el caso Sevilla–Madrid) y `/api/ruta` con la llamada a Google simulada (mock).
- **Hecho cuando:** publicar Sevilla → Madrid muestra ~530 km y ~13,50 € por plaza con 3 plazas.

### [ ] 2.4 Búsqueda y detalle (11–15 nov)
- `/viajes` con filtros: origen, destino (texto parcial), fecha, plazas mínimas. Solo viajes
  activos y futuros, ordenados por fecha. Paginación de 10.
- Tarjeta de resultado: ruta, fecha, hora, plazas, precio, conductor.
- `/viajes/<id>`: detalle con mapa y la ruta guardada, datos del conductor y botón de reservar.
- El buscador de la portada ya lleva a `/viajes`.
- **Hecho cuando:** buscar "Sevilla" devuelve los viajes de prueba que salen de Sevilla.

### [ ] 2.5 Reservas (13–17 nov)
- `POST /viajes/<id>/reservar` con número de plazas; restar de `plazas_libres` dentro de una transacción.
- No permitir: reservar tu propio viaje, más plazas de las libres, reservar dos veces el mismo viaje.
- Cancelar reserva devuelve las plazas. Estado `completo` cuando no quedan plazas.
- El conductor puede cancelar su viaje (las reservas pasan a canceladas).
- Tests de todas las reglas anteriores.
- **Hecho cuando:** los tests de reservas pasan y las plazas cuadran siempre.

### [ ] 2.6 Cierre E2 (18–19 nov)
- Revisión, README actualizado, `docs/entregable2.md` con capturas.
- `git tag v0.2`.

---

## Fase 3 · Chat, pulido y entrega — Entrega final: lunes 30 nov 2026

### [ ] 3.1 Chat interno (20–24 nov)
- Botón "Contactar con el conductor" en el detalle del viaje: crea o reutiliza la conversación.
- `/chat`: bandeja con conversaciones, último mensaje y número de no leídos.
- `/chat/<id>`: conversación. Solo pueden verla sus dos participantes.
- API JSON: `GET /api/chat/<id>/mensajes?desde=<id_ultimo>` y `POST /api/chat/<id>/mensajes`.
- `static/js/chat.js`: sondeo cada 3 s, envío sin recargar, scroll al último mensaje,
  texto escapado (nada de `innerHTML` con contenido del usuario).
- Marcar como leídos al abrir la conversación. Máximo 1000 caracteres por mensaje.
- Tests: acceso de un tercero denegado, envío y lectura de mensajes.
- **Hecho cuando:** dos navegadores (uno en incógnito) chatean sin recargar.

### [ ] 3.2 Mis viajes y avisos (23–25 nov)
- `/mis-viajes` con pestañas: viajes publicados (con pasajeros), reservas hechas, viajes pasados.
- Contador de mensajes sin leer en la barra de navegación.
- **Hecho cuando:** el panel muestra correctamente los datos del usuario de prueba.

### [ ] 3.3 Pulido y seguridad (24–26 nov)
- Revisar que todas las vistas comprueban propiedad y login.
- CSRF en formularios y APIs JSON; cabeceras de seguridad básicas.
- Mensajes flash coherentes, estados vacíos ("No hay viajes"), formularios con errores claros.
- Revisión de diseño en móvil y escritorio.
- **Hecho cuando:** checklist de seguridad repasado y anotado aquí.

### [ ] 3.4 Pruebas integrales (25–28 nov)
- Recorrido completo con datos limpios: registro → publicar → buscar → reservar → chatear → cancelar.
- Corregir todos los errores encontrados y añadir un test por cada fallo corregido.
- **Hecho cuando:** `pytest` pasa entero y el recorrido no tiene errores.

### [ ] 3.5 Documentación final (26–29 nov)
- `docs/manual_instalacion.md` (Windows + XAMPP + Google Cloud) y `docs/manual_usuario.md` con capturas.
- `docs/memoria.md`: arquitectura, modelo de datos, decisiones, problemas encontrados, mejoras futuras.
- Arranque de demo con `waitress-serve --port=5000 --call app:create_app`.

### [ ] Entrega final (30 nov)
- `schema.sql` y `seed.py` actualizados, `git tag v1.0`, comprobar instalación en limpio.

---

## Mejoras opcionales (solo si sobra tiempo)
- [ ] Valoraciones entre usuarios tras el viaje.
- [ ] Peajes de Routes API sumados al coste.
- [ ] Chat en tiempo real con Flask-SocketIO.

## Decisiones y notas

### 1.6
- 8 tests pasan en 0,70 s con SQLite en memoria.
- README actualizado con tabla de cuentas de prueba y todos los comandos.

### 1.5
- Los fixtures de pytest usan `scope='function'` (cada test tiene su propia BD limpia). El hang anterior ya no ocurre porque `create_app()` no abre contexto de BD al importar modelos.
- El avatar se guarda con nombre `{id}_{uuid}.ext` para evitar colisiones y no exponer el nombre original.
- `PerfilForm(obj=current_user)` rellena el formulario automáticamente con los datos actuales del usuario al entrar con GET.

### 1.4
- Tema Bootswatch Flatly por CDN (verde/blanco, legible y profesional).
- `current_user` lo inyecta Flask-Login automáticamente en todas las plantillas; no hace falta pasarlo desde la vista.
- Los enlaces de la navbar apuntan a `#` por ahora; se actualizarán en las tareas 1.5 y 2.x cuando existan las rutas.
- `base.html` usa flexbox (`min-height: 100vh`) para que el pie quede siempre al fondo.

### 1.3
- `create_app()` acepta `test_config` para que los tests puedan usar SQLite en memoria sin tocar MySQL.
- Los fixtures de pytest usan `scope='module'` para evitar recrear la app entre cada test.
- Los tests usan emails distintos por test para no violar la restricción UNIQUE de la tabla `usuarios`.

### 1.2
- Las rutas `/` y `/salud` viven directamente en `create_app()` para no crear un blueprint solo para dos rutas simples; se moverán cuando haga falta.
- `/salud` devuelve JSON para que sea fácil de leer en el navegador y en los tests.
- Las plantillas de esta tarea son HTML mínimo; se reemplazarán por `base.html` + Bootstrap en la tarea 1.4.

### 1.1
- `docs/requisitos.md` cubre 10 casos de uso (incluye cerrar sesión y cancelar viaje como CU separados).
- Los mockups son HTML estático puro (sin frameworks) en `docs/mockups/`: inicio, registro, login, perfil, publicar_viaje, busqueda, detalle, chat.
- El mockup de publicar viaje muestra el paso 3 activo para ilustrar la lógica del precio sugerido (70 %–120 %).
