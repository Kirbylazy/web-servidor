<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * UserFactory — Genera usuarios aleatorios con datos realistas españoles.
 *
 * Crea competidores de escalada con:
 *   - Nombres y apellidos españoles reales (38 nombres M, 38 F, 42 apellidos)
 *   - Género coherente con el nombre (M → nombre masculino, F → femenino)
 *   - Edad entre 8 y 45 años (cubre todas las categorías: U9 a Veterana)
 *   - Provincias andaluzas (las 8 provincias)
 *   - DNIs con formato realista (8 dígitos + 1 letra)
 *
 * Todos los usuarios generados tienen:
 *   - Rol: 'competidor' (por defecto)
 *   - Contraseña: 'password' (cacheada estáticamente para rendimiento)
 *   - Email verificado (email_verified_at = now)
 *
 * Usado por: DatabaseSeeder para crear 300 competidores masivos.
 * Modelo: App\Models\User
 */
class UserFactory extends Factory
{
    /**
     * Contraseña hasheada cacheada estáticamente.
     * Se hashea solo UNA vez y se reutiliza para los 300+ usuarios,
     * lo que mejora enormemente el rendimiento del seeder.
     */
    protected static ?string $password;

    public function definition(): array
    {
        // Provincias de Andalucía — se asigna una aleatoria
        $provincias = ['Sevilla', 'Cádiz', 'Málaga', 'Granada', 'Córdoba', 'Huelva', 'Jaén', 'Almería'];
        $tallas     = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];

        // ── Nombres masculinos españoles ──
        $nombresM = [
            'Alejandro', 'Carlos', 'Miguel', 'David', 'Antonio', 'José', 'Manuel', 'Luis',
            'Javier', 'Pablo', 'Sergio', 'Diego', 'Andrés', 'Rubén', 'Fernando', 'Jorge',
            'Raúl', 'Iván', 'Marcos', 'Daniel', 'Álvaro', 'Adrián', 'Mario', 'Hugo',
            'Víctor', 'Óscar', 'Guillermo', 'Ricardo', 'Enrique', 'Jaime', 'Roberto',
            'Pedro', 'Rodrigo', 'Samuel', 'Tomás', 'Eduardo', 'Francisco', 'Rafael',
        ];

        // ── Nombres femeninos españoles ──
        $nombresF = [
            'María', 'Ana', 'Carmen', 'Laura', 'Sara', 'Patricia', 'Cristina', 'Elena',
            'Marta', 'Isabel', 'Sofía', 'Lucía', 'Alba', 'Paula', 'Nerea', 'Claudia',
            'Raquel', 'Silvia', 'Beatriz', 'Natalia', 'Rosa', 'Pilar', 'Irene', 'Andrea',
            'Miriam', 'Nuria', 'Teresa', 'Virginia', 'Alicia', 'Mónica', 'Verónica',
            'Esther', 'Sandra', 'Vanessa', 'Rebeca', 'Lorena', 'Celia', 'Yolanda',
        ];

        // ── Apellidos españoles comunes ──
        $apellidos = [
            'García', 'Martínez', 'López', 'Sánchez', 'González', 'Pérez', 'Rodríguez',
            'Fernández', 'Torres', 'Ramírez', 'Ruiz', 'Díaz', 'Moreno', 'Jiménez',
            'Álvarez', 'Navarro', 'Romero', 'Domínguez', 'Castro', 'Vega', 'Ramos',
            'Guerrero', 'Medina', 'Herrera', 'Ortega', 'Muñoz', 'Molina', 'Delgado',
            'Suárez', 'Rubio', 'Ortiz', 'Serrano', 'Blanco', 'Vargas', 'Iglesias',
            'Cano', 'Cabrera', 'Fuentes', 'Cruz', 'Reyes', 'Mendoza', 'Aguilar',
        ];

        // Género aleatorio → determina el nombre y la categoría de competición
        $genero    = fake()->randomElement(['M', 'F']);
        // Seleccionar nombre coherente con el género
        $nombre    = $genero === 'M'
            ? fake()->randomElement($nombresM)
            : fake()->randomElement($nombresF);
        // Dos apellidos (estilo español: "Nombre Apellido1 Apellido2")
        $apellido1 = fake()->randomElement($apellidos);
        $apellido2 = fake()->randomElement($apellidos);

        // ── Fecha de nacimiento ──
        // Rango de edad: 8-45 años → cubre U9, U11, ..., Absoluta, Veterana
        // Solo importa el año para la categoría (Inscripcion::calcularCategoria)
        $añoNac = fake()->numberBetween(now()->year - 45, now()->year - 8);
        $fechaNac = $añoNac . '-' . fake()->numberBetween(1, 12) . '-' . fake()->numberBetween(1, 28);

        return [
            'name'             => "$nombre $apellido1 $apellido2",       // Nombre completo español
            'dni'              => fake()->unique()->bothify('########?'), // 8 dígitos + 1 letra aleatoria
            'fecha_nacimiento' => $fechaNac,
            'provincia'        => fake()->randomElement($provincias),     // Provincia andaluza aleatoria
            'talla'            => fake()->randomElement($tallas),
            'email'            => fake()->unique()->safeEmail(),
            'email_verified_at'=> now(),                                  // Ya verificados para testing
            'password'         => static::$password ??= Hash::make('password'), // Cacheado estáticamente
            'rol'              => 'competidor',                           // Todos son competidores por defecto
            'remember_token'   => Str::random(10),
            'genero'           => $genero,                                // M o F para categoría de competición
        ];
    }

    /**
     * Estado: email sin verificar.
     * Usado para testing de flujo de verificación de email.
     */
    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }
}
