<?php
// backend/lib/env.php

class Env {
    public static function cargar($ruta) {
        if (!file_exists($ruta)) {
            return false;
        }

        $lineas = file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lineas as $linea) {
            // Ignorar comentarios
            if (strpos(trim($linea), '#') === 0) continue;

            // Separar por el primer signo de '='
            list($nombre, $valor) = explode('=', $linea, 2);
            $nombre = trim($nombre);
            $valor = trim($valor);

            // Quitar comillas si existen
            $valor = trim($valor, '"\'');

            // Definir variable de entorno en el sistema PHP
            if (!array_key_exists($nombre, $_SERVER) && !array_key_exists($nombre, $_ENV)) {
                putenv("{$nombre}={$valor}");
                $_ENV[$nombre] = $valor;
                $_SERVER[$nombre] = $valor;
            }
        }
        return true;
    }
}