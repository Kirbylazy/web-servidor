"""
test_auth.py — Tests del sistema de usuarios.

Prueba: registro correcto, email duplicado, login correcto,
login incorrecto y acceso a ruta protegida sin sesión.
"""

from werkzeug.security import generate_password_hash
from app.models import Usuario


def _registrar(client, nombre, email, password):
    """Ayuda a enviar el formulario de registro."""
    return client.post('/auth/registro', data={
        'nombre': nombre,
        'email': email,
        'password': password,
        'password2': password,
    }, follow_redirects=True)


def _login(client, email, password, follow=True):
    """Ayuda a enviar el formulario de login."""
    return client.post('/auth/login', data={
        'email': email,
        'password': password,
    }, follow_redirects=follow)


# --- Tests ---

def test_registro_correcto(client):
    """Registrarse con datos válidos redirige a la portada con mensaje de bienvenida."""
    resp = _registrar(client, 'Ana López', 'ana@test.com', '12345678')
    assert resp.status_code == 200
    assert 'Bienvenido' in resp.data.decode('utf-8')


def test_email_duplicado(client, db):
    """Intentar registrarse con un email ya existente muestra un error."""
    # Creamos el usuario directamente en la BD
    u = Usuario(nombre='Dup', email='dup@test.com',
                password_hash=generate_password_hash('12345678'))
    db.session.add(u)
    db.session.commit()

    # Intentamos registrarnos con el mismo email
    resp = _registrar(client, 'Dup2', 'dup@test.com', '12345678')
    assert 'ya está en uso' in resp.data.decode('utf-8')


def test_login_correcto(client, db):
    """Login con credenciales válidas redirige a la portada."""
    u = Usuario(nombre='Login', email='login_ok@test.com',
                password_hash=generate_password_hash('12345678'))
    db.session.add(u)
    db.session.commit()

    resp = _login(client, 'login_ok@test.com', '12345678')
    assert resp.status_code == 200
    assert 'iniciado sesión' in resp.data.decode('utf-8')


def test_login_incorrecto(client):
    """Login con contraseña errónea muestra el mensaje de error."""
    resp = _login(client, 'noexiste@test.com', 'wrongpass')
    assert 'incorrectos' in resp.data.decode('utf-8')


def test_acceso_protegido_sin_sesion(client):
    """Visitar /auth/perfil sin sesión redirige al login."""
    resp = client.get('/auth/perfil', follow_redirects=False)
    assert resp.status_code == 302
    assert '/auth/login' in resp.headers['Location']
