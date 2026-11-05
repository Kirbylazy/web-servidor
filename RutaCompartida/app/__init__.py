import click
from flask import Flask, render_template, jsonify
from config import Config
from app.extensions import db, login_manager, csrf


def create_app(test_config=None):
    """
    Crea y configura la aplicación Flask.
    Se llama así (en lugar de crear la app directamente) para poder
    crear instancias distintas en tests y en producción.

    test_config: diccionario con valores que sobreescriben la configuración
    normal. Se usa en los tests para apuntar a SQLite en memoria.
    """
    app = Flask(__name__)
    app.config.from_object(Config)
    if test_config:
        app.config.update(test_config)

    # Conecta las extensiones con esta instancia de la app
    db.init_app(app)
    login_manager.init_app(app)
    csrf.init_app(app)

    # Importar los modelos registra el user_loader de Flask-Login
    # y hace que SQLAlchemy conozca todas las tablas.
    # No hace falta contexto de app para esto — el decorador funciona en cualquier momento.
    from app import models  # noqa

    # --- Rutas básicas ---

    @app.route("/")
    def inicio():
        return render_template("inicio.html")

    @app.route("/salud")
    def salud():
        """
        Comprueba que la app puede conectarse a la base de datos MySQL.
        Devuelve JSON con el resultado para que sea fácil de leer.
        """
        try:
            db.session.execute(db.text("SELECT 1"))
            return jsonify({"estado": "ok", "mensaje": "Conexión a MySQL correcta"})
        except Exception as e:
            return jsonify({"estado": "error", "mensaje": str(e)}), 500

    # --- Comando de línea de órdenes: flask crear-bd ---

    @app.cli.command("crear-bd")
    def crear_bd():
        """Crea todas las tablas en la base de datos."""
        db.create_all()
        click.echo("Base de datos creada correctamente.")

    # --- Páginas de error personalizadas ---

    @app.errorhandler(404)
    def pagina_no_encontrada(e):
        return render_template("404.html"), 404

    @app.errorhandler(500)
    def error_interno(e):
        return render_template("500.html"), 500

    return app
