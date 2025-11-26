<?php
/**
 * Dashboard del Facilitador
 */
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/icase_calculator.php';

protegerFacilitador();

$db = Database::getInstance();
$calculator = new IcaseCalculator();

$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : obtenerMesActual();
$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : obtenerAnioActual();

$calculator->actualizarEstadosObservaciones();

// Estadísticas globales del mes
$stmt = $db->prepare("
    SELECT COUNT(*) as total
    FROM inspecciones
    WHERE MONTH(fecha_inspeccion) = ? AND YEAR(fecha_inspeccion) = ?
");
$stmt->bind_param("ii", $mes, $anio);
$stmt->execute();
$total_inspecciones = $stmt->get_result()->fetch_assoc()['total'];

// Observaciones globales
$stmt = $db->prepare("
    SELECT
        SUM(CASE WHEN estado = 'vencida' THEN 1 ELSE 0 END) as vencidas,
        SUM(CASE WHEN estado = 'en_plazo' THEN 1 ELSE 0 END) as en_plazo,
        SUM(CASE WHEN estado = 'completada' THEN 1 ELSE 0 END) as completadas,
        COUNT(*) as total
    FROM observaciones
    WHERE MONTH(fecha_registro) = ? AND YEAR(fecha_registro) = ? AND es_nula = 0
");
$stmt->bind_param("ii", $mes, $anio);
$stmt->execute();
$obs_stats = $stmt->get_result()->fetch_assoc();

// Obtener ICASE de todos los socios
$socios_icase = [];
$socios_result = $db->query("SELECT id, nombre_empresa FROM socios ORDER BY nombre_empresa ASC");
while ($socio = $socios_result->fetch_assoc()) {
    $icase = $calculator->obtenerIcaseMensual($socio['id'], $mes, $anio);
    if ($icase !== null) {
        $socios_icase[] = [
            'id' => $socio['id'],
            'nombre' => $socio['nombre_empresa'],
            'icase' => $icase
        ];
    }
}

// Comparativa por ítems
$items_result = $db->query("SELECT id, nombre FROM items ORDER BY orden ASC");
$comparativa_items = [];
while ($item = $items_result->fetch_assoc()) {
    $item_data = [
        'item_id' => $item['id'],
        'item_nombre' => $item['nombre'],
        'socios' => []
    ];

    foreach ($socios_icase as $socio) {
        $porcentajes = $calculator->obtenerPorcentajesItemsMensual($socio['id'], $mes, $anio);
        foreach ($porcentajes as $p) {
            if ($p['item_id'] == $item['id'] && !$p['es_na']) {
                $item_data['socios'][] = [
                    'nombre' => $socio['nombre'],
                    'porcentaje' => $p['porcentaje']
                ];
                break;
            }
        }
    }

    if (!empty($item_data['socios'])) {
        $comparativa_items[] = $item_data;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Facilitador - ICASE</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="dashboard">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>ICASE</h2>
                <p>Facilitador</p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="#" class="active">Dashboard</a></li>
                <li><a href="../inspecciones/todas.php">Todas las Inspecciones</a></li>
                <li><a href="../observaciones/todas.php">Todas las Observaciones</a></li>
                <li><a href="../reportes/index.php">Reportes</a></li>
                <li><a href="../login/logout.php">Cerrar Sesión</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="top-bar">
                <h1>Dashboard Facilitador</h1>
                <div class="user-info">
                    <div class="user-avatar">F</div>
                    <span>Facilitador</span>
                </div>
            </div>

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

            <div class="stats-grid">
                <div class="stat-card">
                    <h4><?php echo $total_inspecciones; ?></h4>
                    <p>Inspecciones del Mes</p>
                </div>
                <div class="stat-card danger">
                    <h4><?php echo $obs_stats['vencidas']; ?></h4>
                    <p>Observaciones Vencidas</p>
                </div>
                <div class="stat-card warning">
                    <h4><?php echo $obs_stats['en_plazo']; ?></h4>
                    <p>Observaciones en Plazo</p>
                </div>
                <div class="stat-card success">
                    <h4><?php echo $obs_stats['completadas']; ?></h4>
                    <p>Observaciones Completadas</p>
                </div>
            </div>

            <?php if (!empty($socios_icase)): ?>
            <div class="card">
                <div class="card-header">
                    <h3>Comparativa ICASE - <?php echo obtenerNombreMes($mes) . ' ' . $anio; ?></h3>
                </div>
                <div class="card-body">
                    <div class="chart-container chart-icase-compare">
                        <canvas id="chartIcase"></canvas>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Comparativas por ítem -->
            <?php foreach ($comparativa_items as $index => $item_comp): ?>
            <?php if (!empty($item_comp['socios'])): ?>
            <div class="card">
                <div class="card-header">
                    <h3>Comparativa: <?php echo htmlspecialchars($item_comp['item_nombre']); ?></h3>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="chartItem<?php echo $index; ?>"></canvas>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <?php endforeach; ?>
        </main>
    </div>

    <script src="../../assets/js/main.js"></script>
    <script>
        // Gráfico comparativo ICASE (con colores dinámicos)
        <?php if (!empty($socios_icase)): ?>
        const icaseData = [<?php echo implode(',', array_column($socios_icase, 'icase')); ?>];
        const icaseColors = getColorsArrayByPerformance(icaseData);

        const ctxIcase = document.getElementById('chartIcase').getContext('2d');
        new Chart(ctxIcase, {
            type: 'bar',
            data: {
                labels: [<?php echo "'" . implode("','", array_column($socios_icase, 'nombre')) . "'"; ?>],
                datasets: [{
                    label: 'ICASE',
                    data: icaseData,
                    backgroundColor: icaseColors.backgrounds,
                    borderColor: icaseColors.borders,
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
                            callback: function(value) { return value + '%'; }
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

        // Gráficos por ítem (con colores dinámicos)
        <?php foreach ($comparativa_items as $index => $item_comp): ?>
        <?php if (!empty($item_comp['socios'])): ?>
        (function() {
            const itemData<?php echo $index; ?> = [<?php echo implode(',', array_column($item_comp['socios'], 'porcentaje')); ?>];
            const itemColors<?php echo $index; ?> = getColorsArrayByPerformance(itemData<?php echo $index; ?>);

            new Chart(document.getElementById('chartItem<?php echo $index; ?>').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: [<?php echo "'" . implode("','", array_column($item_comp['socios'], 'nombre')) . "'"; ?>],
                    datasets: [{
                        label: 'Porcentaje',
                        data: itemData<?php echo $index; ?>,
                        backgroundColor: itemColors<?php echo $index; ?>.backgrounds,
                        borderColor: itemColors<?php echo $index; ?>.borders,
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
                            ticks: { callback: function(value) { return value + '%'; } }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Cumplimiento: ' + context.parsed.y + '%';
                                }
                            }
                        }
                    }
                }
            });
        })();
        <?php endif; ?>
        <?php endforeach; ?>
    </script>
</body>
</html>
