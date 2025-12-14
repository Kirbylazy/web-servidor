<?php
//Dario Aguilar Rodriguez
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if (isset($_POST['login'])) {
        if (isset($_POST['n']))
            $_SESSION['nombre'] = $_POST['n']; 
            header('location: poker.php');
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

<?php if (!isset($_SESSION['nombre'])): ?>

    <!-- FORMULARIO LOGIN -->
    <h2>Iniciar sesión</h2>
    <form method="post">
        <label for="n">Nombre:</label>
        <input type="text" name="n" id="n" required>
        <button type="submit" name="login">Login</button>
    </form>

<?php endif;?>
</body>
</html>