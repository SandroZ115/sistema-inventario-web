<?php
// backend/api/login.php

require_once __DIR__ . '/../lib/respuesta.php';
require_once __DIR__ . '/../config/db.php';

// Inicializar cabeceras CORS y limpiar búfer
Respuesta::inicializarAPI();

// Leer las credenciales del cuerpo de la petición
$body = Respuesta::leerBody();

$emailInput = isset($body['email']) ? trim($body['email']) : '';
$passInput = isset($body['contrasena']) ? trim($body['contrasena']) : '';

// Validar campos vacíos en el backend
if (empty($emailInput) || empty($passInput)) {
    Respuesta::json("error", "Por favor, complete todos los campos.", [], 400);
}

// Conectar a SQL Server usando tu configuración .env
$conn = DB::conectar();

// Invocar tu SP original
$tsql = "{call sp_login(?, ?)}";
$params = array($emailInput, $passInput);

$stmt = sqlsrv_query($conn, $tsql, $params);

if ($stmt === false) {
    Respuesta::json("error", "Error crítico en el servidor de base de datos.", ["errors" => sqlsrv_errors()], 500);
}

// Procesar el conjunto de resultados que devuelve tu SP
if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    
    // 1. Validar si el SP cayó en el BEGIN CATCH o disparó un RAISERROR
    if (isset($row['Error'])) {
        // Tu SP devuelve el texto del error aquí (ej: "Error: email o contrasena incorrectos")
        Respuesta::json("error", $row['Error'], ["error_number" => $row['NumeroError'] ?? null], 401);
    }

    // 2. Si no hay columna Error, el inicio de sesión fue exitoso
    $dataUsuario = array(
        "usuario" => array(
            "id" => $row['id_usuario'],
            "nombre" => $row['nombre'] . " " . $row['apellido'],
            "email" => $row['email'],
            "id_rol" => $row['id_rol'],
            "rol" => $row['Rol'],
            "ultimo_acceso" => $row['ultimo_acceso']
        )
    );

    // Retornamos el 'Mensaje' que configuraste en tu SP ("Inicio de sesion exitoso")
    Respuesta::json("success", $row['Mensaje'], $dataUsuario, 200);

} else {
    Respuesta::json("error", "No se recibió respuesta del procedimiento de autenticación.", [], 500);
}

// Liberar recursos
sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);
?>