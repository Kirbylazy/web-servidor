{{--
    Layout para vistas de invitado (no autenticado).

    Se usa en las vistas de autenticación generadas por Breeze:
      - auth/login.blade.php
      - auth/register.blade.php
      - auth/forgot-password.blade.php
      - auth/reset-password.blade.php
      - auth/confirm-password.blade.php
      - auth/verify-email.blade.php

    Proporciona:
      - Navbar mínima con solo la marca "Escalada"
      - Tarjeta centrada donde se inyecta el formulario ({{ $slot }})
      - Bootstrap 5 vía CDN (mismo estilo que layouts/app.blade.php)

    No incluye Alpine.js ni sección de scripts porque las vistas de auth
    son formularios simples sin interactividad reactiva.

    Relacionado con:
      - layouts/app.blade.php → layout para usuarios autenticados
      - routes/auth.php → define las rutas de autenticación
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Escalada</title>

    <!-- Bootstrap 5 vía CDN — consistente con el layout principal -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

{{-- Navbar mínima: solo muestra la marca, sin enlaces ni usuario --}}
<nav class="navbar navbar-dark bg-dark">
    <div class="container">
        <span class="navbar-brand mb-0 h1">Escalada</span>
    </div>
</nav>

{{-- Contenedor centrado con tarjeta para el formulario de auth --}}
<div class="container py-5">

    <div class="row justify-content-center">
        <div class="col-md-6">

            <div class="card shadow-sm">
                <div class="card-body">

                    {{-- Aquí se inyecta el contenido de cada vista de auth
                         (login form, register form, etc.) mediante componentes Blade --}}
                    {{ $slot }}

                </div>
            </div>

        </div>
    </div>

</div>

</body>
</html>
