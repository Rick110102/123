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

// Consulta de observaciones
$sql = "
    SELECT
        o.id,
        o.descripcion,
        o.tipo,
        o.fecha_registro,
        o.fecha_vencimiento,
        o.estado,
        o.es_nula,
        i.id as inspeccion_id,
        i.fecha_inspeccion,
        i.lugar,
        si.nombre_empresa as inspector_empresa,
        so.nombre_empresa as inspeccionado_empresa,
        (SELECT COUNT(*) FROM levantamientos WHERE observacion_id = o.id) as levantamiento_exists
    FROM observaciones o
    INNER JOIN inspecciones i ON i.id = o.inspeccion_id
    INNER JOIN socios si ON si.id = i.socio_inspector_id
    INNER JOIN socios so ON so.id = i.socio_inspeccionado_id
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
                            <div class="observacion-item" style="margin-bottom: 20px;">
                                <div class="observacion-header">
                                    <div>
                                        <strong>ID <?php echo $obs['id']; ?></strong> -
                                        <span><?php echo htmlspecialchars($obs['inspeccionado_empresa']); ?></span>
                                    </div>
                                    <div>
                                        <span class="badge badge-<?php echo obtenerClaseEstado($obs['estado']); ?>">
                                            <?php echo strtoupper($obs['estado']); ?>
                                        </span>
                                        <?php if ($obs['es_nula']): ?>
                                            <span class="badge badge-danger">NULA</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-top: 10px;">
                                    <div>
                                        <p><strong>Descripción:</strong> <?php echo htmlspecialchars($obs['descripcion']); ?></p>
                                        <p><strong>Inspección:</strong> <?php echo formatearFecha($obs['fecha_inspeccion']); ?> - <?php echo htmlspecialchars($obs['lugar']); ?></p>
                                        <p><strong>Registrada:</strong> <?php echo formatearFecha($obs['fecha_registro']); ?> | <strong>Vence:</strong> <?php echo formatearFecha($obs['fecha_vencimiento']); ?></p>
                                    </div>

                                    <div>
                                        <form method="POST" style="margin-bottom: 10px;">
                                            <input type="hidden" name="observacion_id" value="<?php echo $obs['id']; ?>">
                                            <label style="font-size: 0.9em;">Tipo:</label>
                                            <select name="tipo" class="form-control" style="margin-bottom: 5px;">
                                                <?php foreach (TIPOS_OBSERVACION as $tipo): ?>
                                                    <option <?php
                                                        if ($tipo == 'Ninguno' && $obs['tipo'] === null) echo 'selected';
                                                        elseif ($tipo != 'Ninguno' && $obs['tipo'] == (int)str_replace('Tipo ', '', $tipo)) echo 'selected';
                                                    ?>><?php echo $tipo; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" name="accion" value="cambiar_tipo" class="btn btn-sm btn-primary">Actualizar Tipo</button>
                                        </form>

                                        <div style="display: flex; gap: 5px; flex-direction: column;">
                                            <?php if (!$obs['es_nula']): ?>
                                                <form method="POST" style="margin: 0;">
                                                    <input type="hidden" name="observacion_id" value="<?php echo $obs['id']; ?>">
                                                    <button type="submit" name="accion" value="declarar_nula" class="btn btn-sm btn-warning" style="width: 100%;"
                                                            onclick="return confirm('¿Declarar como NULA?');">Declarar Nula</button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" style="margin: 0;">
                                                    <input type="hidden" name="observacion_id" value="<?php echo $obs['id']; ?>">
                                                    <button type="submit" name="accion" value="activar" class="btn btn-sm btn-success" style="width: 100%;">Activar</button>
                                                </form>
                                            <?php endif; ?>

                                            <form method="POST" style="margin: 0;">
                                                <input type="hidden" name="observacion_id" value="<?php echo $obs['id']; ?>">
                                                <button type="submit" name="accion" value="eliminar" class="btn btn-sm btn-danger" style="width: 100%;"
                                                        onclick="return confirm('¿Eliminar esta observación permanentemente?');">Eliminar</button>
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
