<?php
// ============================================================================
// ENDPOINT: backend/api/compras.php
// Propósito: Registrar transacciones de compras enviando los datos al TVP de SQL
// Invoca: sp_registrar_compra (Script 10) usando la extensión sqlsrv nativa
// Soporta: Triggers de auditoría JSON activos (Script 13)
// ============================================================================

// 1. CARGA FÍSICA DIRECTA (Clases globales, sin namespaces)
require_once __DIR__ . '/../lib/respuesta.php';
require_once __DIR__ . '/../config/db.php';

// 2. Inicializar políticas de CORS usando tu método nativo de respuesta.php
Respuesta::inicializarAPI();

// Restringir el acceso: este endpoint de escritura operativa solo admite POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Respuesta::json("error", "Método no permitido. Use POST para registrar compras.", [], 405);
}

// 3. Capturar y decodificar el payload JSON enviado por Thunder Client o la SPA
$data = Respuesta::leerBody();

$id_proveedor  = $data['id_proveedor'] ?? null;
$id_usuario    = $data['id_usuario'] ?? null;
$observaciones = $data['observaciones'] ?? 'Compra registrada desde API Web';
$productos     = $data['productos'] ?? null; 

// 4. Sanitización y validación estructural de las variables de entrada
if (!$id_usuario || !$id_proveedor || empty($productos) || !is_array($productos)) {
    Respuesta::json("error", "Datos de petición malformados. Se requiere id_usuario, id_proveedor y el listado de productos estructurado.", [], 400);
}

// 5. Obtener la conexión activa a través de tu archivo db.php
$conn = DB::conectar();

// ============================================================================
// 6. PREPARAR EL TABLE-VALUED PARAMETER (TVP) PARA SQL SERVER
// ============================================================================
// Convertimos el array asociativo de PHP a la estructura indexada de columnas 
// del tipo dbo.tipo_detalle_compra: [0] => id_producto, [1] => cantidad, [2] => precio_unitario
$tvp_detalle = [];
foreach ($productos as $p) {
    $tvp_detalle[] = [
        $p['id_producto'] ?? null,
        $p['cantidad'] ?? null,
        $p['precio_unitario'] ?? null
    ];
}

// 7. Sintaxis Transact-SQL para ejecutar el procedimiento
$tsql = "{call sp_registrar_compra(?, ?, ?, ?)}";

// 💡 CONTROL DE ORDEN ESTRICTO SEGÚN TU SCRIPT DE SQL SERVER:
// 1. @id_proveedor, 2. @id_usuario, 3. @observaciones, 4. @detalle (TVP)
$params = [
    [$id_proveedor, SQLSRV_PARAM_IN],
    [$id_usuario, SQLSRV_PARAM_IN],
    [$observaciones, SQLSRV_PARAM_IN],
    [
        $tvp_detalle, 
        SQLSRV_PARAM_IN, 
        SQLSRV_PHPTYPE_TABLE, 
        'dbo.tipo_detalle_compra'
    ]
];

$stmt = sqlsrv_query($conn, $tsql, $params);

if ($stmt === false) {
    Respuesta::json("error", "Error crítico en el servidor de base de datos al preparar la compra.", ["errors" => sqlsrv_errors()], 500);
}

// ============================================================================
// 8. ESCUDO COLECTOR DE FLUJOS (Para lidiar con los Triggers)
// ============================================================================
// Descarta los conteos internos de filas afectadas generados por el cursor de stock
// y las inserciones automáticas de tus Triggers de Auditoría en la tabla bitácora,
// posicionando de manera segura el cursor en el SELECT final.
$resultado = null;
do {
    $resultado = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    if ($resultado !== null) {
        break; 
    }
} while (sqlsrv_next_result($stmt));

// ============================================================================
// 9. PROCESAR EL RESULTADO DE LA TRANSACCIÓN
// ============================================================================
if ($resultado) {
    
    // Evaluar si saltó un RAISERROR de validación dentro del bloque TRY de SQL Server
    if (isset($resultado['Error'])) {
        Respuesta::json("error_negocio", $resultado['Error'], ["codigo_sql" => $resultado['NumeroError'] ?? null], 422);
    }

    // Transacción completada con éxito. Retornar las columnas exactas de tu SELECT: Mensaje, IdCompra, Total
    $payloadExito = [
        "id_compra" => isset($resultado['IdCompra']) ? (int)$resultado['IdCompra'] : null,
        "total"     => isset($resultado['Total']) ? (float)$resultado['Total'] : 0.0
    ];

    Respuesta::json("success", $resultado['Mensaje'] ?? "Compra registrada correctamente", $payloadExito, 201);
    
} else {
    // Escenario de error si el SP terminó su ejecución pero no se halló el conjunto final de datos
    Respuesta::json("error", "No se recibió respuesta estructurada del procedimiento transaccional.", [], 500);
}

// 10. Liberar recursos y cerrar conexiones de forma limpia
sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);
?>