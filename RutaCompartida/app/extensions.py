from flask_sqlalchemy import SQLAlchemy
from flask_login import LoginManager
from flask_wtf.csrf import CSRFProtect

# Estas tres extensiones se crean aquí sin asociarlas todavía a ninguna app.
# Se inicializan de verdad dentro de create_app() con extension.init_app(app).
# Así podemos importarlas desde cualquier parte del proyecto sin importar la app entera.

db = SQLAlchemy()
login_manager = LoginManager()
csrf = CSRFProtect()

# Si alguien intenta acceder a una página que requiere login, Flask-Login
# lo redirige a esta ruta y muestra un mensaje.
login_manager.login_view = "auth.login"
login_manager.login_message = "Debes iniciar sesión para acceder a esta página."
login_manager.login_message_category = "warning"
