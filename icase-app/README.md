# Sistema de Gestión de Inspecciones ICASE

## Descripción

Sistema web completo para la gestión de inspecciones cruzadas entre socios estratégicos, con cálculo automático del ICASE (Índice de Cumplimiento Ambiental por Socio Estratégico).

## Características Principales

✓ **Sistema de Login Dual** - Facilitador y Socios
✓ **Dashboards Interactivos** - Visualización de métricas y KPIs
✓ **Registro de Inspecciones** - Formulario de 3 pestañas con checklist de 28 preguntas
✓ **Cálculo Automático ICASE** - Con redistribución de pesos según ítems N.A.
✓ **Gestión de Observaciones** - Con estados (vencida, en plazo, completada)
✓ **Sistema de Notificaciones** - Pop-ups con timer de 30 segundos
✓ **Gráficos Comparativos** - Chart.js para visualización de datos
✓ **Diseño Responsive** - Adaptable a móviles, tablets y desktop

## Tecnologías

- **Backend:** PHP 7.4+
- **Base de Datos:** MySQL
- **Frontend:** HTML5, CSS3, JavaScript ES6
- **Gráficos:** Chart.js
- **Hosting:** Optimizado para Hostinger

## Instalación Rápida

1. **Importar Base de Datos:**
   - Acceder a phpMyAdmin
   - Ejecutar el script `database.sql`

2. **Configurar Conexión:**
   - Editar `config/config.php`
   - Actualizar credenciales de MySQL
   - Actualizar BASE_URL con tu dominio

3. **Subir Archivos:**
   - Subir carpeta `icase-app/` a `public_html/`
   - Configurar permisos 755 en carpetas `uploads/`

4. **Acceder al Sistema:**
   - Navegador: `https://tudominio.com/icase-app/`
   - Usuario Facilitador: `Facilitador` / Contraseña: `123456`
   - Socios: Seleccionar empresa / Contraseña: `123456`

## Documentación Completa

Ver archivo `README_INSTALACION.md` para instrucciones detalladas.

## Usuarios Precargados

### Facilitador
- Usuario: **Facilitador**
- Contraseña: **123456**

### Socios (todos con contraseña: 123456)
1. Antu
2. RSC
3. WSP
4. Stracon
5. Ausenco
6. Global
7. Flesan
8. Diamond

## Estructura del Proyecto

```
icase-app/
├── config/           # Configuración y conexión DB
├── includes/         # Funciones y clases auxiliares
├── assets/           # CSS, JS, imágenes, uploads
├── modules/          # Módulos funcionales
│   ├── login/
│   ├── dashboard/
│   ├── inspecciones/
│   ├── observaciones/
│   └── reportes/
├── index.php         # Página de login
└── database.sql      # Script de base de datos
```

## Sistema ICASE

El ICASE es un indicador **mensual** que consolida múltiples inspecciones:

1. **Evaluación:** 28 preguntas en 7 ítems (valores: 0, 0.5, 1, N.A.)
2. **Cálculo por Ítem:** % = (suma valores aplicables / total aplicables) × 100
3. **Consolidación Mensual:** Promedio de inspecciones donde el ítem fue aplicable
4. **Redistribución de Pesos:** Si un ítem es N.A., su peso se redistribuye proporcionalmente
5. **ICASE Final:** Suma ponderada de todos los ítems aplicables (0-100%)

## Soporte

Para consultas técnicas, revisar la documentación completa en `README_INSTALACION.md`.

---

**Versión 1.0 - 2024**
**Desarrollado para Hostinger con PHP y MySQL**
