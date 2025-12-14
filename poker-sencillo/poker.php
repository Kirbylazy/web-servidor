<?php

// DESCRIPCIÓN DEL PROYECTO
// Desarrollarás un juego de póker para 2 jugadores. Uno de ellos simularía ser el jugador y otro la cpu. Aplicando los conceptos de arrays, funciones y formularios que hemos aprendido, haz lo siguiente:

// Flujo del juego:
// Se reparten 5 cartas al jugador y 5 a la CPU
// El jugador puede cambiar sus cartas (puedes cambiar 2 al azar, o puedes desarrollar un algoritmo para este punto y el siguiente).
// La CPU cambia automáticamente sus cartas (en este elige la complejidad de la función. Si es más compleja desarrollarás un jugador más inteligente).
// Se comparan las manos y se determina el ganador
// Muestra las manos (imágenes) y un mensaje que indique el resultado.
// Manos de póker (de menor a mayor valor):
// Carta alta - Ninguna combinación
// Pareja - Dos cartas del mismo valor
// Doble pareja - Dos parejas diferentes
// Trío - Tres cartas del mismo valor
// Escalera - Cinco cartas consecutivas
// Color - Cinco cartas del mismo palo
// Full - Un trío + una pareja
// Póker - Cuatro cartas del mismo valor
// Escalera de color - Escalera del mismo palo


require_once "funciones.php";

if ($_SERVER["REQUEST_METHOD"] == "POST"){

    if (isset($_POST['comenzar'])){

        $_SESSION['comenzar'] = $_POST['comenzar'];
        $_SESSION['juego'] = repartir($_POST['js'], $_SESSION['nombre']);

    }

        // Una vez pulsado el botón podemos empezar a operar

        if (isset($_POST['confirmar'])) {

            $d1 = $_POST["d1"];
            $d2 = $_POST["d2"];
            $_SESSION['confirmar'] = $_POST['confirmar'];

            $_SESSION['juego'] = descartar($_SESSION['juego'],$d1,$d2, $_SESSION['nombre']);
        }


    if (isset($_POST['resultado'])){

        $_SESSION['resultado'] = resultado($_SESSION['juego'], $_SESSION['nombre']);
    }

    if (isset($_POST['volver'])){

        $_SESSION['juego'] = null;
        $_SESSION['comenzar'] = null;
        $_SESSION['confirmar'] = null;
        $_SESSION['resultado'] = null;
    }

    if (isset($_POST['logout'])){

        session_unset();      
        session_destroy();    
        header("Location: index.php"); // Recarga la página para volver al login
        exit();
    }
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Poker</title>
</head>
<body>

<?php 

if (!isset($_SESSION['comenzar']) && !isset($_SESSION['confirmar']) && !isset($_SESSION['resultado'])){

?>
    
<h2>Introduce el número de Jugadores</h2>

    <form method="post">
        <label for="js">Numero:</label>
        <input type="number" name="js" id="js">
        <button type="submit" name="comenzar">Comenzar</button>
        <br><br>
        <button type="submit" name="logout">Logout</button>
    </form>

<?php 

}elseif(isset($_SESSION['comenzar']) && !isset($_SESSION['confirmar']) && !isset($_SESSION['resultado'])){

?>

<h2>Mano inicial</h2> 
    <table border="1" cellpadding="5">
        <?php foreach ($_SESSION['juego'] as $jugador => $mano): ?>

            <tr>
                <th>Jugador <?= is_string($jugador) ? $jugador : $jugador + 1 ?></th>
                <th>1</th><th>2</th><th>3</th><th>4</th><th>5</th>
            </tr>

            <tr>
                <th>Cartas</th>
                <?php foreach ($mano as $carta): ?>
                    <th>
                        <img src="Baraja/<?= $carta['valor'] . $carta['palo'] ?>.svg"
                            alt="carta" width="100">
                    </th>
                <?php endforeach; ?>
            </tr>

        <?php endforeach; ?>

    </table>

    <h2>Cartas a Descartar</h2>

        <!-- Creamos un formulario para recoger todos los datos -->

        <form method="post" action="">

            <!-- Pedimos el primer numero -->

            <label for="d1">Primera Carta (0 para no descartar)</label> 
            <input type="number" min=0 max=5 step=1 name="d1" id="d1" required>

            <!-- Pedimos el segundo numero -->
            <br><br>
            <label for="d2">Segunda Carta (0 para no descartar)</label>
            <input type="number" min=0 max=5 step=1 name="d2" id="d2" required>

            <!-- Creamos el botón para confirma que se puede realizar la operación -->

            <button type="submit" name="confirmar">confirmar</button>
            <br><br>
            <button type="submit" name="logout">Logout</button>

        </form>

<?php 

}

if (isset($_SESSION['comenzar']) && isset($_SESSION['confirmar']) && !isset($_SESSION['resultado'])){

?>

<h2>Resultado despues del descarte</h2> 
    <table border="1" cellpadding="5">
        <?php foreach ($_SESSION['juego'] as $jugador => $mano): ?>

            <tr>
                <th>Jugador <?= is_string($jugador) ? $jugador : $jugador + 1 ?></th>
                <th>1</th><th>2</th><th>3</th><th>4</th><th>5</th>
            </tr>

            <tr>
                <th>Cartas</th>
                <?php foreach ($mano as $carta): ?>
                    <th>
                        <img src="Baraja/<?= $carta['valor'] . $carta['palo'] ?>.svg"
                            alt="carta" width="100">
                    </th>
                <?php endforeach; ?>
            </tr>

        <?php endforeach; ?>
    </table>

<form method="post" action="">

            <button type="submit" name="resultado">Resultado</button>
            <button type="submit" name="logout">Logout</button>

        </form>


<?php

}

if (isset($_SESSION['comenzar']) && isset($_SESSION['confirmar']) && isset($_SESSION['resultado'])){

?>

<h2><?= $_SESSION['resultado'] ?></h2>
<br>
<br>

<form method="post" action="">

            <button type="submit" name="volver">Volver a jugar</button>
            <button type="submit" name="logout">Logout</button>
            <p><br></p>

        </form>

<?php } ?>

</body>
</html>