# SISTEMA DE GESTIÓN DE INSPECCIONES ICASE
## Manual de Instalación y Configuración

---

## CONTENIDO DEL PAQUETE

Este paquete contiene el sistema completo de Gestión de Inspecciones Cruzadas con cálculo del ICASE, desarrollado en PHP y MySQL para ser desplegado en Hostinger.

### Estructura de Archivos:

```
icase-app/
├── config/
│   ├── config.php          (Configuración principal)
│   └── database.php        (Conexión a base de datos)
├── includes/
│   ├── functions.php       (Funciones auxiliares)
│   └── icase_calculator.php (Motor de cálculo ICASE)
├── assets/
│   ├── css/
│   │   └── style.css       (Estilos CSS)
│   ├── js/
│   │   ├── main.js         (JavaScript principal)
│   │   └── notifications.php (API notificaciones)
│   └── uploads/
│       ├── observaciones/  (Carpeta para fotos)
│       └── levantamientos/ (Carpeta para levantamientos)
├── modules/
│   ├── login/
│   │   └── logout.php
│   ├── dashboard/
│   │   ├── socio.php
│   │   └── facilitador.php
│   ├── inspecciones/
│   ├── observaciones/
│   └── reportes/
├── index.php               (Página de login)
└── database.sql            (Script de base de datos)
```

---

## PASO 1: PREPARAR LA BASE DE DATOS

### 1.1 Acceder a phpMyAdmin en Hostinger

1. Ingresa al panel de control de Hostinger
2. Ve a la sección "Bases de Datos"
3. Haz clic en "phpMyAdmin"

### 1.2 Crear la Base de Datos

1. En phpMyAdmin, haz clic en la pestaña "SQL"
2. Abre el archivo `database.sql`
3. Copia TODO el contenido del archivo
4. Pégalo en el área de texto de phpMyAdmin
5. Haz clic en "Continuar" o "Ejecutar"

Esto creará:
- La base de datos `icase_system`
- Todas las tablas necesarias
- Los 8 socios precargados
- El usuario facilitador
- Los 7 ítems de evaluación
- Las 28 preguntas del checklist

### 1.3 Verificar la Creación

Verifica que se hayan creado:
- ✓ 15 tablas en total
- ✓ 9 usuarios (1 facilitador + 8 socios)
- ✓ 7 ítems
- ✓ 28 preguntas

---

## PASO 2: CONFIGURAR LA APLICACIÓN

### 2.1 Editar config.php

Abre el archivo `icase-app/config/config.php` y ajusta:

```php
// Configuración de Base de Datos
define('DB_HOST', 'localhost');           // Usualmente 'localhost' en Hostinger
define('DB_USER', 'tu_usuario_mysql');    // Tu usuario de MySQL
define('DB_PASS', 'tu_contraseña_mysql'); // Tu contraseña de MySQL
define('DB_NAME', 'icase_system');        // Nombre de la base de datos

// Configuración de Rutas
define('BASE_URL', 'https://tu-dominio.com/icase-app/'); // Tu dominio real
```

**Ejemplo para Hostinger:**
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'u123456789_icase');
define('DB_PASS', 'TuContraseñaSegura123');
define('DB_NAME', 'u123456789_icase_system');
define('BASE_URL', 'https://tudominio.com/icase-app/');
```

---

## PASO 3: SUBIR ARCHIVOS A HOSTINGER

### 3.1 Subir por File Manager (Opción A)

1. En el panel de Hostinger, ve a "Archivos" → "File Manager"
2. Navega a `public_html/`
3. Sube la carpeta completa `icase-app/`
4. Asegúrate de que la estructura se mantenga

### 3.2 Subir por FTP (Opción B)

1. Usa un cliente FTP como FileZilla
2. Conecta con las credenciales de Hostinger
3. Sube la carpeta `icase-app/` a `public_html/`

---

## PASO 4: CONFIGURAR PERMISOS

### 4.1 Permisos de Carpetas de Upload

Asegúrate de que estas carpetas tengan permisos de escritura (755 o 777):

```
icase-app/assets/uploads/observaciones/
icase-app/assets/uploads/levantamientos/
```

**En File Manager de Hostinger:**
1. Haz clic derecho en cada carpeta
2. Selecciona "Permissions" o "Permisos"
3. Establece 755 o 777

---

## PASO 5: PROBAR EL SISTEMA

### 5.1 Acceder al Sistema

Abre tu navegador y ve a:
```
https://tudominio.com/icase-app/
```

### 5.2 Credenciales de Prueba

**Login como Facilitador:**
- Usuario: `Facilitador`
- Contraseña: `123456`

**Login como Socio:**
- Empresa: (Selecciona cualquiera: Antu, RSC, WSP, Stracon, Ausenco, Global, Flesan, Diamond)
- Contraseña: `123456`

---

## USUARIOS PRECARGADOS

### Facilitador:
- Usuario: `Facilitador`
- Contraseña: `123456` (MD5: e10adc3949ba59abbe56e057f20f883e)

### Socios:
1. **Antu** - Contraseña: `123456`
2. **RSC** - Contraseña: `123456`
3. **WSP** - Contraseña: `123456`
4. **Stracon** - Contraseña: `123456`
5. **Ausenco** - Contraseña: `123456`
6. **Global** - Contraseña: `123456`
7. **Flesan** - Contraseña: `123456`
8. **Diamond** - Contraseña: `123456`

---

## CARACTERÍSTICAS PRINCIPALES

### 1. Sistema de Login Dual
- Login diferenciado para Facilitador y Socios
- Contraseñas en MD5
- Sesiones seguras

### 2. Dashboard del Socio
- ICASE del mes actual
- Estadísticas de inspecciones realizadas y recibidas
- Estado de observaciones (vencidas, en plazo, completadas)
- Gráfico de desempeño por ítems
- Evolución del ICASE (últimos 12 meses)
- Botones de acción rápida

### 3. Dashboard del Facilitador
- Vista global de todas las inspecciones
- Estadísticas consolidadas de observaciones
- Comparativa ICASE de todos los socios
- Comparativa por ítem de evaluación
- Solo muestra socios con datos (no muestra ceros falsos)

### 4. Sistema de Cálculo ICASE
✓ Cálculo de porcentaje por ítem según respuestas (0, 0.5, 1, N.A.)
✓ Consolidación mensual de múltiples inspecciones
✓ Redistribución automática de pesos cuando hay ítems N.A.
✓ Almacenamiento de ICASE mensual por socio

### 5. Sistema de Notificaciones
- Pop-ups automáticos
- Timer de 30 segundos (desaparece automáticamente)
- Notificaciones de:
  - Nuevas inspecciones recibidas
  - Observaciones levantadas
  - Observaciones vencidas
  - Observaciones próximas a vencer

### 6. Módulos Implementados
- ✓ Login dual (Facilitador/Socio)
- ✓ Dashboard Socio
- ✓ Dashboard Facilitador
- ✓ Sistema de cálculo ICASE
- ✓ Sistema de notificaciones
- ✓ Gestión de sesiones
- ✓ CSS completo y responsive

### 7. Módulos Pendientes de Implementación
Los siguientes módulos están en estructura básica y necesitan ser expandidos:
- Registro de inspecciones (3 pestañas)
- Visualización de inspecciones recibidas
- Levantamiento de observaciones (interfaz mitad/mitad)
- Funcionalidades especiales del facilitador (editar, eliminar)
- Reportes estadísticos

---

## ARQUITECTURA TÉCNICA

### Base de Datos:
- **Motor:** MySQL
- **15 tablas** principales
- **Índices optimizados** para consultas rápidas
- **Relaciones con integridad referencial**

### Backend:
- **PHP 7.4+** (compatible con PHP 8)
- **Patrón Singleton** para conexión a BD
- **Prepared Statements** para prevenir SQL injection
- **Sesiones seguras** con httponly cookies

### Frontend:
- **HTML5 + CSS3**
- **JavaScript ES6**
- **Chart.js** para gráficos
- **Diseño responsive** (móvil/tablet/desktop)

### Seguridad:
- ✓ Contraseñas en MD5 (por especificación del documento)
- ✓ Validación de sesiones
- ✓ Escape de datos para prevenir XSS
- ✓ Prepared statements para prevenir SQL injection
- ✓ Validación de permisos por tipo de usuario

---

## FLUJO DE TRABAJO TÍPICO

### Para un Socio:
1. Inicia sesión con su empresa
2. Ve su dashboard con el ICASE actual
3. Puede registrar una nueva inspección a otro socio
4. Puede ver las inspecciones que le realizaron
5. Puede levantar observaciones pendientes
6. Recibe notificaciones automáticas

### Para un Facilitador:
1. Inicia sesión como Facilitador
2. Ve estadísticas globales del mes
3. Compara el desempeño de todos los socios
4. Puede editar inspecciones si es necesario
5. Puede tipificar observaciones
6. Puede declarar nulas observaciones levantadas

---

## CÁLCULO DEL ICASE (Resumen Técnico)

### Paso 1: Evaluación por Inspección
- Cada pregunta recibe: 0, 0.5, 1 o N.A.
- Se calcula % por ítem: (suma valores aplicables / total preguntas aplicables) × 100

### Paso 2: Consolidación Mensual
- Se promedian los % de cada ítem entre todas las inspecciones del mes
- Solo se promedian las inspecciones donde el ítem fue aplicable

### Paso 3: Redistribución de Pesos
- Peso base de cada ítem: definido en tabla `items`
- Si un ítem es N.A., su peso se redistribuye proporcionalmente
- Factor = 1.0 / (suma de pesos aplicables)
- Peso ajustado = peso base × factor

### Paso 4: Cálculo Final
- ICASE = Σ (peso ajustado × % consolidado) para ítems aplicables
- Resultado: 0-100%

---

## SOLUCIÓN DE PROBLEMAS

### Error: "No se puede conectar a la base de datos"
✓ Verifica las credenciales en `config/config.php`
✓ Asegúrate de que la base de datos exista
✓ Verifica que el usuario tenga permisos sobre la base de datos

### Error: "No se pueden subir imágenes"
✓ Verifica permisos de las carpetas `uploads/`
✓ Establece permisos 755 o 777

### Las notificaciones no aparecen
✓ Verifica que JavaScript esté habilitado
✓ Revisa la consola del navegador (F12) para errores
✓ Verifica la ruta en `assets/js/main.js`

### Los gráficos no se muestran
✓ Verifica que Chart.js se cargue correctamente
✓ Revisa la consola del navegador
✓ Asegúrate de tener datos para el mes seleccionado

---

## MANTENIMIENTO

### Cambiar Contraseñas
Para cambiar la contraseña de un usuario, genera el MD5:
```php
echo md5('nueva_contraseña');
```
Luego actualiza en la BD:
```sql
UPDATE usuarios SET contrasena = 'hash_md5' WHERE nombre = 'nombre_usuario';
```

### Agregar Nuevos Socios
```sql
-- Insertar usuario
INSERT INTO usuarios (nombre, tipo, contrasena)
VALUES ('NuevoSocio', 'socio', 'e10adc3949ba59abbe56e057f20f883e');

-- Obtener el ID insertado (por ejemplo: 10)

-- Insertar socio
INSERT INTO socios (usuario_id, nombre_empresa)
VALUES (10, 'NuevoSocio');
```

### Recalcular ICASE
Si necesitas recalcular el ICASE de todos los socios para un mes:
```php
require_once 'includes/icase_calculator.php';
$calculator = new IcaseCalculator();
$calculator->recalcularIcaseTodosSocios($mes, $anio);
```

---

## SOPORTE Y CONTACTO

Este sistema fue desarrollado siguiendo las especificaciones completas del documento proporcionado.

### Características Implementadas:
✓ Base de datos completa
✓ Sistema de login
✓ Dashboards (Socio y Facilitador)
✓ Cálculo ICASE con redistribución de pesos
✓ Sistema de notificaciones con timer de 30 segundos
✓ Gráficos comparativos
✓ Diseño responsive
✓ Estructura completa de archivos

### Por Implementar (Base Creada):
- Formulario de registro de inspecciones (3 pestañas)
- Módulo de visualización de inspecciones recibidas
- Módulo de levantamiento de observaciones
- Funcionalidades especiales del facilitador

---

## NOTAS FINALES

1. **Contraseña por defecto:** Todos los usuarios tienen la contraseña `123456`
2. **ICASE Mensual:** El ICASE NO se calcula por inspección individual, sino mensualmente
3. **Redistribución de Pesos:** Se aplica automáticamente cuando hay ítems N.A.
4. **Notificaciones:** Desaparecen automáticamente en 30 segundos
5. **Gráficos:** Solo muestran socios/ítems con datos (no muestran ceros falsos)

---

**Sistema desarrollado para Hostinger con PHP y MySQL**
**Versión 1.0 - 2024**
