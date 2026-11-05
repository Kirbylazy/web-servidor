from flask import Blueprint

# Creamos el blueprint llamado 'auth'.
# Todas sus rutas empezarán por /auth (definido en create_app).
auth = Blueprint('auth', __name__)

# Importar las rutas aquí para que se registren en el blueprint.
# El import va al final para evitar importaciones circulares.
from app.auth import routes  # noqa
