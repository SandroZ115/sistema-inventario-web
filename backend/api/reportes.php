<?php
// backend/api/reportes.php
// Expone el reporte de ventas (sp_reporte_ventas, script 16).
// GET con filtros opcionales por query string:
//   ?id_usuario=1&fecha_desde=2026-01-01&fecha_hasta=2026-12-31
//   &id_categoria=&id_cliente=&id_producto=&id_vendedor=
//
// El SP usa CTE y valida que el rol pueda ver reportes (Administrador,
// Gerente o Reportes). id_usuario es obligatorio.

require_once __DIR__ . '/../lib/respuesta.php';
require_once __DIR__ . '/../config/db.php';

Respuesta::inicializarAPI();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Respuesta::json("error", "Metodo no permitido.", [], 405);
}

$id_usuario = isset($_GET['id_usuario']) ? intval($_GET['id_usuario']) : 0;
if ($id_usuario <= 0) {
    Respuesta::json("error", "Se requiere id_usuario para consultar reportes.", [], 400);
}

// helper: convierte query param vacio en null (para que el SP no filtre)
function opt($clave) {
    return isset($_GET[$clave]) && $_GET[$clave] !== '' ? $_GET[$clave] : null;
}

$conn = DB::conectar();

// orden del SP: id_usuario_consulta, fecha_desde, fecha_hasta,
//               id_categoria, id_cliente, id_producto, id_vendedor
$tsql = "{call sp_reporte_ventas(?, ?, ?, ?, ?, ?, ?)}";
$params = [
    $id_usuario,
    opt('fecha_desde'),
    opt('fecha_hasta'),
    opt('id_categoria'),
    opt('id_cliente'),
    opt('id_producto'),
    opt('id_vendedor'),
];

$stmt = sqlsrv_query($conn, $tsql, $params);
if ($stmt === false) {
    Respuesta::json("error", "Error al generar el reporte.", ["errors" => sqlsrv_errors()], 500);
}

// recoger las filas; si la primera trae Error, es un fallo de negocio (ej. rol)
$filas = [];
do {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        if (isset($row['Error'])) {
            sqlsrv_free_stmt($stmt);
            Respuesta::json("error_negocio", $row['Error'], [], 422);
        }
        // formatear la fecha para que el front la use facil
        if (isset($row['fecha_venta']) && $row['fecha_venta'] instanceof DateTime) {
            $row['fecha_venta'] = $row['fecha_venta']->format('Y-m-d H:i');
        }
        $filas[] = $row;
    }
} while (sqlsrv_next_result($stmt));

sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);

Respuesta::json("success", "Reporte generado.", ["lineas" => $filas], 200);
?>
