<?php
/**
 * Levantamiento de Observaciones
 * Interfaz Mitad/Mitad: Observación Original vs Levantamiento
 */
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

protegerSocio();

$db = Database::getInstance();
$socio_id = obtenerSocioId();
$mensaje = '';
$error = '';

// Obtener ID de observación
$observacion_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$observacion_id) {
    redirigir(BASE_URL . 'modules/inspecciones/recibidas.php');
}

// Procesar formulario de levantamiento
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $descripcion = limpiar($_POST['descripcion'] ?? '');

        if (empty($descripcion)) {
            $error = 'Debe proporcionar una descripción del levantamiento';
        } elseif (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Debe cargar una foto del levantamiento';
        } else {
            // Subir foto
            $upload_result = subirImagen($_FILES['foto'], 'levantamientos');

            if (!$upload_result['success']) {
                $error = $upload_result['error'];
            } else {
                $db->getConnection()->begin_transaction();

                // Guardar levantamiento
                $stmt = $db->prepare("
                    INSERT INTO levantamientos (observacion_id, foto_url, descripcion)
                    VALUES (?, ?, ?)
                ");
                $stmt->bind_param("iss", $observacion_id, $upload_result['url'], $descripcion);
                $stmt->execute();

                // Actualizar estado de observación
                $stmt = $db->prepare("UPDATE observaciones SET estado = 'completada' WHERE id = ?");
                $stmt->bind_param("i", $observacion_id);
                $stmt->execute();

                // Obtener inspector para notificar
                $stmt = $db->prepare("
                    SELECT i.socio_inspector_id, u.id as usuario_id
                    FROM observaciones o
                    INNER JOIN inspecciones i ON i.id = o.inspeccion_id
                    INNER JOIN socios s ON s.id = i.socio_inspector_id
                    INNER JOIN usuarios u ON u.id = s.usuario_id
                    WHERE o.id = ?
                ");
                $stmt->bind_param("i", $observacion_id);
                $stmt->execute();
                $inspector = $stmt->get_result()->fetch_assoc();

                // Crear notificación para el inspector
                $mensaje_notif = "Una observación ha sido levantada";
                $stmt = $db->prepare("
                    INSERT INTO notificaciones (usuario_id, tipo, mensaje, referencia_id)
                    VALUES (?, 'observacion_levantada', ?, ?)
                ");
                $stmt->bind_param("isi", $inspector['usuario_id'], $mensaje_notif, $observacion_id);
                $stmt->execute();

                $db->getConnection()->commit();
                $mensaje = 'Observación levantada exitosamente';
            }
        }
    } catch (Exception $e) {
        $db->getConnection()->rollback();
        $error = 'Error al levantar la observación: ' . $e->getMessage();
    }
}

// Obtener información de la observación
$stmt = $db->prepare("
    SELECT
        o.id,
        o.foto_url,
        o.descripcion,
        o.tipo,
        o.fecha_registro,
        o.fecha_vencimiento,
        o.estado,
        o.es_nula,
        i.id as inspeccion_id,
        i.fecha_inspeccion,
        i.lugar,
        i.nombre_inspector,
        si.nombre_empresa as inspector_empresa,
        l.id as levantamiento_id
    FROM observaciones o
    INNER JOIN inspecciones i ON i.id = o.inspeccion_id
    INNER JOIN socios si ON si.id = i.socio_inspector_id
    LEFT JOIN levantamientos l ON l.observacion_id = o.id
    WHERE o.id = ? AND i.socio_inspeccionado_id = ?
");
$stmt->bind_param("ii", $observacion_id, $socio_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    redirigir(BASE_URL . 'modules/inspecciones/recibidas.php');
}

$observacion = $result->fetch_assoc();

// Verificar que la observación pueda ser levantada
$puede_levantar = (!$observacion['levantamiento_id'] && !$observacion['es_nula'] && $observacion['estado'] != 'completada');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Levantar Observación - ICASE</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <div class="dashboard">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>ICASE</h2>
                <p><?php echo htmlspecialchars($_SESSION['nombre_usuario']); ?></p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="../dashboard/socio.php">Dashboard</a></li>
                <li><a href="../inspecciones/registrar.php">Registrar Inspección</a></li>
                <li><a href="../inspecciones/recibidas.php">Inspecciones Recibidas</a></li>
                <li><a href="../login/logout.php">Cerrar Sesión</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="top-bar">
                <h1>Levantamiento de Observación</h1>
                <a href="../inspecciones/detalle.php?id=<?php echo $observacion['inspeccion_id']; ?>" class="btn btn-secondary">
                    ← Volver a la Inspección
                </a>
            </div>

            <?php if ($mensaje): ?>
                <div class="alert alert-success">
                    <?php echo $mensaje; ?>
                    <br><br>
                    <a href="../inspecciones/detalle.php?id=<?php echo $observacion['inspeccion_id']; ?>" class="btn btn-primary">
                        Volver a la Inspección
                    </a>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if (!$mensaje): ?>
                <?php if (!$puede_levantar): ?>
                    <div class="alert alert-warning">
                        <?php if ($observacion['levantamiento_id']): ?>
                            Esta observación ya ha sido levantada.
                        <?php elseif ($observacion['es_nula']): ?>
                            Esta observación fue declarada nula por el facilitador.
                        <?php else: ?>
                            Esta observación no puede ser levantada.
                        <?php endif; ?>
                    </div>
                    <a href="../inspecciones/detalle.php?id=<?php echo $observacion['inspeccion_id']; ?>" class="btn btn-secondary">
                        ← Volver a la Inspección
                    </a>
                <?php else: ?>

                    <!-- INFORMACIÓN DE LA INSPECCIÓN -->
                    <div class="card">
                        <div class="card-header">
                            <h3>Información de la Inspección</h3>
                        </div>
                        <div class="card-body">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div>
                                    <p><strong>Fecha de Inspección:</strong> <?php echo formatearFecha($observacion['fecha_inspeccion']); ?></p>
                                    <p><strong>Inspector:</strong> <?php echo htmlspecialchars($observacion['nombre_inspector']); ?></p>
                                    <p><strong>Empresa:</strong> <?php echo htmlspecialchars($observacion['inspector_empresa']); ?></p>
                                </div>
                                <div>
                                    <p><strong>Lugar:</strong> <?php echo htmlspecialchars($observacion['lugar']); ?></p>
                                    <p><strong>Estado:</strong>
                                        <span class="badge badge-<?php echo obtenerClaseEstado($observacion['estado']); ?>">
                                            <?php echo strtoupper($observacion['estado']); ?>
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- INTERFAZ MITAD/MITAD -->
                    <div class="card">
                        <div class="card-header">
                            <h3>Levantamiento de Observación</h3>
                        </div>
                        <div class="card-body">
                            <div class="split-view">
                                <!-- COLUMNA IZQUIERDA: Observación Original -->
                                <div class="split-panel">
                                    <h4>📋 Observación Registrada</h4>

                                    <div style="margin-top: 15px;">
                                        <p><strong>Tipo:</strong> <?php echo $observacion['tipo'] ? 'Tipo ' . $observacion['tipo'] : 'Ninguno'; ?></p>
                                        <p><strong>Fecha de registro:</strong> <?php echo formatearFecha($observacion['fecha_registro']); ?></p>
                                        <p><strong>Fecha de vencimiento:</strong>
                                            <span style="color: <?php echo (strtotime($observacion['fecha_vencimiento']) < time()) ? 'var(--danger-color)' : 'var(--success-color)'; ?>;">
                                                <?php echo formatearFecha($observacion['fecha_vencimiento']); ?>
                                            </span>
                                        </p>

                                        <div style="margin: 15px 0;">
                                            <p><strong>Descripción:</strong></p>
                                            <p style="background-color: white; padding: 10px; border-radius: 5px;">
                                                <?php echo nl2br(htmlspecialchars($observacion['descripcion'])); ?>
                                            </p>
                                        </div>

                                        <div>
                                            <p><strong>Foto de la observación:</strong></p>
                                            <img src="<?php echo htmlspecialchars($observacion['foto_url']); ?>"
                                                 style="width: 100%; max-width: 400px; border-radius: 5px; border: 2px solid var(--border-color);"
                                                 alt="Foto de observación">
                                        </div>
                                    </div>
                                </div>

                                <!-- COLUMNA DERECHA: Levantamiento -->
                                <div class="split-panel">
                                    <h4>✅ Levantamiento</h4>

                                    <form method="POST" enctype="multipart/form-data" style="margin-top: 15px;">
                                        <div class="form-group">
                                            <label>Foto del Levantamiento *</label>
                                            <input type="file" name="foto" id="foto" accept="image/*" class="form-control" required
                                                   onchange="previewImagen(this, 'preview_foto')">
                                            <div id="preview_foto" style="margin-top: 10px;"></div>
                                        </div>

                                        <div class="form-group">
                                            <label>Descripción del Levantamiento *</label>
                                            <textarea name="descripcion" id="descripcion" class="form-control"
                                                      placeholder="Describa detalladamente cómo se levantó la observación..."
                                                      required style="min-height: 150px;"></textarea>
                                            <small style="color: #7f8c8d;">
                                                Explique las acciones tomadas para corregir la observación
                                            </small>
                                        </div>

                                        <div style="margin-top: 20px;">
                                            <button type="submit" class="btn btn-success btn-block">
                                                ✓ Guardar Levantamiento
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <div style="margin-top: 20px; padding: 15px; background-color: #fff3cd; border-radius: 5px; border-left: 4px solid #ffc107;">
                                <p style="margin: 0; color: #856404;">
                                    <strong>⚠️ Importante:</strong> Una vez guardado el levantamiento, la observación cambiará a estado "Completada"
                                    y se notificará al inspector. Asegúrese de que la foto y descripción sean claras y detalladas.
                                </p>
                            </div>
                        </div>
                    </div>

                <?php endif; ?>
            <?php endif; ?>

        </main>
    </div>

    <script src="../../assets/js/main.js"></script>
</body>
</html>
