# 🔧 INSTRUCCIONES PARA CORREGIR ERRORES

## Problemas Reportados y Soluciones

---

## 1️⃣ ERROR DE UPLOAD DE IMÁGENES

### Problema:
```
Warning: move_uploaded_file(...): Failed to open stream: No such file or directory
```

### Causa:
Las carpetas de upload no existen o no tienen permisos correctos.

### ✅ SOLUCIÓN:

#### Paso 1: Ejecutar Script de Instalación
1. Abre tu navegador
2. Ve a: `https://tudominio.com/install.php`
3. El script creará automáticamente las carpetas necesarias
4. Verifica que todo esté en verde
5. **ELIMINA el archivo install.php** después de usarlo

#### Paso 2: Si el script falla, crear manualmente
Conéctate por FTP o File Manager y:

1. Crea estas carpetas dentro de `icase-app/assets/`:
   ```
   assets/
   └── uploads/
       ├── observaciones/
       └── levantamientos/
   ```

2. Dale permisos **755** a cada carpeta:
   - En File Manager: Click derecho → Permisos → 755
   - Por FTP: `chmod 755 assets/uploads/observaciones`
   - Por FTP: `chmod 755 assets/uploads/levantamientos`

#### Paso 3: Verificar
- Intenta registrar una inspección con observación
- La foto debería subir sin errores

---

## 2️⃣ NO SE MUESTRAN LOS RESULTADOS (ICASE)

### Posibles Causas:
1. No hay inspecciones registradas para el mes actual
2. Los porcentajes por ítem no se calcularon
3. El ICASE no se calculó después de registrar

### ✅ SOLUCIÓN:

#### Paso 1: Ejecutar Script de Depuración
1. Abre: `https://tudominio.com/debug.php`
2. Revisa cada sección:
   - ¿Hay inspecciones registradas?
   - ¿Los porcentajes están calculados?
   - ¿El ICASE se calculó?

#### Paso 2: Si hay inspecciones pero no hay ICASE:
El ICASE se calcula automáticamente al registrar una inspección, pero si algo falló:

1. Ve al script `debug.php`
2. En la sección "5. Intentar Calcular ICASE" verás si se calculó
3. Si dice "Sin datos", significa que no hay inspecciones del mes
4. Si dice "Error", anota el error y repórtalo

#### Paso 3: Registrar una inspección de prueba
1. Inicia sesión como SOCIO
2. Registra una inspección completa (3 pestañas)
3. Ve al Dashboard
4. Deberías ver el ICASE calculado

#### Paso 4: ELIMINAR debug.php
Por seguridad, elimina el archivo después de usarlo.

---

## 3️⃣ NO PUEDO ACCEDER A FUNCIONES DE FACILITADOR

### ✅ SOLUCIÓN:
Ahora ya están implementadas. Inicia sesión como FACILITADOR y verás:

### Nuevas Páginas Disponibles:

#### A) Todas las Inspecciones
- **URL:** `modules/inspecciones/todas.php`
- **Acceso:** Desde sidebar → "Todas las Inspecciones"
- **Funciones:**
  - ✅ Ver todas las inspecciones
  - ✅ Filtrar por mes/año/socio
  - ✅ **Eliminar inspecciones**
  - ✅ Ver detalles
  - ✅ Editar (próximamente)

#### B) Todas las Observaciones
- **URL:** `modules/observaciones/todas.php`
- **Acceso:** Desde sidebar → "Todas las Observaciones"
- **Funciones:**
  - ✅ Ver todas las observaciones
  - ✅ Filtrar por estado/mes/socio
  - ✅ **Cambiar tipo de observación**
  - ✅ **Declarar como NULA**
  - ✅ **Activar observaciones nulas**
  - ✅ **Eliminar observaciones**

#### C) Dashboard
- **URL:** `modules/dashboard/facilitador.php`
- **Funciones:**
  - ✅ Ver ICASE de todos los socios
  - ✅ Gráficos comparativos
  - ✅ Estadísticas globales

---

## 4️⃣ ARCHIVOS MODIFICADOS/CREADOS

### ✅ Archivos Modificados:
1. **config/config.php**
   - Rutas absolutas corregidas
   - Nueva constante APP_ROOT

2. **includes/functions.php**
   - Función subirImagen() mejorada
   - Crea directorios automáticamente
   - Mejor manejo de errores

### ✅ Archivos Nuevos:
3. **install.php** (Script de instalación)
   - Crea carpetas de upload
   - Verifica permisos
   - **ELIMINAR después de usar**

4. **debug.php** (Script de depuración)
   - Diagnóstico completo del sistema
   - Verifica datos
   - Calcula ICASE manualmente
   - **ELIMINAR después de usar**

5. **modules/inspecciones/todas.php**
   - Vista completa de inspecciones
   - Filtros avanzados
   - Eliminar inspecciones

6. **modules/observaciones/todas.php**
   - Vista completa de observaciones
   - Gestión completa (tipificar, nulificar, eliminar)
   - Filtros múltiples

---

## 📋 CHECKLIST DE VERIFICACIÓN

Después de aplicar las correcciones, verifica:

### Upload de Imágenes:
- [ ] Carpetas creadas: `assets/uploads/observaciones/` y `levantamientos/`
- [ ] Permisos 755 en ambas carpetas
- [ ] Se pueden subir fotos en observaciones
- [ ] Se pueden subir fotos en levantamientos

### ICASE y Resultados:
- [ ] Al registrar inspección, se calculan porcentajes por ítem
- [ ] Se muestra el ICASE en el dashboard del socio
- [ ] Los gráficos muestran datos
- [ ] Los colores dinámicos funcionan (verde/amarillo/rojo)

### Funciones del Facilitador:
- [ ] Puede ver "Todas las Inspecciones"
- [ ] Puede eliminar inspecciones
- [ ] Puede ver "Todas las Observaciones"
- [ ] Puede cambiar tipo de observación
- [ ] Puede declarar observaciones como nulas
- [ ] Puede eliminar observaciones

---

## 🚨 SI PERSISTEN LOS ERRORES

### 1. Error de Upload:
```
Verifica en debug.php → Sección 6 "Directorios de Upload"
```
Si dice "No existe" o "No escribible":
- Crea las carpetas manualmente
- Dale permisos 755 o 777

### 2. No se ve ICASE:
```
Ejecuta debug.php → Sección 5 "Intentar Calcular ICASE"
```
Si dice "Sin datos":
- Registra una inspección para el mes actual
- El ICASE se calculará automáticamente

Si dice "Error":
- Anota el mensaje de error
- Verifica que todas las 28 preguntas tengan respuesta

### 3. Errores de Base de Datos:
```
Verifica en debug.php → Sección 1 "Conexión a Base de Datos"
```
Si falla:
- Verifica credenciales en `config/config.php`
- Asegúrate que la base de datos existe
- Verifica permisos del usuario MySQL

---

## 📁 ARCHIVOS A SUBIR A HOSTINGER

Reemplaza estos archivos en tu servidor:

```
icase-app/
├── config/
│   └── config.php (MODIFICADO)
├── includes/
│   └── functions.php (MODIFICADO)
├── modules/
│   ├── inspecciones/
│   │   └── todas.php (NUEVO)
│   └── observaciones/
│       └── todas.php (NUEVO)
├── install.php (NUEVO - temporal)
└── debug.php (NUEVO - temporal)
```

---

## ⚡ PASOS RÁPIDOS DE CORRECCIÓN

### En tu servidor Hostinger:

1. **Subir archivos actualizados**
   - Reemplaza `config/config.php`
   - Reemplaza `includes/functions.php`
   - Agrega `modules/inspecciones/todas.php`
   - Agrega `modules/observaciones/todas.php`
   - Agrega `install.php`
   - Agrega `debug.php`

2. **Ejecutar install.php**
   - Abre en navegador: `tudominio.com/install.php`
   - Verifica que todo esté en verde
   - Elimina el archivo después

3. **Ejecutar debug.php**
   - Abre en navegador: `tudominio.com/debug.php`
   - Revisa si hay errores
   - Anota cualquier problema
   - Elimina el archivo después

4. **Probar el sistema**
   - Inicia sesión como SOCIO
   - Registra una inspección con observación
   - Verifica que la foto se suba
   - Ve al dashboard y verifica ICASE

5. **Probar funciones de facilitador**
   - Inicia sesión como FACILITADOR
   - Ve a "Todas las Inspecciones"
   - Ve a "Todas las Observaciones"
   - Prueba eliminar, tipificar, etc.

---

## 📞 SOPORTE

Si después de seguir estos pasos todavía hay problemas:

1. Ejecuta `debug.php` y copia el resultado
2. Anota exactamente qué error aparece
3. Indica en qué paso del proceso falla
4. Proporciona capturas de pantalla si es posible

---

**¡Todo debería funcionar correctamente después de estos pasos!** 🎉
