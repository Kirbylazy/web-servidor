"""
seed.py — Carga datos de prueba en la base de datos.

Uso:
    python seed.py

Borra todo lo que hubiera antes y crea:
  - 3 usuarios de ejemplo
  - 5 viajes entre ciudades de Andalucía y Madrid
"""

from datetime import datetime, timezone, timedelta
from werkzeug.security import generate_password_hash
from app import create_app
from app.extensions import db
from app.models import Usuario, Viaje

app = create_app()

with app.app_context():
    # Borrar tablas y recrearlas limpias
    db.drop_all()
    db.create_all()

    # --- Usuarios ---
    carlos = Usuario(
        nombre='Carlos García',
        email='carlos@ejemplo.com',
        password_hash=generate_password_hash('12345678'),
        telefono='600111222',
        coche_modelo='Seat Ibiza',
        consumo_l100km=6.5,
    )
    laura = Usuario(
        nombre='Laura Martín',
        email='laura@ejemplo.com',
        password_hash=generate_password_hash('12345678'),
        telefono='600333444',
        coche_modelo='Renault Clio',
        consumo_l100km=5.8,
    )
    javier = Usuario(
        nombre='Javier Ruiz',
        email='javier@ejemplo.com',
        password_hash=generate_password_hash('12345678'),
        coche_modelo='Toyota Yaris',
        consumo_l100km=5.5,
    )
    db.session.add_all([carlos, laura, javier])
    db.session.commit()

    # Fecha base: mañana a las 8:00 UTC
    manana = datetime.now(timezone.utc).replace(hour=8, minute=0, second=0, microsecond=0) + timedelta(days=1)

    # --- Viajes ---
    viajes = [
        Viaje(
            conductor_id=carlos.id,
            origen_txt='Sevilla',
            origen_lat=37.3890924, origen_lng=-5.9844589,
            destino_txt='Madrid',
            destino_lat=40.4167754, destino_lng=-3.7037902,
            fecha_hora=manana,
            plazas_totales=3, plazas_libres=3,
            distancia_km=530, duracion_min=320,
            precio_plaza=13.50,
            comentarios='No fumar. Paro si se necesita.',
        ),
        Viaje(
            conductor_id=laura.id,
            origen_txt='Córdoba',
            origen_lat=37.8881751, origen_lng=-4.7793835,
            destino_txt='Granada',
            destino_lat=37.1773363, destino_lng=-3.5985571,
            fecha_hora=manana + timedelta(days=1, hours=1, minutes=30),
            plazas_totales=2, plazas_libres=2,
            distancia_km=166, duracion_min=105,
            precio_plaza=4.50,
        ),
        Viaje(
            conductor_id=javier.id,
            origen_txt='Málaga',
            origen_lat=36.7212737, origen_lng=-4.4213988,
            destino_txt='Sevilla',
            destino_lat=37.3890924, destino_lng=-5.9844589,
            fecha_hora=manana + timedelta(days=2, hours=-1),
            plazas_totales=3, plazas_libres=3,
            distancia_km=211, duracion_min=130,
            precio_plaza=5.50,
        ),
        Viaje(
            conductor_id=carlos.id,
            origen_txt='Madrid',
            origen_lat=40.4167754, origen_lng=-3.7037902,
            destino_txt='Córdoba',
            destino_lat=37.8881751, destino_lng=-4.7793835,
            fecha_hora=manana + timedelta(days=3),
            plazas_totales=2, plazas_libres=2,
            distancia_km=398, duracion_min=240,
            precio_plaza=10.00,
        ),
        Viaje(
            conductor_id=laura.id,
            origen_txt='Sevilla',
            origen_lat=37.3890924, origen_lng=-5.9844589,
            destino_txt='Granada',
            destino_lat=37.1773363, destino_lng=-3.5985571,
            fecha_hora=manana + timedelta(days=4, hours=2),
            plazas_totales=4, plazas_libres=4,
            distancia_km=256, duracion_min=160,
            precio_plaza=6.50,
        ),
    ]
    db.session.add_all(viajes)
    db.session.commit()

    print("Datos de prueba cargados:")
    print(f"  {Usuario.query.count()} usuarios")
    print(f"  {Viaje.query.count()} viajes")
