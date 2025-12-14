<?php
session_start();
// Repartir asignará un jugador por cada $numJug asignado y le repartira 5 cartas del mazo de cartas a cada jugador

$baraja = [
        "C" => [2,3,4,5,6,7,8,9,10,11,12,13,14],
        "D" => [2,3,4,5,6,7,8,9,10,11,12,13,14],
        "H" => [2,3,4,5,6,7,8,9,10,11,12,13,14],
        "S" => [2,3,4,5,6,7,8,9,10,11,12,13,14],
    ];

function repartir($numJug, $nombre) {

    global $baraja;
    $numJug--;
    $juego[$nombre] = [];
    for ($j = 0; $j < 5; $j++) {
            $palo = array_rand($baraja);
            $valor = $baraja[$palo][array_rand($baraja[$palo])];

            $juego[$nombre][] = [
                "palo" => $palo,
                "valor" => $valor];

            unset($baraja[$palo][array_search($valor, $baraja[$palo])]);
            
        }

    for ($i = 0; $i < $numJug; $i++) {
        $juego[$i] = [];
        for ($j = 0; $j < 5; $j++) {
            $palo = array_rand($baraja);
            $valor = $baraja[$palo][array_rand($baraja[$palo])];

            $juego[$i][] = [
                "palo" => $palo,
                "valor" => $valor];

            unset($baraja[$palo][array_search($valor, $baraja[$palo])]);
            
        }
    }

    return $juego;
}

function descartar($juego, $d1, $d2, $nombre) {

    global $baraja;

    // Ajustamos índices (el usuario introduce 1–5)
    $d1--;
    $d2--;
    if ($d1 >= 0 && $d2 >= 0){
        unset($juego[$nombre][$d1]);
        unset($juego[$nombre][$d2]);

        // Carta 1
        $paloc1 = array_rand($baraja);                // CLAVE: C, D, H, S
        $valorc1 = $baraja[$paloc1][array_rand($baraja[$paloc1])];

        $c1 = [
            "palo" => $paloc1,
            "valor" => $valorc1
        ];

        // Carta 2
        $paloc2 = array_rand($baraja);
        $valorc2 = $baraja[$paloc2][array_rand($baraja[$paloc2])];

        $c2 = [
            "palo" => $paloc2,
            "valor" => $valorc2
        ];

        // Volvemos a insertar cartas
        $juego[$nombre][$d1] = $c1;
        $juego[$nombre][$d2] = $c2;

    }elseif($d1 < 0){
        unset($juego[$nombre][$d2]);


        // Carta 2
        $paloc2 = array_rand($baraja);
        $valorc2 = $baraja[$paloc2][array_rand($baraja[$paloc2])];

        $c2 = [
            "palo" => $paloc2,
            "valor" => $valorc2
        ];

        // Volvemos a insertar cartas
        $juego[$nombre][$d2] = $c2;
    }elseif($d2 < 0){
        unset($juego[$nombre][$d1]);


        // Carta 2
        $paloc1 = array_rand($baraja);
        $valorc1 = $baraja[$paloc1][array_rand($baraja[$paloc1])];

        $c1 = [
            "palo" => $paloc1,
            "valor" => $valorc1
        ];

        // Volvemos a insertar cartas
        $juego[$nombre][$d1] = $c1;
    }
    return $juego;
    
}

function resultado($juego, $nombre) {

    //$resultado = 'El jugador ' . $nombre . ' ha ganado!!';

    foreach ($juego as $jugador=> $mano) {
        
        $resultado[$jugador] = valorarMano($mano);

    }

    $max = max($resultado);
    $ganadores = array_keys($resultado, $max);
    if (count($ganadores) === 1) {
        $mensaje = "El ganador es: el jugador " . $ganadores[0];
    } else {
        $mensaje =  "Empate entre: el jugador " . implode(', ', $ganadores);
    }


    return $mensaje;
}

function valorarMano(array $mano): int {

    // Extraer valores y palos
    $valores = [];
    $palos = [];

    foreach ($mano as $carta) {
        $valores[] = $carta['valor'];
        $palos[] = $carta['palo'];
    }

    sort($valores);

    // Contar repeticiones
    $conteo = array_count_values($valores);
    rsort($conteo);

    $esColor = count(array_unique($palos)) === 1;

    // Escalera (incluye A-2-3-4-5)
    $esEscalera = false;
    if ($valores === [2,3,4,5,14]) {
        $esEscalera = true;
        $cartaAltaEscalera = 5;
    } else {
        $esEscalera = true;
        for ($i = 0; $i < 4; $i++) {
            if ($valores[$i] + 1 !== $valores[$i + 1]) {
                $esEscalera = false;
                break;
            }
        }
        $cartaAltaEscalera = max($valores);
    }

    // ESCALERA REAL
    if ($esEscalera && $esColor && $valores === [10,11,12,13,14]) {
        return 1000;
    }

    // ESCALERA DE COLOR
    if ($esEscalera && $esColor) {
        return 900 + $cartaAltaEscalera;
    }

    // PÓKER
    if ($conteo[0] === 4) {
        $valorPoker = array_search(4, $conteo = array_count_values($valores));
        return 800 + $valorPoker;
    }

    // FULL
    if ($conteo[0] === 3 && $conteo[1] === 2) {
        $valorTrio = array_search(3, array_count_values($valores));
        return 700 + $valorTrio;
    }

    // COLOR
    if ($esColor) {
        return 600 + max($valores);
    }

    // ESCALERA
    if ($esEscalera) {
        return 500 + $cartaAltaEscalera;
    }

    // TRÍO
    if ($conteo[0] === 3) {
        $valorTrio = array_search(3, array_count_values($valores));
        return 400 + $valorTrio;
    }

    // DOBLE PAREJA
    if ($conteo[0] === 2 && $conteo[1] === 2) {
        $parejas = array_keys(array_filter(array_count_values($valores), fn($v) => $v === 2));
        return 300 + max($parejas);
    }

    // PAREJA
    if ($conteo[0] === 2) {
        $valorPareja = array_search(2, array_count_values($valores));
        return 200 + $valorPareja;
    }

    // CARTA ALTA
    return 100 + max($valores);
}


?>