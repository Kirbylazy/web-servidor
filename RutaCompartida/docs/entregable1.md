# Entregable 1 — RutaCompartida

**Fecha:** 5 de noviembre de 2026
**Versión:** v0.1
**Tareas completadas:** 1.1 · 1.2 · 1.3 · 1.4 · 1.5

---

## Qué incluye este entregable

### Análisis y diseño (tarea 1.1)
- `docs/requisitos.md` con 10 casos de uso explicados en lenguaje sencillo.
- 8 mockups HTML estáticos en `docs/mockups/` (abre cualquiera en el navegador para verlos).

### Entorno y estructura Flask (tarea 1.2)
- Aplicación Flask con el patrón *application factory* (`create_app()`).
- Configuración por variables de entorno (`.env`).
- Ruta `/salud` que comprueba la conexión a MySQL y devuelve JSON.

### Modelo de datos (tarea 1.3)
- 5 tablas en MySQL: `usuarios`, `viajes`, `reservas`, `conversaciones`, `mensajes`.
- Comando `flask --app run:app crear-bd` que crea todas las tablas.
- `seed.py` con 3 usuarios y 5 viajes de ejemplo.
- `schema.sql` equivalente para importar desde phpMyAdmin.

### Plantilla base (tarea 1.4)
- `base.html` con Bootstrap 5 (tema Bootswatch Flatly).
- Barra de navegación responsive que cambia según si hay sesión activa o no.
- Zona de mensajes flash para éxitos y errores.
- Pie de página fijo al fondo.
- Páginas de error 404 y 500 con el mismo estilo.

### Sistema de usuarios (tarea 1.5)
- Registro con validación de email único y contraseña de mínimo 8 caracteres.
- Login con opción "Recuérdame".
- Logout.
- Edición de perfil: nombre, teléfono, coche, consumo y foto de avatar (jpg/png, máx. 2 MB).

---

## Cómo probarlo

### Instalación rápida

```powershell
# 1. Activar entorno virtual
.\venv\Scripts\Activate.ps1

# 2. Instalar dependencias (si no se ha hecho antes)
pip install -r requirements.txt

# 3. Crear tablas (con MySQL de XAMPP arrancado)
flask --app run:app crear-bd

# 4. Cargar datos de prueba
python seed.py

# 5. Arrancar
python run.py
```

Luego abre **http://127.0.0.1:5000**

### Recorrido de prueba manual

1. **Portada** — `http://127.0.0.1:5000`
   - Se ven 4 viajes de ejemplo con nombre, ruta, fecha y precio.
   - La navbar muestra "Iniciar sesión" y "Registrarse".

2. **Registro** — clic en "Registrarse"
   - Rellena nombre, email y contraseña (mínimo 8 caracteres).
   - Si pones el mismo email dos veces, aparece el error "Este email ya está en uso".
   - Tras registrarte, la navbar muestra tu nombre.

3. **Perfil** — clic en tu nombre → "Mi perfil"
   - Puedes cambiar nombre, teléfono, coche y consumo.
   - Puedes subir una foto (jpg/png, máx. 2 MB).
   - Aparece un mensaje verde de confirmación al guardar.

4. **Logout** — clic en tu nombre → "Cerrar sesión"
   - Vuelves a ver "Iniciar sesión" en la navbar.

5. **Login** — clic en "Iniciar sesión"
   - Entra con las credenciales que creaste.
   - Si la contraseña es incorrecta, aparece el error "Email o contraseña incorrectos".

6. **Ruta de salud** — `http://127.0.0.1:5000/salud`
   - Debe devolver `{"estado": "ok", "mensaje": "Conexión a MySQL correcta"}`.

### Tests automáticos

```powershell
python -m pytest tests/ -v
```

Deben pasar los 8 tests:
- `test_crear_usuario` — crea un usuario en SQLite
- `test_crear_viaje` — crea un viaje vinculado a un conductor
- `test_crear_reserva` — crea una reserva con sus relaciones
- `test_registro_correcto` — el formulario de registro funciona
- `test_email_duplicado` — detecta emails ya registrados
- `test_login_correcto` — login con credenciales válidas
- `test_login_incorrecto` — rechaza credenciales incorrectas
- `test_acceso_protegido_sin_sesion` — redirige al login si no hay sesión

### Cuentas de prueba (tras `python seed.py`)

| Email | Contraseña |
|---|---|
| carlos@ejemplo.com | 12345678 |
| laura@ejemplo.com | 12345678 |
| javier@ejemplo.com | 12345678 |

---

## Pantallas incluidas

| Pantalla | URL |
|---|---|
| Portada con buscador y viajes | `/` |
| Registro | `/auth/registro` |
| Login | `/auth/login` |
| Perfil (editar) | `/auth/perfil` |
| Comprobación de BD | `/salud` |
| Error 404 | Visita cualquier URL que no exista |

---

## Lo que falta (Entregable 2)

- Publicar y buscar viajes con Google Maps.
- Reservar y cancelar plazas.
- Sistema de chat entre conductor y pasajero.
