<?php
/**
 * Sistema de Notificaciones
 * Maneja las notificaciones con timer de 30 segundos
 */
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');
iniciarSesion();

if (!estaLogueado()) {
    jsonResponse(['error' => 'No autorizado'], 401);
}

$db = Database::getInstance();
$usuario_id = obtenerUsuarioId();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'obtener':
        // Obtener notificaciones no leídas
        $stmt = $db->prepare("
            SELECT id, tipo, mensaje, referencia_id, fecha_creacion
            FROM notificaciones
            WHERE usuario_id = ? AND leida = 0
            ORDER BY fecha_creacion DESC
        ");
        $stmt->bind_param("i", $usuario_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $notificaciones = [];
        while ($row = $result->fetch_assoc()) {
            $notificaciones[] = $row;
        }

        jsonResponse(['notificaciones' => $notificaciones]);
        break;

    case 'marcar_leida':
        // Marcar notificación como leída
        $notificacion_id = (int)($_POST['notificacion_id'] ?? 0);

        $stmt = $db->prepare("UPDATE notificaciones SET leida = 1 WHERE id = ? AND usuario_id = ?");
        $stmt->bind_param("ii", $notificacion_id, $usuario_id);
        $stmt->execute();

        jsonResponse(['success' => true]);
        break;

    case 'marcar_todas_leidas':
        // Marcar todas las notificaciones como leídas
        $stmt = $db->prepare("UPDATE notificaciones SET leida = 1 WHERE usuario_id = ? AND leida = 0");
        $stmt->bind_param("i", $usuario_id);
        $stmt->execute();

        jsonResponse(['success' => true]);
        break;

    default:
        jsonResponse(['error' => 'Acción no válida'], 400);
}
