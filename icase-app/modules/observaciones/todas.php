<?php
/**
 * Todas las Observaciones - Vista Facilitador
 */
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/icase_calculator.php';

protegerFacilitador();

$db = Database::getInstance();
$calculator = new IcaseCalculator();
$mensaje = '';
$error = '';

// Actualizar estados
$calculator->actualizarEstadosObservaciones();

// Procesar acciones
if (isset($_POST['accion'])) {
    $observacion_id = (int)($_POST['observacion_id'] ?? 0);
    $accion = $_POST['accion'];

    try {
        switch ($accion) {
            case 'declarar_nula':
                $stmt = $db->prepare("UPDATE observaciones SET es_nula = 1 WHERE id = ?");
                $stmt->bind_param("i", $observacion_id);
                $stmt->execute();
                $mensaje = "Observación declarada como NULA exitosamente";
                break;

            case 'activar':
                $stmt = $db->prepare("UPDATE observaciones SET es_nula = 0 WHERE id = ?");
                $stmt->bind_param("i", $observacion_id);
                $stmt->execute();
                $mensaje = "Observación activada exitosamente";
                break;

            case 'cambiar_tipo':
                $tipo = $_POST['tipo'] == 'Ninguno' ? null : (int)str_replace('Tipo ', '', $_POST['tipo']);
                $stmt = $db->prepare("UPDATE observaciones SET tipo = ? WHERE id = ?");
                $stmt->bind_param("ii", $tipo, $observacion_id);
                $stmt->execute();
                $mensaje = "Tipo de observación actualizado";
                break;

            case 'eliminar':
                $stmt = $db->prepare("DELETE FROM observaciones WHERE id = ?");
                $stmt->bind_param("i", $observacion_id);
                $stmt->execute();
                $mensaje = "Observación eliminada exitosamente";
                break;
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Filtros
$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : obtenerMesActual();
$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : obtenerAnioActual();
$estado_filtro = $_GET['estado'] ?? 'todas';
$socio_filtro = isset($_GET['socio']) ? (int)$_GET['socio'] : 0;

// Consulta de observaciones con más detalles
$sql = "
    SELECT
        o.id,
        o.descripcion,
        o.tipo,
        o.fecha_registro,
        o.fecha_vencimiento,
        o.estado,
        o.es_nula,
        o.evidencia_foto,
        i.id as inspeccion_id,
        i.fecha_inspeccion,
        i.lugar,
        i.nombre_inspector,
        si.nombre_empresa as inspector_empresa,
        so.nombre_empresa as inspeccionado_empresa,
        (SELECT COUNT(*) FROM levantamientos WHERE observacion_id = o.id) as levantamiento_exists,
        l.id as levantamiento_id,
        l.descripcion_levantamiento,
        l.fecha_levantamiento,
        l.evidencia_foto as levantamiento_foto
    FROM observaciones o
    INNER JOIN inspecciones i ON i.id = o.inspeccion_id
    INNER JOIN socios si ON si.id = i.socio_inspector_id
    INNER JOIN socios so ON so.id = i.socio_inspeccionado_id
    LEFT JOIN levantamientos l ON l.observacion_id = o.id
    WHERE MONTH(o.fecha_registro) = ? AND YEAR(o.fecha_registro) = ?
";

$params = [$mes, $anio];
$types = "ii";

if ($estado_filtro != 'todas') {
    $sql .= " AND o.estado = ?";
    $params[] = $estado_filtro;
    $types .= "s";
}

if ($socio_filtro > 0) {
    $sql .= " AND i.socio_inspeccionado_id = ?";
    $params[] = $socio_filtro;
    $types .= "i";
}

$sql .= " ORDER BY o.fecha_registro DESC, o.estado ASC";

$stmt = $db->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$observaciones = $stmt->get_result();

// Obtener lista de socios
$socios_result = $db->query("SELECT id, nombre_empresa FROM socios ORDER BY nombre_empresa ASC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Todas las Observaciones - ICASE</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <div class="dashboard">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>ICASE</h2>
                <p>Facilitador</p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="../dashboard/facilitador.php">Dashboard</a></li>
                <li><a href="../inspecciones/todas.php">Todas las Inspecciones</a></li>
                <li><a href="#" class="active">Todas las Observaciones</a></li>
                <li><a href="../reportes/index.php">Reportes</a></li>
                <li><a href="../login/logout.php">Cerrar Sesión</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="top-bar">
                <h1>Gestión de Observaciones</h1>
            </div>

            <?php if ($mensaje): ?>
                <div class="alert alert-success"><?php echo $mensaje; ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Filtros -->
            <div class="card">
                <div class="card-body">
                    <form method="GET" action="" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; align-items: end;">
                        <div class="form-group" style="margin: 0;">
                            <label>Mes:</label>
                            <select name="mes" class="form-control">
                                <?php for($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?php echo $m; ?>" <?php echo $m == $mes ? 'selected' : ''; ?>>
                                        <?php echo obtenerNombreMes($m); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <label>Año:</label>
                            <select name="anio" class="form-control">
                                <?php for($a = 2024; $a <= 2030; $a++): ?>
                                    <option value="<?php echo $a; ?>" <?php echo $a == $anio ? 'selected' : ''; ?>>
                                        <?php echo $a; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <label>Estado:</label>
                            <select name="estado" class="form-control">
                                <option value="todas" <?php echo $estado_filtro == 'todas' ? 'selected' : ''; ?>>Todas</option>
                                <option value="en_plazo" <?php echo $estado_filtro == 'en_plazo' ? 'selected' : ''; ?>>En Plazo</option>
                                <option value="vencida" <?php echo $estado_filtro == 'vencida' ? 'selected' : ''; ?>>Vencidas</option>
                                <option value="completada" <?php echo $estado_filtro == 'completada' ? 'selected' : ''; ?>>Completadas</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <label>Socio:</label>
                            <select name="socio" class="form-control">
                                <option value="0">Todos</option>
                                <?php while($s = $socios_result->fetch_assoc()): ?>
                                    <option value="<?php echo $s['id']; ?>" <?php echo $s['id'] == $socio_filtro ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($s['nombre_empresa']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div>
                            <button type="submit" class="btn btn-primary">Filtrar</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Lista de Observaciones -->
            <div class="card">
                <div class="card-header">
                    <h3>Observaciones - <?php echo obtenerNombreMes($mes) . ' ' . $anio; ?> (<?php echo $observaciones->num_rows; ?>)</h3>
                </div>
                <div class="card-body">
                    <?php if ($observaciones->num_rows > 0): ?>
                        <?php while ($obs = $observaciones->fetch_assoc()): ?>
                            <div class="observacion-item" style="margin-bottom: 25px; border: 1px solid #ddd; border-radius: 8px; padding: 20px; background: #fff;">
                                <!-- Header -->
                                <div class="observacion-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0;">
                                    <div>
                                        <h4 style="margin: 0; color: #2c3e50;">
                                            📋 Observación #<?php echo $obs['id']; ?>
                                        </h4>
                                        <p style="margin: 5px 0 0 0; color: #7f8c8d; font-size: 0.9em;">
                                            <strong>Socio Inspeccionado:</strong> <?php echo htmlspecialchars($obs['inspeccionado_empresa']); ?>
                                        </p>
                                    </div>
                                    <div style="text-align: right;">
                                        <span class="badge badge-<?php echo obtenerClaseEstado($obs['estado']); ?>" style="font-size: 1em; padding: 8px 15px;">
                                            <?php echo strtoupper($obs['estado']); ?>
                                        </span>
                                        <?php if ($obs['es_nula']): ?>
                                            <span class="badge badge-danger" style="font-size: 1em; padding: 8px 15px; margin-left: 5px;">NULA</span>
                                        <?php endif; ?>
                                        <?php if ($obs['levantamiento_exists']): ?>
                                            <span class="badge badge-success" style="font-size: 1em; padding: 8px 15px; margin-left: 5px;">✓ LEVANTADA</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Detalles de la Observación y Levantamiento -->
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 20px;">

                                    <!-- Columna Izquierda: Observación Original -->
                                    <div style="border-right: 2px solid #f0f0f0; padding-right: 20px;">
                                        <h5 style="color: #e74c3c; margin-top: 0; margin-bottom: 15px; font-size: 1.1em;">
                                            🔴 Observación Registrada
                                        </h5>

                                        <div style="margin-bottom: 12px;">
                                            <strong style="color: #555;">Descripción:</strong>
                                            <p style="margin: 5px 0; padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">
                                                <?php echo nl2br(htmlspecialchars($obs['descripcion'])); ?>
                                            </p>
                                        </div>

                                        <div style="margin-bottom: 10px;">
                                            <strong style="color: #555;">Inspector:</strong> <?php echo htmlspecialchars($obs['nombre_inspector']); ?>
                                            (<?php echo htmlspecialchars($obs['inspector_empresa']); ?>)
                                        </div>

                                        <div style="margin-bottom: 10px;">
                                            <strong style="color: #555;">Inspección:</strong>
                                            <?php echo formatearFecha($obs['fecha_inspeccion']); ?> - <?php echo htmlspecialchars($obs['lugar']); ?>
                                        </div>

                                        <div style="margin-bottom: 10px;">
                                            <strong style="color: #555;">Fecha Registro:</strong> <?php echo formatearFecha($obs['fecha_registro']); ?>
                                        </div>

                                        <div style="margin-bottom: 10px;">
                                            <strong style="color: #555;">Fecha Vencimiento:</strong>
                                            <span style="color: <?php echo $obs['estado'] == 'vencida' ? '#e74c3c' : '#27ae60'; ?>; font-weight: bold;">
                                                <?php echo formatearFecha($obs['fecha_vencimiento']); ?>
                                            </span>
                                        </div>

                                        <div style="margin-bottom: 10px;">
                                            <strong style="color: #555;">Tipo:</strong>
                                            <?php
                                                if ($obs['tipo']) {
                                                    echo 'Tipo ' . $obs['tipo'];
                                                } else {
                                                    echo '<span style="color: #999;">Sin tipo asignado</span>';
                                                }
                                            ?>
                                        </div>

                                        <?php if ($obs['evidencia_foto']): ?>
                                            <div style="margin-top: 15px;">
                                                <strong style="color: #555;">Evidencia Fotográfica:</strong>
                                                <div style="margin-top: 8px;">
                                                    <a href="<?php echo BASE_URL . $obs['evidencia_foto']; ?>" target="_blank">
                                                        <img src="<?php echo BASE_URL . $obs['evidencia_foto']; ?>"
                                                             style="max-width: 100%; height: auto; border-radius: 8px; border: 2px solid #ddd; cursor: pointer;"
                                                             alt="Evidencia de observación">
                                                    </a>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Columna Derecha: Levantamiento -->
                                    <div style="padding-left: 20px;">
                                        <?php if ($obs['levantamiento_exists']): ?>
                                            <h5 style="color: #27ae60; margin-top: 0; margin-bottom: 15px; font-size: 1.1em;">
                                                ✅ Levantamiento Completado
                                            </h5>

                                            <div style="margin-bottom: 12px;">
                                                <strong style="color: #555;">Descripción del Levantamiento:</strong>
                                                <p style="margin: 5px 0; padding: 10px; background: #d4edda; border-left: 4px solid #28a745; border-radius: 4px;">
                                                    <?php echo nl2br(htmlspecialchars($obs['descripcion_levantamiento'] ?? 'Sin descripción')); ?>
                                                </p>
                                            </div>

                                            <div style="margin-bottom: 10px;">
                                                <strong style="color: #555;">Fecha de Levantamiento:</strong>
                                                <span style="color: #27ae60; font-weight: bold;">
                                                    <?php echo formatearFecha($obs['fecha_levantamiento']); ?>
                                                </span>
                                            </div>

                                            <?php if ($obs['levantamiento_foto']): ?>
                                                <div style="margin-top: 15px;">
                                                    <strong style="color: #555;">Evidencia del Levantamiento:</strong>
                                                    <div style="margin-top: 8px;">
                                                        <a href="<?php echo BASE_URL . $obs['levantamiento_foto']; ?>" target="_blank">
                                                            <img src="<?php echo BASE_URL . $obs['levantamiento_foto']; ?>"
                                                                 style="max-width: 100%; height: auto; border-radius: 8px; border: 2px solid #28a745; cursor: pointer;"
                                                                 alt="Evidencia de levantamiento">
                                                        </a>
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                        <?php else: ?>
                                            <h5 style="color: #f39c12; margin-top: 0; margin-bottom: 15px; font-size: 1.1em;">
                                                ⏳ Pendiente de Levantamiento
                                            </h5>
                                            <div style="padding: 20px; background: #fff3cd; border: 2px dashed #ffc107; border-radius: 8px; text-align: center;">
                                                <p style="margin: 0; color: #856404; font-size: 1.1em;">
                                                    Esta observación aún no ha sido levantada por el socio.
                                                </p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Panel de Acciones del Facilitador -->
                                <div style="border-top: 2px solid #f0f0f0; padding-top: 20px;">
                                    <h5 style="margin: 0 0 15px 0; color: #34495e;">⚙️ Acciones del Facilitador</h5>
                                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">

                                        <!-- Cambiar Tipo -->
                                        <div>
                                            <form method="POST" style="display: flex; gap: 10px; align-items: end;">
                                                <input type="hidden" name="observacion_id" value="<?php echo $obs['id']; ?>">
                                                <div class="form-group" style="flex: 1; margin: 0;">
                                                    <label style="font-size: 0.9em; font-weight: bold;">Cambiar Tipo de Observación:</label>
                                                    <select name="tipo" class="form-control">
                                                        <?php foreach (TIPOS_OBSERVACION as $tipo): ?>
                                                            <option <?php
                                                                if ($tipo == 'Ninguno' && $obs['tipo'] === null) echo 'selected';
                                                                elseif ($tipo != 'Ninguno' && $obs['tipo'] == (int)str_replace('Tipo ', '', $tipo)) echo 'selected';
                                                            ?>><?php echo $tipo; ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <button type="submit" name="accion" value="cambiar_tipo" class="btn btn-primary">Actualizar Tipo</button>
                                            </form>
                                        </div>

                                        <!-- Botones de Acción -->
                                        <div style="display: flex; gap: 10px; flex-direction: column;">
                                            <?php if (!$obs['es_nula']): ?>
                                                <form method="POST" style="margin: 0;">
                                                    <input type="hidden" name="observacion_id" value="<?php echo $obs['id']; ?>">
                                                    <button type="submit" name="accion" value="declarar_nula" class="btn btn-warning" style="width: 100%;"
                                                            onclick="return confirm('¿Declarar esta observación como NULA?\n\nEsta acción indica que la observación no es válida.');">
                                                        ⚠️ Declarar Nula
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" style="margin: 0;">
                                                    <input type="hidden" name="observacion_id" value="<?php echo $obs['id']; ?>">
                                                    <button type="submit" name="accion" value="activar" class="btn btn-success" style="width: 100%;">
                                                        ✓ Reactivar
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <form method="POST" style="margin: 0;">
                                                <input type="hidden" name="observacion_id" value="<?php echo $obs['id']; ?>">
                                                <button type="submit" name="accion" value="eliminar" class="btn btn-danger" style="width: 100%;"
                                                        onclick="return confirm('¿Eliminar esta observación PERMANENTEMENTE?\n\nEsta acción NO se puede deshacer.');">
                                                    🗑️ Eliminar
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="alert alert-info">No se encontraron observaciones con los filtros seleccionados.</div>
                    <?php endif; ?>
                </div>
            </div>

        </main>
    </div>

    <script src="../../assets/js/main.js"></script>
</body>
</html>
