from flask_wtf import FlaskForm
from flask_wtf.file import FileField, FileAllowed
from wtforms import StringField, PasswordField, BooleanField, SubmitField, DecimalField
from wtforms.validators import (
    DataRequired, Email, EqualTo, Length, NumberRange, Optional, ValidationError
)
from app.models import Usuario


class RegistroForm(FlaskForm):
    nombre = StringField('Nombre', validators=[
        DataRequired(message='El nombre es obligatorio.'),
        Length(2, 100, message='El nombre debe tener entre 2 y 100 caracteres.')
    ])
    email = StringField('Email', validators=[
        DataRequired(message='El email es obligatorio.'),
        Email(message='Introduce un email válido.')
    ])
    password = PasswordField('Contraseña', validators=[
        DataRequired(),
        Length(min=8, message='La contraseña debe tener al menos 8 caracteres.')
    ])
    password2 = PasswordField('Confirmar contraseña', validators=[
        DataRequired(),
        EqualTo('password', message='Las contraseñas no coinciden.')
    ])
    submit = SubmitField('Registrarse')

    def validate_email(self, field):
        """Comprueba que el email no esté ya registrado."""
        if Usuario.query.filter_by(email=field.data.lower()).first():
            raise ValidationError('Este email ya está en uso.')


class LoginForm(FlaskForm):
    email = StringField('Email', validators=[
        DataRequired(),
        Email(message='Introduce un email válido.')
    ])
    password = PasswordField('Contraseña', validators=[DataRequired()])
    recordarme = BooleanField('Recuérdame')
    submit = SubmitField('Entrar')


class PerfilForm(FlaskForm):
    nombre = StringField('Nombre', validators=[
        DataRequired(),
        Length(2, 100)
    ])
    telefono = StringField('Teléfono', validators=[
        Optional(),
        Length(max=20)
    ])
    coche_modelo = StringField('Modelo de coche', validators=[
        Optional(),
        Length(max=100)
    ])
    consumo_l100km = DecimalField('Consumo (l/100 km)', validators=[
        Optional(),
        NumberRange(min=2, max=20, message='El consumo debe estar entre 2 y 20 l/100 km.')
    ], places=1)
    avatar = FileField('Foto de perfil', validators=[
        Optional(),
        FileAllowed(['jpg', 'jpeg', 'png'], 'Solo se permiten imágenes jpg y png.')
    ])
    submit = SubmitField('Guardar cambios')
