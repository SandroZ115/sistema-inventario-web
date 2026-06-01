# Sistema de Gestión de Inventario y Compras

##  Características Principales
* **API RESTful:** Comunicación estandarizada mediante JSON utilizando una arquitectura limpia.
* **Transacciones Atómicas:** Operaciones críticas (como el registro de compras y actualización de stock) encapsuladas para garantizar la integridad de los datos (`BEGIN TRANSACTION`, `COMMIT`, `ROLLBACK`).
* **Seguridad y Control:** Implementación de roles de usuario (`rol_gerente`) y permisos basados en Procedimientos Almacenados.
* **Auditoría:** Registro automático de movimientos en la tabla de bitácora mediante Triggers en la base de datos.

---

##  Requisitos del Entorno (XAMPP + SQL Server)

Para que el proyecto funcione localmente en tu entorno XAMPP, es obligatorio configurar los drivers de conexión para SQL Server.

### 1. Extensiones PHP (.dll) Requeridas
Debes descargar los drivers oficiales de Microsoft SQL Server para PHP (compatibles con tu versión de PHP instalada en XAMPP, ej. PHP 8.1 u 8.2).

Mueve los siguientes archivos a la carpeta de extensiones de tu XAMPP (`C:\xampp\php\ext\`):
php_pdo_sqlsrv_82_ts_x64.dll
php_sqlsrv_82_ts_x64.dll

> **Nota:** Dependiendo de la arquitectura de tu XAMPP, asegúrate de descargar las versiones **Thread Safe (ts)** y para **x64** o **x86** según corresponda.

### 2. Configuración en `php.ini`
Abre tu archivo de configuración `C:\xampp\php\php.ini` y añade o descomenta las siguientes líneas al final de la sección de extensiones:

```ini
extension=php_pdo_sqlsrv_82_ts_x64.dll
extension=php_sqlsrv_82_ts_x64.dll
```