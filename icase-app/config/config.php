<?php
/**
 * Archivo de Configuración Principal
 * Sistema de Gestión de Inspecciones ICASE
 */

// Configuración de Base de Datos
define('DB_HOST', 'localhost');
define('DB_USER', 'root'); // Cambiar según tu configuración de Hostinger
define('DB_PASS', ''); // Cambiar según tu configuración de Hostinger
define('DB_NAME', 'icase_system');

// Configuración de Rutas
define('BASE_URL', 'http://localhost/icase-app/'); // Cambiar a tu dominio de Hostinger

// Ruta absoluta al directorio raíz de la aplicación
define('APP_ROOT', dirname(__DIR__) . '/');
define('UPLOAD_PATH', APP_ROOT . 'assets/uploads/');
define('UPLOAD_URL', BASE_URL . 'assets/uploads/');

// Configuración de Sesión
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_lifetime', 0);

// Configuración de Zona Horaria
date_default_timezone_set('America/Lima');

// Configuración de Errores (cambiar a 0 en producción)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuración de Uploads
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);

// Nombres de Facilitadores disponibles
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
