<?php
/**
 * Ver Inspecciones Recibidas - SOCIO
 */
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/icase_calculator.php';

protegerSocio();

$db = Database::getInstance();
$calculator = new IcaseCalculator();
$socio_id = obtenerSocioId();

// Filtros
$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : obtenerMesActual();
$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : obtenerAnioActual();

// Obtener inspecciones recibidas
$stmt = $db->prepare("
    SELECT
        i.id,
        i.fecha_inspeccion,
        i.nombre_inspector,
        i.facilitador_nombre,
        i.lugar,
        s.nombre_empresa as inspector_empresa,
        (SELECT COUNT(*) FROM observaciones WHERE inspeccion_id = i.id AND es_nula = 0) as total_observaciones
    FROM inspecciones i
    INNER JOIN socios s ON s.id = i.socio_inspector_id
    WHERE i.socio_inspeccionado_id = ?
    AND MONTH(i.fecha_inspeccion) = ?
    AND YEAR(i.fecha_inspeccion) = ?
    ORDER BY i.fecha_inspeccion DESC
");
$stmt->bind_param("iii", $socio_id, $mes, $anio);
$stmt->execute();
$inspecciones = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inspecciones Recibidas - ICASE</title>
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
                <li><a href="registrar.php">Registrar Inspección</a></li>
                <li><a href="#" class="active">Inspecciones Recibidas</a></li>
                <li><a href="../login/logout.php">Cerrar Sesión</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="top-bar">
                <h1>Inspecciones que me Realizaron</h1>
            </div>

            <!-- Filtros -->
            <div class="filters">
                <div class="filter-group">
                    <label>Mes:</label>
                    <select id="filtro_mes" class="form-control">
                        <?php for($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $m == $mes ? 'selected' : ''; ?>>
                                <?php echo obtenerNombreMes($m); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Año:</label>
                    <select id="filtro_anio" class="form-control">
                        <?php for($a = 2024; $a <= 2030; $a++): ?>
                            <option value="<?php echo $a; ?>" <?php echo $a == $anio ? 'selected' : ''; ?>>
                                <?php echo $a; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <button class="btn btn-primary" onclick="aplicarFiltros()">Aplicar</button>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>Lista de Inspecciones - <?php echo obtenerNombreMes($mes) . ' ' . $anio; ?></h3>
                </div>
                <div class="card-body">
                    <?php if ($inspecciones->num_rows > 0): ?>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Inspector</th>
                                        <th>Empresa</th>
                                        <th>Facilitador</th>
                                        <th>Lugar</th>
                                        <th>Observaciones</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($insp = $inspecciones->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo formatearFecha($insp['fecha_inspeccion']); ?></td>
                                            <td><?php echo htmlspecialchars($insp['nombre_inspector']); ?></td>
                                            <td><?php echo htmlspecialchars($insp['inspector_empresa']); ?></td>
                                            <td><?php echo htmlspecialchars($insp['facilitador_nombre']); ?></td>
                                            <td><?php echo htmlspecialchars($insp['lugar']); ?></td>
                                            <td>
                                                <span class="badge badge-info">
                                                    <?php echo $insp['total_observaciones']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="detalle.php?id=<?php echo $insp['id']; ?>" class="btn btn-primary btn-sm">
                                                    Ver Detalle
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            No se encontraron inspecciones para <?php echo obtenerNombreMes($mes) . ' ' . $anio; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="../../assets/js/main.js"></script>
</body>
</html>
