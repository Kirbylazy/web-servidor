from app import create_app

app = create_app()

if __name__ == "__main__":
    # debug=True hace que el servidor se reinicie solo al guardar cambios
    # y muestra errores detallados en el navegador. Solo para desarrollo.
    app.run(debug=True)
