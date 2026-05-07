{{--
    Página de bienvenida / landing page.

    Es la primera vista que ve un usuario al acceder a la raíz del sitio (/).
    Definida en routes/web.php: Route::get('/', fn() => view('welcome'))

    Ofrece dos opciones:
      1. Registrar nuevo usuario → redirige a auth/register.blade.php
      2. Acceder a mi perfil → redirige a login (si no autenticado) o a profile/edit (si autenticado)

    NO extiende layouts/app.blade.php porque es una página pública independiente
    con su propio HTML completo y Bootstrap 5 vía CDN.

    Relacionado con:
      - routes/web.php → ruta '/' que renderiza esta vista
      - auth/register.blade.php → formulario de registro (Breeze)
      - auth/login.blade.php → formulario de login (Breeze)
      - profile/edit.blade.php → edición del perfil del usuario autenticado
--}}
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inicio</title>

    {{-- Bootstrap 5 vía CDN — consistente con el resto de la app --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-6">

            <div class="card shadow-sm">
                <div class="card-body p-4 text-center">
                    <h2 class="mb-2">Bienvenido</h2>
                    <p class="text-muted mb-4">Elige una opción para continuar</p>

                    <div class="d-grid gap-3">
                        {{-- Botón de registro → lleva a la ruta 'register' (Breeze) --}}
                        <a class="btn btn-primary btn-lg" href="{{ route('register') }}">
                            Registrar nuevo usuario
                        </a>

                        {{-- Botón de acceso al perfil:
                             - Si está autenticado: va directo a profile.edit
                             - Si no está autenticado: va a login, que luego redirige al dashboard --}}
                        @auth
                            <a class="btn btn-outline-secondary btn-lg" href="{{ route('profile.edit') }}">
                                Acceder a mi perfil
                            </a>
                        @else
                            <a class="btn btn-outline-secondary btn-lg" href="{{ route('login') }}">
                                Acceder a mi perfil
                            </a>
                        @endauth
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>

</body>
</html>
