"""
conftest.py — Configuración compartida para todos los tests.

Crea una app de prueba con SQLite en memoria para que los tests
no toquen la base de datos real de MySQL.
"""

import pytest
from app import create_app
from app.extensions import db as _db


@pytest.fixture(scope='function')
def app():
    """
    Crea una instancia de la app configurada para tests.
    scope='function' significa que se reutiliza para todos los tests del mismo
    fichero, en lugar de crear y destruir la app en cada test.
    """
    app = create_app({
        'TESTING': True,
        'SQLALCHEMY_DATABASE_URI': 'sqlite:///:memory:',  # BD temporal en memoria
        'WTF_CSRF_ENABLED': False,  # Desactiva CSRF en tests para simplificar
    })

    with app.app_context():
        _db.create_all()   # Crea las tablas en SQLite
        yield app
        _db.drop_all()     # Limpia al terminar todos los tests del módulo


@pytest.fixture(scope='function')
def db(app):
    """Devuelve el objeto de base de datos dentro del contexto de la app."""
    return _db


@pytest.fixture(scope='function')
def client(app):
    """Cliente HTTP para hacer peticiones a la app en los tests."""
    return app.test_client()
