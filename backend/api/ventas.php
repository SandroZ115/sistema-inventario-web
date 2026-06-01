<?php
// backend/api/ventas.php
// GET  -> historial de ventas (lectura)
// POST -> registrar venta usando sp_registrar_venta (script 11) con TVP
//
// Igual que compras.php: el detalle se manda como Table-Valued Parameter
// (dbo.tipo_detalle_venta), que la extension sqlsrv si soporta.
// El TVP del detalle de venta lleva: id_producto, cantidad, descuento.
// El precio lo pone el SP solo desde producto.precio_venta.

require_once __DIR__ . '/../lib/respuesta.php';
require_once __DIR__ . '/../config/db.php';

Respuesta::inicializarAPI();

$conn = DB::conectar();
$metodo = $_SERVER['REQUEST_METHOD'];

switch ($metodo) {

    // ============================================================
    // GET: HISTORIAL DE VENTAS (lectura)
    // ============================================================
    case 'GET':
        $tsql = "SELECT v.id_venta, v.fecha_venta, v.subtotal, v.descuento, v.total,
                        v.metodo_pago, v.estado,
                        (c.nombre + ' ' + c.apellido) AS cliente_nombre,
                        (u.nombre + ' ' + u.apellido) AS usuario_nombre
                 FROM venta v
                 INNER JOIN cliente c ON v.id_cliente = c.id_cliente
                 INNER JOIN usuario u ON v.id_usuario = u.id_usuario
                 ORDER BY v.id_venta DESC";

        $stmt = sqlsrv_query($conn, $tsql);
        if ($stmt === false) {
            Respuesta::json("error", "Error al consultar el historial de ventas.", ["errors" => sqlsrv_errors()], 500);
        }
        $ventas = [];
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $ventas[] = $row;
        }
        sqlsrv_free_stmt($stmt);
        Respuesta::json("success", "Historial de ventas obtenido.", ["ventas" => $ventas], 200);
        break;

    // ============================================================
    // POST: REGISTRAR VENTA  ->  sp_registrar_venta (con TVP)
    // ============================================================
    case 'POST':
        $b = Respuesta::leerBody();

        $id_cliente  = $b['id_cliente'] ?? null;
        $id_usuario  = $b['id_usuario'] ?? null;
        $metodo_pago = $b['metodo_pago'] ?? null;
        $productos   = $b['productos'] ?? null;

        if (!$id_cliente || !$id_usuario || !$metodo_pago || empty($productos) || !is_array($productos)) {
            Respuesta::json("error", "Datos incompletos. Se requiere id_cliente, id_usuario, metodo_pago y el listado de productos.", [], 400);
        }

        // armar el TVP del detalle: [id_producto, cantidad, descuento]
        $tvp_detalle = [];
        foreach ($productos as $p) {
            $tvp_detalle[] = [
                $p['id_producto'] ?? null,
                $p['cantidad'] ?? null,
                $p['descuento'] ?? 0
            ];
        }

        // orden del SP: @id_cliente, @id_usuario, @metodo_pago, @detalle (TVP)
        $tsql = "{call sp_registrar_venta(?, ?, ?, ?)}";
        $params = [
            [$id_cliente, SQLSRV_PARAM_IN],
            [$id_usuario, SQLSRV_PARAM_IN],
            [$metodo_pago, SQLSRV_PARAM_IN],
            [$tvp_detalle, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_TABLE, 'dbo.tipo_detalle_venta']
        ];

        $stmt = sqlsrv_query($conn, $tsql, $params);
        if ($stmt === false) {
            Respuesta::json("error", "Error critico al registrar la venta.", ["errors" => sqlsrv_errors()], 500);
        }

        // saltar resultados intermedios de los triggers hasta el SELECT final
        $resultado = null;
        do {
            $resultado = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            if ($resultado !== null) break;
        } while (sqlsrv_next_result($stmt));

        if ($resultado && isset($resultado['Error'])) {
            Respuesta::json("error_negocio", $resultado['Error'], ["codigo_sql" => $resultado['NumeroError'] ?? null], 422);
        }

        if ($resultado) {
            Respuesta::json("success", $resultado['Mensaje'] ?? "Venta registrada correctamente", [
                "id_venta" => isset($resultado['IdVenta']) ? (int)$resultado['IdVenta'] : null,
                "total"    => isset($resultado['Total']) ? (float)$resultado['Total'] : 0.0
            ], 201);
        } else {
            Respuesta::json("error", "No se recibio respuesta del procedimiento de venta.", [], 500);
        }

        sqlsrv_free_stmt($stmt);
        break;

    default:
        Respuesta::json("error", "Metodo HTTP no permitido.", [], 405);
        break;
}

sqlsrv_close($conn);
?>
