<?php
// backend/config/db.php

require_once __DIR__ . '/../lib/env.php';

// Cargar el archivo .env desde la raíz del backend
Env::cargar(__DIR__ . '/../.env');

class DB {
    public static function conectar() {
        $serverName = getenv('DB_SERVER') ?: "SANDRO";
        
        $connectionInfo = array(
            "Database" => getenv('DB_DATABASE'),
            "CharacterSet" => "UTF-8"
        );

        // Si en el .env se definieron credenciales SQL, las agrega
        if (getenv('DB_UID') !== false && getenv('DB_UID') !== "") {
            $connectionInfo["UID"] = getenv('DB_UID');
            $connectionInfo["PWD"] = getenv('DB_PWD');
        }

        $conn = sqlsrv_connect($serverName, $connectionInfo);

        if (!$conn) {
            header("Content-Type: application/json; charset=UTF-8");
            echo json_encode(array(
                "status" => "error",
                "message" => "Error crítico de conexión a la base de datos.",
                "errors" => sqlsrv_errors()
            ));
            die();
        }

        return $conn;
    }
}