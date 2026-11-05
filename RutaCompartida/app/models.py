from datetime import datetime, timezone
from flask_login import UserMixin
from app.extensions import db, login_manager


# --- Función de carga de usuario para Flask-Login ---
# Flask-Login llama a esta función cada vez que necesita saber
# quién es el usuario de la sesión actual.
@login_manager.user_loader
def load_user(user_id):
    return db.session.get(Usuario, int(user_id))


# --- Modelos ---

class Usuario(db.Model, UserMixin):
    """
    Tabla de usuarios. Hereda UserMixin para que Flask-Login sepa
    que tiene los métodos is_authenticated, is_active, etc.
    """
    __tablename__ = 'usuarios'

    id              = db.Column(db.Integer, primary_key=True)
    nombre          = db.Column(db.String(100), nullable=False)
    email           = db.Column(db.String(150), unique=True, nullable=False)
    password_hash   = db.Column(db.String(255), nullable=False)
    telefono        = db.Column(db.String(20))
    avatar          = db.Column(db.String(255))
    coche_modelo    = db.Column(db.String(100))
    consumo_l100km  = db.Column(db.Numeric(4, 2), default=6.5)
    creado_en       = db.Column(db.DateTime, default=lambda: datetime.now(timezone.utc))

    # Relaciones
    viajes_como_conductor = db.relationship('Viaje', foreign_keys='Viaje.conductor_id', backref='conductor', lazy='dynamic')
    reservas              = db.relationship('Reserva', foreign_keys='Reserva.pasajero_id', backref='pasajero', lazy='dynamic')

    def __repr__(self):
        return f'<Usuario {self.email}>'


class Viaje(db.Model):
    __tablename__ = 'viajes'

    id             = db.Column(db.Integer, primary_key=True)
    conductor_id   = db.Column(db.Integer, db.ForeignKey('usuarios.id'), nullable=False)
    origen_txt     = db.Column(db.String(200), nullable=False)
    origen_lat     = db.Column(db.Numeric(10, 7))
    origen_lng     = db.Column(db.Numeric(10, 7))
    destino_txt    = db.Column(db.String(200), nullable=False)
    destino_lat    = db.Column(db.Numeric(10, 7))
    destino_lng    = db.Column(db.Numeric(10, 7))
    fecha_hora     = db.Column(db.DateTime, nullable=False)
    plazas_totales = db.Column(db.Integer, nullable=False)
    plazas_libres  = db.Column(db.Integer, nullable=False)
    distancia_km   = db.Column(db.Numeric(8, 2))
    duracion_min   = db.Column(db.Integer)
    polyline       = db.Column(db.Text)
    precio_plaza   = db.Column(db.Numeric(6, 2), nullable=False)
    comentarios    = db.Column(db.Text)
    estado         = db.Column(db.String(20), default='activo')  # activo / completo / cancelado
    creado_en      = db.Column(db.DateTime, default=lambda: datetime.now(timezone.utc))

    # Relaciones
    reservas       = db.relationship('Reserva', backref='viaje', lazy='dynamic')
    conversaciones = db.relationship('Conversacion', backref='viaje', lazy='dynamic')

    def __repr__(self):
        return f'<Viaje {self.origen_txt} → {self.destino_txt}>'


class Reserva(db.Model):
    __tablename__ = 'reservas'

    id          = db.Column(db.Integer, primary_key=True)
    viaje_id    = db.Column(db.Integer, db.ForeignKey('viajes.id'), nullable=False)
    pasajero_id = db.Column(db.Integer, db.ForeignKey('usuarios.id'), nullable=False)
    plazas      = db.Column(db.Integer, nullable=False, default=1)
    estado      = db.Column(db.String(20), default='confirmada')  # confirmada / cancelada
    creado_en   = db.Column(db.DateTime, default=lambda: datetime.now(timezone.utc))

    # Un pasajero solo puede tener una reserva por viaje
    __table_args__ = (db.UniqueConstraint('viaje_id', 'pasajero_id'),)

    def __repr__(self):
        return f'<Reserva viaje={self.viaje_id} pasajero={self.pasajero_id}>'


class Conversacion(db.Model):
    __tablename__ = 'conversaciones'

    id           = db.Column(db.Integer, primary_key=True)
    viaje_id     = db.Column(db.Integer, db.ForeignKey('viajes.id'), nullable=False)
    conductor_id = db.Column(db.Integer, db.ForeignKey('usuarios.id'), nullable=False)
    pasajero_id  = db.Column(db.Integer, db.ForeignKey('usuarios.id'), nullable=False)
    creado_en    = db.Column(db.DateTime, default=lambda: datetime.now(timezone.utc))

    # Solo puede existir una conversación por viaje y pasajero
    __table_args__ = (db.UniqueConstraint('viaje_id', 'pasajero_id'),)

    mensajes = db.relationship('Mensaje', backref='conversacion', lazy='dynamic')

    def __repr__(self):
        return f'<Conversacion viaje={self.viaje_id}>'


class Mensaje(db.Model):
    __tablename__ = 'mensajes'

    id               = db.Column(db.Integer, primary_key=True)
    conversacion_id  = db.Column(db.Integer, db.ForeignKey('conversaciones.id'), nullable=False)
    autor_id         = db.Column(db.Integer, db.ForeignKey('usuarios.id'), nullable=False)
    texto            = db.Column(db.String(1000), nullable=False)
    enviado_en       = db.Column(db.DateTime, default=lambda: datetime.now(timezone.utc))
    leido            = db.Column(db.Boolean, default=False)

    def __repr__(self):
        return f'<Mensaje conversacion={self.conversacion_id}>'
