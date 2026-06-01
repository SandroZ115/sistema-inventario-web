// src/context/AuthContext.jsx
// Maneja el estado de sesion: quien esta logueado.
// Guarda el usuario en el estado y en localStorage para que la sesion
// sobreviva al recargar la pagina.

import { createContext, useContext, useState } from "react";

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  // al iniciar, intenta recuperar el usuario guardado
  const [usuario, setUsuario] = useState(() => {
    const guardado = localStorage.getItem("usuario");
    return guardado ? JSON.parse(guardado) : null;
  });

  // iniciar sesion: guarda el usuario
  function iniciarSesion(datosUsuario) {
    setUsuario(datosUsuario);
    localStorage.setItem("usuario", JSON.stringify(datosUsuario));
  }

  // cerrar sesion: limpia todo
  function cerrarSesion() {
    setUsuario(null);
    localStorage.removeItem("usuario");
  }

  return (
    <AuthContext.Provider value={{ usuario, iniciarSesion, cerrarSesion }}>
      {children}
    </AuthContext.Provider>
  );
}

// hook para usar el contexto facilmente
export function useAuth() {
  return useContext(AuthContext);
}
