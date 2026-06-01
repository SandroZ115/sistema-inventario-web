// src/services/api.js
// Centraliza las llamadas al backend PHP.
// El backend responde { status, message, ...datos } con status "success" o error.

const BASE_URL = "http://localhost:8080/api";

async function pedir(ruta, opciones = {}) {
  const resp = await fetch(`${BASE_URL}/${ruta}`, {
    headers: { "Content-Type": "application/json" },
    ...opciones,
  });
  const data = await resp.json();
  if (data.status !== "success") {
    throw new Error(data.message || "Error en la peticion");
  }
  return data;
}

// ===== Autenticacion =====
export function login(email, contrasena) {
  return pedir("login.php", {
    method: "POST",
    body: JSON.stringify({ email, contrasena }),
  });
}

// ===== Productos =====
export function listarProductos() {
  return pedir("productos.php");
}

// ===== Clientes =====
export function listarClientes() {
  return pedir("clientes.php");
}
export function crearCliente(datos) {
  return pedir("clientes.php", { method: "POST", body: JSON.stringify(datos) });
}
export function actualizarCliente(id, datos) {
  return pedir(`clientes.php?id=${id}`, { method: "PUT", body: JSON.stringify(datos) });
}
export function eliminarCliente(id) {
  return pedir(`clientes.php?id=${id}`, { method: "DELETE" });
}

// ===== Proveedores =====
export function listarProveedores() {
  return pedir("proveedores.php");
}
export function crearProveedor(datos) {
  return pedir("proveedores.php", { method: "POST", body: JSON.stringify(datos) });
}
export function actualizarProveedor(id, datos) {
  return pedir(`proveedores.php?id=${id}`, { method: "PUT", body: JSON.stringify(datos) });
}
export function eliminarProveedor(id) {
  return pedir(`proveedores.php?id=${id}`, { method: "DELETE" });
}

// ===== Categorias =====
export function listarCategorias() {
  return pedir("categorias.php");
}
export function crearCategoria(datos) {
  return pedir("categorias.php", { method: "POST", body: JSON.stringify(datos) });
}
export function actualizarCategoria(id, datos) {
  return pedir(`categorias.php?id=${id}`, { method: "PUT", body: JSON.stringify(datos) });
}
export function eliminarCategoria(id) {
  return pedir(`categorias.php?id=${id}`, { method: "DELETE" });
}

// ===== Movimientos =====
export function listarMovimientos() {
  return pedir("movimientos.php");
}

// ===== Ventas =====
export function listarVentas() {
  return pedir("ventas.php");
}
export function registrarVenta(datos) {
  return pedir("ventas.php", { method: "POST", body: JSON.stringify(datos) });
}

// ===== Compras =====
export function registrarCompra(datos) {
  return pedir("compras.php", { method: "POST", body: JSON.stringify(datos) });
}

// ===== Atencion al cliente =====
export function listarAtencion(idUsuario) {
  return pedir(`atencion.php?id_usuario=${idUsuario}`);
}
export function crearAtencion(datos) {
  return pedir("atencion.php", { method: "POST", body: JSON.stringify(datos) });
}
export function cerrarAtencion(datos) {
  return pedir("atencion.php", { method: "PATCH", body: JSON.stringify(datos) });
}
