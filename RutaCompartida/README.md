# RutaCompartida

Aplicación web para compartir coche entre conductores y pasajeros. Proyecto académico hecho con Python y Flask.

---

## Requisitos previos

- **Python 3.12 o superior** — descárgalo en [python.org](https://www.python.org/downloads/)
- **XAMPP** — descárgalo en [apachefriends.org](https://www.apachefriends.org/). Solo necesitamos el módulo **MySQL**.

---

## Instalación paso a paso (Windows)

### 1. Arranca MySQL en XAMPP

1. Abre el **Panel de control de XAMPP**.
2. Pulsa **Start** junto a **MySQL** (no hace falta arrancar Apache).
3. Abre **phpMyAdmin** (botón "Admin" junto a MySQL).
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

Abre `.env` con un editor y ajusta los valores:

```
SECRET_KEY=pon-aqui-cualquier-texto-largo-y-secreto
DATABASE_URL=mysql+pymysql://root:@localhost:3306/rutacompartida
```

> Si tu MySQL de XAMPP tiene contraseña, ponla después de los dos puntos: `root:tu_contrasena@...`

### 5. Arranca la aplicación

```powershell
python run.py
```

Abre el navegador en **http://localhost:5000**.
Para comprobar la conexión a MySQL: **http://localhost:5000/salud**

---

## Comandos habituales

```powershell
# Activar el entorno virtual (hacerlo cada vez que abras una terminal nueva)
.\venv\Scripts\Activate.ps1

# Arrancar la app en modo desarrollo
python run.py

# Cargar datos de prueba (disponible a partir de la tarea 1.3)
python seed.py

# Ejecutar los tests
pytest
```

---

## Estructura del proyecto

```
RutaCompartida/
├── app/
│   ├── __init__.py        # create_app(): punto de entrada de la aplicación
│   ├── extensions.py      # Base de datos, login y CSRF
│   ├── models.py          # Tablas de la base de datos (tarea 1.3)
│   ├── templates/         # Páginas HTML
│   └── static/            # CSS, JS e imágenes
├── docs/
│   ├── PLAN.md            # Plan de desarrollo por tareas
│   └── requisitos.md      # Casos de uso y pantallas
├── config.py              # Lee la configuración del fichero .env
├── run.py                 # Arranca el servidor
├── requirements.txt       # Dependencias de Python
├── .env.example           # Plantilla para crear tu .env
└── README.md              # Este fichero
```
