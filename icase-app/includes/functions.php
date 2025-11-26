<?php
/**
 * Funciones Auxiliares del Sistema ICASE
 */

/**
 * Iniciar sesión
 */
function iniciarSesion() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Verificar si el usuario está logueado
 */
function estaLogueado() {
    iniciarSesion();
    return isset($_SESSION['usuario_id']) && isset($_SESSION['tipo_usuario']);
}

/**
 * Verificar si es facilitador
 */
function esFacilitador() {
    iniciarSesion();
    return isset($_SESSION['tipo_usuario']) && $_SESSION['tipo_usuario'] === 'facilitador';
}

/**
 * Verificar si es socio
 */
function esSocio() {
    iniciarSesion();
    return isset($_SESSION['tipo_usuario']) && $_SESSION['tipo_usuario'] === 'socio';
}

/**
 * Obtener ID del usuario logueado
 */
function obtenerUsuarioId() {
    iniciarSesion();
    return $_SESSION['usuario_id'] ?? null;
}

/**
 * Obtener ID del socio logueado (si es socio)
 */
function obtenerSocioId() {
    iniciarSesion();
    return $_SESSION['socio_id'] ?? null;
}

/**
 * Redirigir
 */
function redirigir($url) {
    header("Location: " . $url);
    exit();
}

/**
 * Proteger página (requiere login)
 */
function protegerPagina() {
    if (!estaLogueado()) {
        redirigir(BASE_URL . 'index.php');
    }
}

/**
 * Proteger página de facilitador
 */
function protegerFacilitador() {
    protegerPagina();
    if (!esFacilitador()) {
        redirigir(BASE_URL . 'modules/dashboard/socio.php');
    }
}

/**
 * Proteger página de socio
 */
function protegerSocio() {
    protegerPagina();
    if (!esSocio()) {
        redirigir(BASE_URL . 'modules/dashboard/facilitador.php');
    }
}

/**
 * Sanitizar entrada
 */
function limpiar($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Formatear fecha para mostrar
 */
function formatearFecha($fecha) {
    if (!$fecha) return '';
    $timestamp = strtotime($fecha);
    return date('d/m/Y', $timestamp);
}

/**
 * Obtener mes y año actual
 */
function obtenerMesActual() {
    return (int)date('n');
}

function obtenerAnioActual() {
    return (int)date('Y');
}

/**
 * Obtener nombre del mes
 */
function obtenerNombreMes($mes) {
    $meses = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];
    return $meses[$mes] ?? '';
}

/**
 * Subir archivo de imagen
 */
function subirImagen($file, $carpeta = 'observaciones') {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Error al subir el archivo'];
    }

    // Validar tamaño
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'error' => 'El archivo excede el tamaño máximo permitido (5MB)'];
    }

    // Validar extensión
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_EXTENSIONS)) {
        return ['success' => false, 'error' => 'Tipo de archivo no permitido. Solo: ' . implode(', ', ALLOWED_EXTENSIONS)];
    }

    // Generar nombre único
    $nombreArchivo = uniqid() . '_' . time() . '.' . $extension;
    $rutaDestino = UPLOAD_PATH . $carpeta . '/' . $nombreArchivo;

    // Mover archivo
    if (move_uploaded_file($file['tmp_name'], $rutaDestino)) {
        return [
            'success' => true,
            'filename' => $nombreArchivo,
            'url' => UPLOAD_URL . $carpeta . '/' . $nombreArchivo
        ];
    } else {
        return ['success' => false, 'error' => 'Error al mover el archivo'];
    }
}

/**
 * Calcular estado de observación
 */
function calcularEstadoObservacion($fecha_vencimiento, $es_completada) {
    if ($es_completada) {
        return 'completada';
    }

    $hoy = strtotime(date('Y-m-d'));
    $vencimiento = strtotime($fecha_vencimiento);

    if ($hoy > $vencimiento) {
        return 'vencida';
    }

    return 'en_plazo';
}

/**
 * Obtener clase CSS según estado
 */
function obtenerClaseEstado($estado) {
    switch ($estado) {
        case 'completada':
            return 'success';
        case 'vencida':
            return 'danger';
        case 'en_plazo':
            return 'warning';
        default:
            return 'secondary';
    }
}

/**
 * Obtener clase CSS según ICASE
 */
function obtenerClaseIcase($icase) {
    if ($icase >= 90) {
        return 'success';
    } elseif ($icase >= 70) {
        return 'warning';
    } else {
        return 'danger';
    }
}

/**
 * Obtener mensaje según ICASE
 */
function obtenerMensajeIcase($icase) {
    if ($icase >= 90) {
        return 'Desempeño Excelente';
    } elseif ($icase >= 70) {
        return 'Desempeño que puede mejorar';
    } else {
        return 'Mal desempeño';
    }
}

/**
 * Generar JSON response
 */
function jsonResponse($data, $httpCode = 200) {
    http_response_code($httpCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}
