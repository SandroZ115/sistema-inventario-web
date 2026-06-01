// src/App.jsx
// Router principal. Si no hay sesion -> login. Si hay sesion -> el sistema
// con sus rutas (inventario, ventas, etc.) dentro del Layout.

import { BrowserRouter, Routes, Route, Navigate } from "react-router-dom";
import { AuthProvider, useAuth } from "./context/AuthContext";
import Login from "./pages/Login";
import Layout from "./components/Layout";
import Inventario from "./pages/Inventario";
import EnConstruccion from "./pages/EnConstruccion";

// rutas del sistema (protegidas: requieren sesion)
function Sistema() {
  const { usuario } = useAuth();

  // sin sesion, manda al login
  if (!usuario) {
    return <Navigate to="/" replace />;
  }

  return (
    <Routes>
      <Route element={<Layout />}>
        <Route path="/inventario" element={<Inventario />} />
        <Route path="/ventas" element={<EnConstruccion nombre="Ventas" />} />
        <Route path="/compras" element={<EnConstruccion nombre="Compras" />} />
        <Route path="/clientes" element={<EnConstruccion nombre="Clientes" />} />
        <Route path="/proveedores" element={<EnConstruccion nombre="Proveedores" />} />
        <Route path="/categorias" element={<EnConstruccion nombre="Categorias" />} />
        <Route path="/atencion" element={<EnConstruccion nombre="Atencion al cliente" />} />
        <Route path="/reportes" element={<EnConstruccion nombre="Reportes" />} />
        <Route path="/movimientos" element={<EnConstruccion nombre="Movimientos" />} />
        {/* cualquier otra ruta del sistema cae en inventario */}
        <Route path="*" element={<Navigate to="/inventario" replace />} />
      </Route>
    </Routes>
  );
}

// decide entre login y sistema
function Contenido() {
  const { usuario } = useAuth();

  return (
    <Routes>
      {/* la raiz: si ya hay sesion, va al inventario; si no, muestra login */}
      <Route
        path="/"
        element={usuario ? <Navigate to="/inventario" replace /> : <Login />}
      />
      {/* todo lo demas lo maneja el sistema (protegido) */}
      <Route path="/*" element={<Sistema />} />
    </Routes>
  );
}

export default function App() {
  return (
    <AuthProvider>
      <BrowserRouter>
        <Contenido />
      </BrowserRouter>
    </AuthProvider>
  );
}
