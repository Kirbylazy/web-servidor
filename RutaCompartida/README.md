# RutaCompartida

Aplicación web para compartir coche entre conductores y pasajeros. Proyecto académico hecho con Python y Flask.

---

## Requisitos previos

- **Python 3.12 o superior** — descárgalo en [python.org](https://www.python.org/downloads/)
- **XAMPP** — descárgalo en [apachefriends.org](https://www.apachefriends.org/). Solo necesitamos el módulo **MySQL**.

---

## Instalación paso a paso (Windows)

### 1. Arranca MySQL y Apache en XAMPP

1. Abre el **Panel de control de XAMPP**.
2. Pulsa **Start** junto a **Apache** y junto a **MySQL**.
3. Abre **phpMyAdmin** pulsando el botón "Admin" junto a MySQL, o ve a `http://localhost/phpmyadmin`.
4. Crea una base de datos nueva llamada `rutacompartida` (cotejamiento: `utf8mb4_unicode_ci`).

### 2. Descarga el proyecto

```powershell
git clone <url-del-repositorio>
cd RutaCompartida
```

### 3. Crea el entorno virtual e instala las dependencias

Un entorno virtual es una carpeta aislada donde se instalan los paquetes de Python para este proyecto, sin afectar al resto del sistema.

```powershell
python -m venv venv
.\venv\Scripts\Activate.ps1
pip install -r requirements.txt
```

> Si PowerShell dice que no puede ejecutar scripts, abre PowerShell como administrador y ejecuta:
> `Set-ExecutionPolicy RemoteSigned`

### 4. Configura las variables de entorno

```powershell
copy .env.example .env
```

Abre `.env` con un editor de texto y ajusta los valores:

```
SECRET_KEY=pon-aqui-cualquier-texto-largo-y-secreto
DATABASE_URL=mysql+pymysql://root:@localhost:3306/rutacompartida
```

> Si tu MySQL de XAMPP tiene contraseña, ponla después de los dos puntos: `root:tu_contrasena@...`

### 5. Crea las tablas en la base de datos

```powershell
flask --app run:app crear-bd
```

### 6. (Opcional) Carga datos de prueba

```powershell
python seed.py
```

Esto crea 3 usuarios y 5 viajes de ejemplo para ver la app con datos reales.

### 7. Arranca la aplicación

```powershell
python run.py
```

Abre el navegador en **http://127.0.0.1:5000**.

---

## Comandos habituales

```powershell
# Activar el entorno virtual (hacerlo cada vez que abras una terminal nueva)
.\venv\Scripts\Activate.ps1

# Arrancar la app en modo desarrollo
python run.py

# Crear las tablas en MySQL
flask --app run:app crear-bd

# Cargar datos de prueba (borra los anteriores)
python seed.py

# Ejecutar los tests automáticos
python -m pytest tests/ -v

# Comprobar la conexión a la base de datos
# (con el servidor corriendo) http://127.0.0.1:5000/salud
```

---

## Estructura del proyecto

```
RutaCompartida/
├── app/
│   ├── __init__.py        # create_app(): monta la aplicación
│   ├── extensions.py      # Base de datos, login y CSRF
│   ├── models.py          # Tablas: usuarios, viajes, reservas, conversaciones, mensajes
│   ├── auth/              # Blueprint de usuarios (registro, login, perfil)
│   │   ├── __init__.py
│   │   ├── forms.py
│   │   └── routes.py
│   ├── templates/         # Páginas HTML (heredan de base.html)
│   └── static/            # CSS, JS e imágenes
├── tests/
│   ├── conftest.py        # Configuración de pytest (SQLite en memoria)
│   ├── test_modelos.py    # Tests del modelo de datos
│   └── test_auth.py       # Tests de registro, login y acceso protegido
├── docs/
│   ├── PLAN.md            # Plan de desarrollo por tareas
│   ├── requisitos.md      # Casos de uso y pantallas
│   ├── mockups/           # Mockups HTML estáticos de cada pantalla
│   └── entregable1.md     # Documentación del Entregable 1
├── config.py              # Lee la configuración del fichero .env
├── run.py                 # Arranca el servidor
├── seed.py                # Carga datos de prueba
├── schema.sql             # SQL equivalente para phpMyAdmin
├── requirements.txt       # Dependencias de Python
├── .env.example           # Plantilla para crear tu .env
└── README.md              # Este fichero
```

---

## Cuentas de prueba (tras ejecutar seed.py)

| Nombre | Email | Contraseña |
|---|---|---|
| Carlos García | carlos@ejemplo.com | 12345678 |
| Laura Martín | laura@ejemplo.com | 12345678 |
| Javier Ruiz | javier@ejemplo.com | 12345678 |
