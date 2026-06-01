// src/main.jsx
// Punto de entrada de la app. Importa el css de temas (global) y monta App.

import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import "./styles/temas.css";
import App from "./App.jsx";

createRoot(document.getElementById("root")).render(
  <StrictMode>
    <App />
  </StrictMode>
);
