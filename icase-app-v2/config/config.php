<?php
/**
 * ============================================================================
 * SISTEMA ICASE - Configuración Principal v2.0
 * ============================================================================
 * Gestión de Inspecciones Cruzadas con Cálculo Automático del ICASE
 */

// ============================================================================
// CONFIGURACIÓN DE BASE DE DATOS
// ============================================================================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');                    // Cambiar en producción
define('DB_PASS', '');                        // Cambiar en producción
define('DB_NAME', 'icase_system');

// ============================================================================
// CONFIGURACIÓN DE RUTAS
// ============================================================================
define('APP_ROOT', dirname(__DIR__) . '/');
define('BASE_URL', 'http://localhost/icase-app-v2/');  // Cambiar a tu dominio

// Rutas de archivos
define('CONFIG_PATH', APP_ROOT . 'config/');
define('INCLUDES_PATH', APP_ROOT . 'includes/');
define('MODULES_PATH', APP_ROOT . 'modules/');
define('ASSETS_PATH', APP_ROOT . 'assets/');

// Rutas de uploads
define('UPLOAD_PATH', ASSETS_PATH . 'uploads/');
define('UPLOAD_URL', BASE_URL . 'assets/uploads/');

// ============================================================================
// CONFIGURACIÓN DE SESIÓN
// ============================================================================
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_lifetime', 0);
session_name('ICASE_SESSION');

// ============================================================================
// CONFIGURACIÓN DE ZONA HORARIA
// ============================================================================
date_default_timezone_set('America/Lima');

// ============================================================================
// CONFIGURACIÓN DE ERRORES
// ============================================================================
// En desarrollo: mostrar errores
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// En producción: cambiar a 0
// error_reporting(0);
// ini_set('display_errors', 0);

// ============================================================================
// CONFIGURACIÓN DE UPLOADS
// ============================================================================
define('MAX_FILE_SIZE', 5242880);  // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

// ============================================================================
// CONFIGURACIÓN DEL SISTEMA
// ============================================================================

// Nombres de Facilitadores Disponibles
define('FACILITADORES', [
    'Raymundo Inga',
    'Renato Del Castillo',
    'Shadai Febrés',
    'Antonio Saldaña',
    'Ninguno'
]);

// Tipos de Observación
define('TIPOS_OBSERVACION', [
    'Ninguno',
    'Tipo 1',
    'Tipo 2',
    'Tipo 3',
    'Tipo 4',
    'Tipo 5',
    'Tipo 6'
]);

// Valores de Evaluación
define('VALORES_EVALUACION', [
    '0' => 'No cumple',
    '0.5' => 'Cumplimiento parcial',
    '1' => 'Cumplimiento total',
    'NA' => 'No aplica'
]);

// ============================================================================
// FUNCIONES DE AUTOLOAD
// ============================================================================
spl_autoload_register(function ($class_name) {
    $file = INCLUDES_PATH . $class_name . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});
