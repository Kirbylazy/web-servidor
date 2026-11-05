"""
test_modelos.py — Tests básicos del modelo de datos.

Comprueba que se pueden crear un usuario, un viaje y una reserva
en la base de datos SQLite de prueba.
"""

from datetime import datetime, timezone, timedelta
from werkzeug.security import generate_password_hash
from app.models import Usuario, Viaje, Reserva


# Fecha futura para usar en los viajes de prueba
FECHA_FUTURA = datetime.now(timezone.utc) + timedelta(days=10)


def test_crear_usuario(db):
    """Crea un usuario y comprueba que se guarda con un id."""
    u = Usuario(
        nombre='Ana López',
        email='ana@test.com',
        password_hash=generate_password_hash('12345678'),
    )
    db.session.add(u)
    db.session.commit()

    assert u.id is not None
    assert u.consumo_l100km == 6.5  # valor por defecto


def test_crear_viaje(db):
    """Crea un conductor y un viaje, y comprueba que se guarda correctamente."""
    conductor = Usuario(
        nombre='Conductor',
        email='conductor_viaje@test.com',
        password_hash=generate_password_hash('12345678'),
    )
    db.session.add(conductor)
    db.session.commit()

    viaje = Viaje(
        conductor_id=conductor.id,
        origen_txt='Sevilla',
        destino_txt='Madrid',
        fecha_hora=FECHA_FUTURA,
        plazas_totales=3,
        plazas_libres=3,
        precio_plaza=13.50,
    )
    db.session.add(viaje)
    db.session.commit()

    assert viaje.id is not None
    assert viaje.estado == 'activo'
    assert viaje.conductor.nombre == 'Conductor'


def test_crear_reserva(db):
    """Crea conductor, pasajero, viaje y reserva; comprueba que todo encaja."""
    conductor = Usuario(nombre='Conductor', email='conductor_reserva@test.com', password_hash=generate_password_hash('12345678'))
    pasajero  = Usuario(nombre='Pasajero',  email='pasajero_reserva@test.com',  password_hash=generate_password_hash('12345678'))
    db.session.add_all([conductor, pasajero])
    db.session.commit()

    viaje = Viaje(
        conductor_id=conductor.id,
        origen_txt='Sevilla',
        destino_txt='Madrid',
        fecha_hora=FECHA_FUTURA,
        plazas_totales=3,
        plazas_libres=3,
        precio_plaza=13.50,
    )
    db.session.add(viaje)
    db.session.commit()

    reserva = Reserva(viaje_id=viaje.id, pasajero_id=pasajero.id, plazas=1)
    db.session.add(reserva)
    db.session.commit()

    assert reserva.id is not None
    assert reserva.estado == 'confirmada'
    assert reserva.pasajero.nombre == 'Pasajero'
    assert reserva.viaje.origen_txt == 'Sevilla'
