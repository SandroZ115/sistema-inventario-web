<?php
// backend/api/movimientos.php

require_once __DIR__ . '/../lib/respuesta.php';
require_once __DIR__ . '/../config/db.php';

// 1. Inicializar cabeceras de la API
Respuesta::inicializarAPI();

$conn = DB::conectar();
$metodo = $_SERVER['REQUEST_METHOD'];

switch ($metodo) {

    // ============================================================
    // 📥 GET: HISTORIAL DE MOVIMIENTOS
    // ============================================================
    case 'GET':
        // Query con INNER JOINs para armar un reporte legible en el JSON
        $tsql = "SELECT m.id_movimiento, m.tipo_movimiento, m.cantidad, 
                        m.stock_antes, m.stock_despues, m.motivo, 
                        m.tabla_origen, m.id_referencia, m.fecha_hora,
                        p.nombre AS producto_nombre, p.codigo AS producto_codigo,
                        (u.nombre + ' ' + u.apellido) AS usuario_nombre
                 FROM movimiento_inventario m
                 INNER JOIN producto p ON m.id_producto = p.id_producto
                 INNER JOIN usuario u ON m.id_usuario = u.id_usuario
                 ORDER BY m.id_movimiento DESC";

        $stmt = sqlsrv_query($conn, $tsql);
        if ($stmt === false) {
            Respuesta::json("error", "Error al consultar el historial de movimientos.", ["errors" => sqlsrv_errors()], 500);
        }

        $movimientos = [];
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $movimientos[] = $row;
        }

        sqlsrv_free_stmt($stmt);
        Respuesta::json("success", "Historial de movimientos obtenido.", ["movimientos" => $movimientos], 200);
        break;

    // ============================================================
    // POST: REGISTRAR ENTRADA/SALIDA/AJUSTE MANUAL
    // ============================================================
    case 'POST':
        $body = Respuesta::leerBody();

        // Validar campos obligatorios
        if (!isset($body['id_producto'], $body['id_usuario'], $body['tipo_movimiento'], $body['cantidad'])) {
            Respuesta::json("error", "Faltan datos obligatorios (id_producto, id_usuario, tipo_movimiento, cantidad).", [], 400);
        }

        $id_producto = intval($body['id_producto']);
        $id_usuario = intval($body['id_usuario']);
        $tipo = trim($body['tipo_movimiento']);
        $cantidad = intval($body['cantidad']);
        $motivo = isset($body['motivo']) ? trim($body['motivo']) : 'Ajuste manual de inventario';

        if ($cantidad <= 0) {
            Respuesta::json("error", "La cantidad del movimiento debe ser mayor a cero.", [], 400);
        }

        // Validar tipos de movimientos permitidos por tu CHECK constraint
        $tiposPermitidos = ['entrada', 'salida', 'ajuste', 'devolucion'];
        if (!in_array($tipo, $tiposPermitidos)) {
            Respuesta::json("error", "Tipo de movimiento inválido.", [], 400);
        }

        // --- INICIAR TRANSACCIÓN EN SQL SERVER ---
        // Al alterar dos tablas (movimientos y productos), si una falla, revertimos todo (ROLLBACK)
        if (sqlsrv_begin_transaction($conn) === false) {
            Respuesta::json("error", "No se pudo iniciar la transacción en el servidor.", ["errors" => sqlsrv_errors()], 500);
        }

        // 1. Consultar el stock actual del producto (Foto del 'stock_antes')
        $sqlStock = "SELECT stock_actual FROM producto WHERE id_producto = ?";
        $stmtStock = sqlsrv_query($conn, $sqlStock, array($id_producto));
        
        if ($stmtStock === false || !($prod = sqlsrv_fetch_array($stmtStock, SQLSRV_FETCH_ASSOC))) {
            sqlsrv_rollback($conn);
            Respuesta::json("error", "El producto especificado no existe.", [], 404);
        }
        sqlsrv_free_stmt($stmtStock);

        $stock_antes = intval($prod['stock_actual']);

        // 2. Calcular el 'stock_despues' según el tipo de movimiento
        // Nota: En tu lógica, un 'ajuste' puede ser tanto de entrada como de salida, 
        // aquí asumiremos que el ajuste manual de este endpoint suma. Si quieres restas, se usa 'salida'.
        if ($tipo === 'entrada' || $tipo === 'devolucion' || $tipo === 'ajuste') {
            $stock_despues = $stock_antes + $cantidad;
        } else { // 'salida'
            $stock_despues = $stock_antes - $cantidad;
            // Regla de negocio: Tu CHECK de la base de datos chk_producto_stock_actual impide stocks negativos
            if ($stock_despues < 0) {
                sqlsrv_rollback($conn);
                Respuesta::json("error", "Operación rechazada: Stock insuficiente. El stock actual es de $stock_antes unidades.", [], 400);
            }
        }

        // 3. Insertar el registro en movimiento_inventario
        $sqlInsertMov = "INSERT INTO movimiento_inventario (id_producto, id_usuario, tipo_movimiento, cantidad, stock_antes, stock_despues, motivo, tabla_origen) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, 'ajuste')";
        $paramsMov = array($id_producto, $id_usuario, $tipo, $cantidad, $stock_antes, $stock_despues, $motivo);
        $stmtMov = sqlsrv_query($conn, $sqlInsertMov, $paramsMov);

        if ($stmtMov === false) {
            sqlsrv_rollback($conn);
            Respuesta::json("error", "Error al registrar el movimiento en el historial.", ["errors" => sqlsrv_errors()], 500);
        }
        sqlsrv_free_stmt($stmtMov);

        // 4. Actualizar el stock_actual en la tabla producto
        $sqlUpdateStock = "UPDATE producto SET stock_actual = ? WHERE id_producto = ?";
        $stmtUpdate = sqlsrv_query($conn, $sqlUpdateStock, array($stock_despues, $id_producto));

        if ($stmtUpdate === false) {
            sqlsrv_rollback($conn);
            Respuesta::json("error", "Error al actualizar las existencias del producto.", ["errors" => sqlsrv_errors()], 500);
        }
        sqlsrv_free_stmt($stmtUpdate);

        // --- TODO OK: GUARDAR CAMBIOS DEFINITIVOS ---
        sqlsrv_commit($conn);

        Respuesta::json("success", "Movimiento de inventario procesado y stock actualizado con éxito.", [
            "detalle" => [
                "stock_anterior" => $stock_antes,
                "nuevo_stock" => $stock_despues
            ]
        ], 201);
        break;

    default:
        Respuesta::json("error", "Método HTTP no permitido.", [], 405);
        break;
}

sqlsrv_close($conn);
?>