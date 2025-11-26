<?php
/**
 * Todas las Inspecciones - Vista Facilitador
 */
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

protegerFacilitador();

$db = Database::getInstance();
$mensaje = '';
$error = '';

// Procesar eliminación de inspección
if (isset($_GET['eliminar']) && isset($_GET['confirm'])) {
    $inspeccion_id = (int)$_GET['eliminar'];

    try {
        $db->getConnection()->begin_transaction();

        // Eliminar inspección (cascada eliminará respuestas y observaciones)
        $stmt = $db->prepare("DELETE FROM inspecciones WHERE id = ?");
        $stmt->bind_param("i", $inspeccion_id);
        $stmt->execute();

        $db->getConnection()->commit();
        $mensaje = "Inspección eliminada exitosamente";
    } catch (Exception $e) {
        $db->getConnection()->rollback();
        $error = "Error al eliminar inspección: " . $e->getMessage();
    }
}

// Filtros
$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : obtenerMesActual();
$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : obtenerAnioActual();
$socio_filtro = isset($_GET['socio']) ? (int)$_GET['socio'] : 0;

// Obtener todas las inspecciones con filtros
$sql = "
    SELECT
        i.id,
        i.fecha_inspeccion,
        i.nombre_inspector,
        i.facilitador_nombre,
        i.lugar,
        si.nombre_empresa as inspector_empresa,
        so.nombre_empresa as inspeccionado_empresa,
        (SELECT COUNT(*) FROM observaciones WHERE inspeccion_id = i.id) as total_obs
    FROM inspecciones i
    INNER JOIN socios si ON si.id = i.socio_inspector_id
    INNER JOIN socios so ON so.id = i.socio_inspeccionado_id
    WHERE MONTH(i.fecha_inspeccion) = ? AND YEAR(i.fecha_inspeccion) = ?
";

$params = [$mes, $anio];
$types = "ii";

if ($socio_filtro > 0) {
    $sql .= " AND (i.socio_inspector_id = ? OR i.socio_inspeccionado_id = ?)";
    $params[] = $socio_filtro;
    $params[] = $socio_filtro;
    $types .= "ii";
}

$sql .= " ORDER BY i.fecha_inspeccion DESC";

$stmt = $db->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$inspecciones = $stmt->get_result();

// Obtener lista de socios para filtro
$socios_result = $db->query("SELECT id, nombre_empresa FROM socios ORDER BY nombre_empresa ASC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Todas las Inspecciones - ICASE</title>
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
                <li><a href="#" class="active">Todas las Inspecciones</a></li>
                <li><a href="../observaciones/todas.php">Todas las Observaciones</a></li>
                <li><a href="../reportes/index.php">Reportes</a></li>
                <li><a href="../login/logout.php">Cerrar Sesión</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="top-bar">
                <h1>Gestión de Inspecciones</h1>
                <div class="user-info">
                    <div class="user-avatar">F</div>
                    <span>Facilitador</span>
                </div>
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
                            <label>Socio:</label>
                            <select name="socio" class="form-control">
                                <option value="0">Todos los socios</option>
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

            <!-- Lista de Inspecciones -->
            <div class="card">
                <div class="card-header">
                    <h3>Inspecciones - <?php echo obtenerNombreMes($mes) . ' ' . $anio; ?> (<?php echo $inspecciones->num_rows; ?>)</h3>
                </div>
                <div class="card-body">
                    <?php if ($inspecciones->num_rows > 0): ?>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Fecha</th>
                                        <th>Inspector</th>
                                        <th>Empresa</th>
                                        <th>Inspeccionado</th>
                                        <th>Facilitador</th>
                                        <th>Lugar</th>
                                        <th>Obs</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($insp = $inspecciones->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $insp['id']; ?></td>
                                            <td><?php echo formatearFecha($insp['fecha_inspeccion']); ?></td>
                                            <td><?php echo htmlspecialchars($insp['nombre_inspector']); ?></td>
                                            <td><?php echo htmlspecialchars($insp['inspector_empresa']); ?></td>
                                            <td><?php echo htmlspecialchars($insp['inspeccionado_empresa']); ?></td>
                                            <td><?php echo htmlspecialchars($insp['facilitador_nombre']); ?></td>
                                            <td><?php echo htmlspecialchars($insp['lugar']); ?></td>
                                            <td><?php echo $insp['total_obs']; ?></td>
                                            <td style="white-space: nowrap;">
                                                <a href="detalle_facilitador.php?id=<?php echo $insp['id']; ?>" class="btn btn-primary btn-sm">Ver</a>
                                                <a href="editar.php?id=<?php echo $insp['id']; ?>" class="btn btn-warning btn-sm">Editar</a>
                                                <a href="?eliminar=<?php echo $insp['id']; ?>" class="btn btn-danger btn-sm"
                                                   onclick="return confirm('¿Está seguro de eliminar esta inspección? Esta acción eliminará también todas las observaciones asociadas.');">Eliminar</a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            No se encontraron inspecciones para los filtros seleccionados.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </main>
    </div>

    <script src="../../assets/js/main.js"></script>
    <script>
        // Confirmación mejorada para eliminar
        document.querySelectorAll('a[href*="eliminar"]').forEach(link => {
            link.addEventListener('click', function(e) {
                if (!this.href.includes('confirm=1')) {
                    e.preventDefault();
                    if (confirm('¿Está SEGURO de eliminar esta inspección?\n\nEsta acción eliminará:\n- La inspección completa\n- Todas las respuestas del checklist\n- Todas las observaciones asociadas\n- Todos los levantamientos\n\nEsta acción NO se puede deshacer.')) {
                        window.location.href = this.href + '&confirm=1';
                    }
                }
            });
        });
    </script>
</body>
</html>
