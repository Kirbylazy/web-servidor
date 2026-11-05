from flask import Flask, render_template, jsonify
from config import Config
from app.extensions import db, login_manager, csrf


def create_app():
    """
    Crea y configura la aplicación Flask.
    Se llama así (en lugar de crear la app directamente) para poder
    crear instancias distintas en tests y en producción.
    """
    app = Flask(__name__)
    app.config.from_object(Config)

    # Conecta las extensiones con esta instancia de la app
    db.init_app(app)
    login_manager.init_app(app)
    csrf.init_app(app)

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

    # --- Páginas de error personalizadas ---

    @app.errorhandler(404)
    def pagina_no_encontrada(e):
        return render_template("404.html"), 404

    @app.errorhandler(500)
    def error_interno(e):
        return render_template("500.html"), 500

    return app
