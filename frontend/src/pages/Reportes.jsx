// src/pages/Reportes.jsx
// Reporte de ventas: filtros por fecha, tabla de detalle, dos graficos
// (ventas por categoria y por producto) y exportacion a PDF y Excel.
//
// Los datos salen de reportes.php (sp_reporte_ventas con CTE en SQL Server).
// La logica de negocio vive en SQL; aqui solo se visualiza y exporta.

import { useState } from "react";
import { useAuth } from "../context/AuthContext";
import {
  BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer, PieChart, Pie, Cell,
} from "recharts";
import "../styles/reportes.css";

const COLORES = ["#b68d40", "#185fa5", "#1d9e75", "#ba7517", "#c0392b", "#7a5ea8", "#2c8c8c"];

export default function Reportes() {
  const { usuario } = useAuth();
  const [desde, setDesde] = useState("");
  const [hasta, setHasta] = useState("");
  const [lineas, setLineas] = useState([]);
  const [cargando, setCargando] = useState(false);
  const [error, setError] = useState("");
  const [generado, setGenerado] = useState(false);

  async function generar() {
    setError("");
    setCargando(true);
    try {
      const params = new URLSearchParams({ id_usuario: usuario.id });
      if (desde) params.append("fecha_desde", desde);
      if (hasta) params.append("fecha_hasta", hasta);

      const resp = await fetch(`http://localhost:8080/api/reportes.php?${params}`);
      const data = await resp.json();
      if (data.status !== "success") throw new Error(data.message);

      setLineas(data.lineas || []);
      setGenerado(true);
    } catch (e) {
      setError(e.message);
    } finally {
      setCargando(false);
    }
  }

  // agrupaciones para los graficos
  const porCategoria = agrupar(lineas, "categoria", "subtotal");
  const porProducto = agrupar(lineas, "producto", "subtotal").slice(0, 7);
  const granTotal = lineas.length > 0 ? Number(lineas[0].gran_total) : 0;

  // ===== Exportar a Excel =====
  async function exportarExcel() {
    const XLSX = await import("xlsx");
    const datos = lineas.map((l) => ({
      Venta: l.id_venta,
      Fecha: l.fecha_venta,
      Cliente: l.cliente,
      Vendedor: l.vendedor,
      Categoria: l.categoria,
      Producto: l.producto,
      Cantidad: l.cantidad,
      "Precio unitario": Number(l.precio_unitario),
      Descuento: Number(l.descuento),
      Subtotal: Number(l.subtotal),
    }));
    const hoja = XLSX.utils.json_to_sheet(datos);
    const libro = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(libro, hoja, "Reporte de ventas");
    XLSX.writeFile(libro, "reporte_ventas.xlsx");
  }

  // ===== Exportar a PDF =====
  async function exportarPDF() {
    const { jsPDF } = await import("jspdf");
    const autoTable = (await import("jspdf-autotable")).default;
    const doc = new jsPDF();

    doc.setFontSize(16);
    doc.text("Reporte de ventas", 14, 18);
    doc.setFontSize(10);
    const rango =
      (desde || "inicio") + " a " + (hasta || "hoy");
    doc.text("Periodo: " + rango, 14, 25);
    doc.text("Total: Q " + granTotal.toFixed(2), 14, 31);

    autoTable(doc, {
      startY: 36,
      head: [["Fecha", "Cliente", "Producto", "Cant.", "Subtotal"]],
      body: lineas.map((l) => [
        l.fecha_venta,
        l.cliente,
        l.producto,
        l.cantidad,
        "Q " + Number(l.subtotal).toFixed(2),
      ]),
      styles: { fontSize: 8 },
      headStyles: { fillColor: [9, 18, 53] },
    });

    doc.save("reporte_ventas.pdf");
  }

  return (
    <div>
      <div className="inv-encabezado">
        <h1 className="inv-titulo">Reportes de ventas</h1>
      </div>

      {/* filtros */}
      <div className="rep-filtros">
        <label>
          Desde
          <input type="date" value={desde} onChange={(e) => setDesde(e.target.value)} />
        </label>
        <label>
          Hasta
          <input type="date" value={hasta} onChange={(e) => setHasta(e.target.value)} />
        </label>
        <button className="crud-boton-nuevo" onClick={generar} disabled={cargando}>
          {cargando ? "Generando..." : "Generar reporte"}
        </button>
        {generado && lineas.length > 0 && (
          <div className="rep-exportar">
            <button onClick={exportarExcel}>Exportar Excel</button>
            <button onClick={exportarPDF}>Exportar PDF</button>
          </div>
        )}
      </div>

      {error && <div className="inv-error">{error}</div>}

      {generado && lineas.length === 0 && !error && (
        <p className="inv-cargando">No hay ventas en ese periodo.</p>
      )}

      {generado && lineas.length > 0 && (
        <>
          {/* resumen */}
          <div className="rep-resumen">
            <div className="metrica">
              <div className="metrica-etq">Total vendido</div>
              <div className="metrica-valor">Q {granTotal.toFixed(2)}</div>
            </div>
            <div className="metrica">
              <div className="metrica-etq">Lineas de venta</div>
              <div className="metrica-valor">{lineas.length}</div>
            </div>
            <div className="metrica">
              <div className="metrica-etq">Categorias</div>
              <div className="metrica-valor">{porCategoria.length}</div>
            </div>
          </div>

          {/* graficos */}
          <div className="rep-graficos">
            <div className="rep-grafico">
              <h3>Ventas por categoria</h3>
              <ResponsiveContainer width="100%" height={260}>
                <PieChart>
                  <Pie
                    data={porCategoria}
                    dataKey="valor"
                    nameKey="nombre"
                    cx="50%"
                    cy="50%"
                    outerRadius={90}
                    label={(e) => e.nombre}
                  >
                    {porCategoria.map((_, i) => (
                      <Cell key={i} fill={COLORES[i % COLORES.length]} />
                    ))}
                  </Pie>
                  <Tooltip formatter={(v) => "Q " + Number(v).toFixed(2)} />
                </PieChart>
              </ResponsiveContainer>
            </div>
            <div className="rep-grafico">
              <h3>Top productos</h3>
              <ResponsiveContainer width="100%" height={260}>
                <BarChart data={porProducto}>
                  <XAxis dataKey="nombre" tick={{ fontSize: 11 }} />
                  <YAxis tick={{ fontSize: 11 }} />
                  <Tooltip formatter={(v) => "Q " + Number(v).toFixed(2)} />
                  <Bar dataKey="valor" fill="#b68d40" radius={[6, 6, 0, 0]} />
                </BarChart>
              </ResponsiveContainer>
            </div>
          </div>

          {/* tabla detalle */}
          <div className="inv-panel">
            <div className="inv-panel-cab">
              <h2>Detalle</h2>
            </div>
            <table className="inv-tabla">
              <thead>
                <tr>
                  <th>Fecha</th>
                  <th>Cliente</th>
                  <th>Producto</th>
                  <th>Cant.</th>
                  <th>Subtotal</th>
                </tr>
              </thead>
              <tbody>
                {lineas.map((l, i) => (
                  <tr key={i}>
                    <td>{l.fecha_venta}</td>
                    <td>{l.cliente}</td>
                    <td>{l.producto}</td>
                    <td>{l.cantidad}</td>
                    <td className="inv-precio">Q {Number(l.subtotal).toFixed(2)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </>
      )}
    </div>
  );
}

// agrupa por una clave sumando un campo numerico
function agrupar(lineas, clave, campo) {
  const mapa = {};
  lineas.forEach((l) => {
    const k = l[clave];
    mapa[k] = (mapa[k] || 0) + Number(l[campo]);
  });
  return Object.entries(mapa)
    .map(([nombre, valor]) => ({ nombre, valor }))
    .sort((a, b) => b.valor - a.valor);
}
