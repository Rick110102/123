<?php
/**
 * Dashboard del Socio
 */
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/icase_calculator.php';

protegerSocio();

$db = Database::getInstance();
$calculator = new IcaseCalculator();
$socio_id = obtenerSocioId();
$nombre_socio = $_SESSION['nombre_usuario'];

// Obtener filtros
$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : obtenerMesActual();
$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : obtenerAnioActual();

// Actualizar estados de observaciones
$calculator->actualizarEstadosObservaciones();

// Obtener ICASE del mes
$icase = $calculator->obtenerIcaseMensual($socio_id, $mes, $anio);

// Obtener estadísticas de inspecciones
$stmt = $db->prepare("
    SELECT COUNT(*) as total
    FROM inspecciones
    WHERE socio_inspector_id = ?
    AND MONTH(fecha_inspeccion) = ?
    AND YEAR(fecha_inspeccion) = ?
");
$stmt->bind_param("iii", $socio_id, $mes, $anio);
$stmt->execute();
$inspecciones_realizadas = $stmt->get_result()->fetch_assoc()['total'];

$stmt = $db->prepare("
    SELECT COUNT(*) as total
    FROM inspecciones
    WHERE socio_inspeccionado_id = ?
    AND MONTH(fecha_inspeccion) = ?
    AND YEAR(fecha_inspeccion) = ?
");
$stmt->bind_param("iii", $socio_id, $mes, $anio);
$stmt->execute();
$inspecciones_recibidas = $stmt->get_result()->fetch_assoc()['total'];

// Obtener estadísticas de observaciones
$stmt = $db->prepare("
    SELECT COUNT(*) as total
    FROM observaciones o
    INNER JOIN inspecciones i ON i.id = o.inspeccion_id
    WHERE i.socio_inspeccionado_id = ?
    AND o.estado = 'vencida'
    AND o.es_nula = 0
    AND MONTH(o.fecha_registro) = ?
    AND YEAR(o.fecha_registro) = ?
");
$stmt->bind_param("iii", $socio_id, $mes, $anio);
$stmt->execute();
$obs_vencidas = $stmt->get_result()->fetch_assoc()['total'];

$stmt = $db->prepare("
    SELECT COUNT(*) as total
    FROM observaciones o
    INNER JOIN inspecciones i ON i.id = o.inspeccion_id
    WHERE i.socio_inspeccionado_id = ?
    AND o.estado = 'en_plazo'
    AND o.es_nula = 0
    AND MONTH(o.fecha_registro) = ?
    AND YEAR(o.fecha_registro) = ?
");
$stmt->bind_param("iii", $socio_id, $mes, $anio);
$stmt->execute();
$obs_en_plazo = $stmt->get_result()->fetch_assoc()['total'];

$stmt = $db->prepare("
    SELECT COUNT(*) as total
    FROM observaciones o
    INNER JOIN inspecciones i ON i.id = o.inspeccion_id
    WHERE i.socio_inspeccionado_id = ?
    AND o.estado = 'completada'
    AND o.es_nula = 0
    AND MONTH(o.fecha_registro) = ?
    AND YEAR(o.fecha_registro) = ?
");
$stmt->bind_param("iii", $socio_id, $mes, $anio);
$stmt->execute();
$obs_completadas = $stmt->get_result()->fetch_assoc()['total'];

// Obtener porcentajes por ítem
$porcentajes_items = $calculator->obtenerPorcentajesItemsMensual($socio_id, $mes, $anio);

// Obtener evolución del ICASE (últimos 12 meses)
$icases_evolucion = [];
for ($i = 11; $i >= 0; $i--) {
    $mes_calc = $mes - $i;
    $anio_calc = $anio;

    if ($mes_calc <= 0) {
        $mes_calc += 12;
        $anio_calc--;
    }

    $icase_mes = $calculator->obtenerIcaseMensual($socio_id, $mes_calc, $anio_calc);
    if ($icase_mes !== null) {
        $icases_evolucion[] = [
            'mes' => $mes_calc,
            'anio' => $anio_calc,
            'icase' => $icase_mes,
            'label' => obtenerNombreMes($mes_calc) . ' ' . $anio_calc
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo htmlspecialchars($nombre_socio); ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>ICASE</h2>
                <p><?php echo htmlspecialchars($nombre_socio); ?></p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="#" class="active">Dashboard</a></li>
                <li><a href="../inspecciones/registrar.php">Registrar Inspección</a></li>
                <li><a href="../inspecciones/recibidas.php">Inspecciones Recibidas</a></li>
                <li><a href="../observaciones/mis_observaciones.php">Mis Observaciones</a></li>
                <li><a href="../login/logout.php">Cerrar Sesión</a></li>
            </ul>
        </aside>

        <!-- Contenido Principal -->
        <main class="main-content">
            <!-- Barra Superior -->
            <div class="top-bar">
                <h1>Dashboard - <?php echo htmlspecialchars($nombre_socio); ?></h1>
                <div class="user-info">
                    <div class="user-avatar"><?php echo strtoupper(substr($nombre_socio, 0, 1)); ?></div>
                    <span><?php echo htmlspecialchars($nombre_socio); ?></span>
                </div>
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

            <!-- Indicador ICASE Principal -->
            <?php if ($icase !== null): ?>
                <div class="card">
                    <div class="icase-indicator">
                        <h3>ICASE - <?php echo obtenerNombreMes($mes) . ' ' . $anio; ?></h3>
                        <div class="icase-value <?php echo obtenerClaseIcase($icase); ?>">
                            <?php echo number_format($icase, 2); ?>%
                        </div>
                        <div class="icase-message" style="color: var(--<?php echo obtenerClaseIcase($icase); ?>-color);">
                            <?php echo obtenerMensajeIcase($icase); ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="alert alert-info">
                        No hay datos suficientes para calcular el ICASE de <?php echo obtenerNombreMes($mes) . ' ' . $anio; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Estadísticas de Inspecciones y Observaciones -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h4><?php echo $inspecciones_realizadas; ?></h4>
                    <p>Inspecciones Realizadas</p>
                </div>
                <div class="stat-card success">
                    <h4><?php echo $inspecciones_recibidas; ?></h4>
                    <p>Inspecciones Recibidas</p>
                </div>
                <div class="stat-card danger">
                    <h4><?php echo $obs_vencidas; ?></h4>
                    <p>Observaciones Vencidas</p>
                </div>
                <div class="stat-card warning">
                    <h4><?php echo $obs_en_plazo; ?></h4>
                    <p>Observaciones en Plazo</p>
                </div>
                <div class="stat-card success">
                    <h4><?php echo $obs_completadas; ?></h4>
                    <p>Observaciones Completadas</p>
                </div>
            </div>

            <!-- Botones de Acción -->
            <div class="card">
                <div style="display: flex; gap: 15px; justify-content: center;">
                    <a href="../inspecciones/registrar.php" class="btn btn-primary">
                        Registrar Nueva Inspección
                    </a>
                    <a href="../inspecciones/recibidas.php" class="btn btn-success">
                        Ver Inspecciones que me Realizaron
                    </a>
                </div>
            </div>

            <!-- Desempeño por Ítems -->
            <div class="card">
                <div class="card-header">
                    <h3>Desempeño por Ítems - <?php echo obtenerNombreMes($mes) . ' ' . $anio; ?></h3>
                </div>
                <div class="card-body">
                    <div class="chart-container chart-items">
                        <canvas id="chartItems"></canvas>
                    </div>
                </div>
            </div>

            <!-- Evolución del ICASE -->
            <?php if (!empty($icases_evolucion)): ?>
            <div class="card">
                <div class="card-header">
                    <h3>Evolución del ICASE</h3>
                </div>
                <div class="card-body">
                    <div class="chart-container chart-evolution">
                        <canvas id="chartEvolucion"></canvas>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <script src="../../assets/js/main.js"></script>
    <script>
        // Gráfico de desempeño por ítems (HORIZONTAL con colores dinámicos)
        <?php if (!empty($porcentajes_items)): ?>
        const itemsData = [<?php
            $data = [];
            foreach ($porcentajes_items as $item) {
                if (!$item['es_na']) {
                    $data[] = $item['porcentaje'];
                }
            }
            echo implode(',', $data);
        ?>];
        const itemsColors = getColorsArrayByPerformance(itemsData);

        const ctxItems = document.getElementById('chartItems').getContext('2d');
        new Chart(ctxItems, {
            type: 'bar',
            data: {
                labels: [<?php
                    $labels = [];
                    foreach ($porcentajes_items as $item) {
                        if (!$item['es_na']) {
                            $labels[] = "'" . addslashes($item['item_nombre']) . "'";
                        }
                    }
                    echo implode(',', $labels);
                ?>],
                datasets: [{
                    label: 'Porcentaje de Cumplimiento',
                    data: itemsData,
                    backgroundColor: itemsColors.backgrounds,
                    borderColor: itemsColors.borders,
                    borderWidth: 2
                }]
            },
            options: {
                indexAxis: 'y', // Esto hace el gráfico horizontal
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Cumplimiento: ' + context.parsed.x + '%';
                            }
                        }
                    }
                }
            }
        });
        <?php endif; ?>

        // Gráfico de evolución del ICASE (con colores dinámicos)
        <?php if (!empty($icases_evolucion)): ?>
        const evolucionData = [<?php echo implode(',', array_column($icases_evolucion, 'icase')); ?>];
        const evolucionColors = getColorsArrayByPerformance(evolucionData);

        const ctxEvolucion = document.getElementById('chartEvolucion').getContext('2d');
        new Chart(ctxEvolucion, {
            type: 'bar',
            data: {
                labels: [<?php
                    echo "'" . implode("','", array_column($icases_evolucion, 'label')) . "'";
                ?>],
                datasets: [{
                    label: 'ICASE',
                    data: evolucionData,
                    backgroundColor: evolucionColors.backgrounds,
                    borderColor: evolucionColors.borders,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'ICASE: ' + context.parsed.y + '%';
                            }
                        }
                    }
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>
