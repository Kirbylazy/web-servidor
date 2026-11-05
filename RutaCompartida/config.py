import os
from dotenv import load_dotenv

# Carga las variables del fichero .env
load_dotenv()


class Config:
    # Clave secreta para sesiones y protección CSRF
    SECRET_KEY = os.getenv("SECRET_KEY", "clave-por-defecto-no-usar-en-produccion")

    # URL de la base de datos (MySQL en XAMPP por defecto)
    SQLALCHEMY_DATABASE_URI = os.getenv(
        "DATABASE_URL",
        "mysql+pymysql://root:@localhost:3306/rutacompartida"
    )

    # Desactiva un sistema de seguimiento de cambios que consume memoria innecesariamente
    SQLALCHEMY_TRACK_MODIFICATIONS = False

    # Clave de Google Maps (puede estar vacía mientras no se configura)
    GOOGLE_MAPS_KEY = os.getenv("GOOGLE_MAPS_KEY", "")

    # Precio del litro de combustible usado para calcular el precio sugerido
    PRECIO_LITRO = 1.55
