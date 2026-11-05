import os
import uuid
from urllib.parse import urlparse

from flask import render_template, redirect, url_for, flash, request, current_app
from flask_login import login_user, logout_user, login_required, current_user
from werkzeug.security import generate_password_hash, check_password_hash

from app.auth import auth
from app.auth.forms import RegistroForm, LoginForm, PerfilForm
from app.extensions import db
from app.models import Usuario


@auth.route('/registro', methods=['GET', 'POST'])
def registro():
    # Si ya hay sesión, no tiene sentido registrarse
    if current_user.is_authenticated:
        return redirect(url_for('inicio'))

    form = RegistroForm()
    if form.validate_on_submit():
        usuario = Usuario(
            nombre=form.nombre.data,
            email=form.email.data.lower(),
            password_hash=generate_password_hash(form.password.data),
        )
        db.session.add(usuario)
        db.session.commit()
        login_user(usuario)
        flash('¡Bienvenido a RutaCompartida!', 'success')
        return redirect(url_for('inicio'))

    return render_template('auth/registro.html', form=form)


@auth.route('/login', methods=['GET', 'POST'])
def login():
    if current_user.is_authenticated:
        return redirect(url_for('inicio'))

    form = LoginForm()
    if form.validate_on_submit():
        usuario = Usuario.query.filter_by(email=form.email.data.lower()).first()
        if usuario and check_password_hash(usuario.password_hash, form.password.data):
            login_user(usuario, remember=form.recordarme.data)
            flash('Has iniciado sesión correctamente.', 'success')
            # Redirigir a la página que intentaba visitar, si es del mismo sitio
            next_page = request.args.get('next')
            if next_page and urlparse(next_page).netloc == '':
                return redirect(next_page)
            return redirect(url_for('inicio'))
        flash('Email o contraseña incorrectos.', 'danger')

    return render_template('auth/login.html', form=form)


@auth.route('/logout')
@login_required
def logout():
    logout_user()
    flash('Has cerrado sesión.', 'info')
    return redirect(url_for('inicio'))


@auth.route('/perfil', methods=['GET', 'POST'])
@login_required
def perfil():
    # obj=current_user rellena el formulario con los datos actuales del usuario
    form = PerfilForm(obj=current_user)

    if form.validate_on_submit():
        current_user.nombre = form.nombre.data
        current_user.telefono = form.telefono.data or None
        current_user.coche_modelo = form.coche_modelo.data or None
        if form.consumo_l100km.data:
            current_user.consumo_l100km = form.consumo_l100km.data

        # Gestionar la subida de avatar
        archivo = form.avatar.data
        if archivo and getattr(archivo, 'filename', None):
            # Comprobar tamaño (máximo 2 MB)
            archivo.seek(0, 2)        # ir al final del fichero
            tamano = archivo.tell()   # posición = tamaño en bytes
            archivo.seek(0)           # volver al principio
            if tamano > 2 * 1024 * 1024:
                flash('La imagen no puede pesar más de 2 MB.', 'danger')
                return render_template('auth/perfil.html', form=form)

            # Guardar con nombre único basado en el id del usuario
            ext = archivo.filename.rsplit('.', 1)[-1].lower()
            nombre_archivo = f"{current_user.id}_{uuid.uuid4().hex}.{ext}"
            ruta_avatares = os.path.join(current_app.root_path, 'static', 'img', 'avatares')
            os.makedirs(ruta_avatares, exist_ok=True)
            archivo.save(os.path.join(ruta_avatares, nombre_archivo))
            current_user.avatar = nombre_archivo

        db.session.commit()
        flash('Perfil actualizado correctamente.', 'success')
        return redirect(url_for('auth.perfil'))

    return render_template('auth/perfil.html', form=form)
