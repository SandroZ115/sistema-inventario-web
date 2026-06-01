// src/services/api.js
// Centraliza las llamadas al backend PHP.
// El backend responde con { status, message, ...datos } donde status es
// "success" o "error"/"error_negocio".

const BASE_URL = "http://localhost:8080/api";

// helper generico
async function pedir(ruta, opciones = {}) {
  const resp = await fetch(`${BASE_URL}/${ruta}`, {
    headers: { "Content-Type": "application/json" },
    ...opciones,
  });
  const data = await resp.json();
  // el backend usa "status": "success" cuando todo va bien
  if (data.status !== "success") {
    throw new Error(data.message || "Error en la peticion");
  }
  return data;
}

// ===== Autenticacion =====
// login.php recibe { email, contrasena } y devuelve el usuario en data.usuario
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

// (se iran agregando mas a medida que se construyan las pantallas)
