<?php
/**
 * Reporte Mensual de Inspecciones
 * Muestra gráficos de ICASE y cumplimiento por ítem del mes
 */

// Mes y año actual
$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : obtenerMesActual();
$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : obtenerAnioActual();

// Calcular ICASE mensual para todos los socios
$calculator->calcularIcaseMensualParaTodos($mes, $anio);

// Obtener datos de ICASE mensual por socio
$datos_icase = [];
foreach ($socios as $socio) {
    $socio_id = $socio['id'];
    $nombre_empresa = $socio['nombre_empresa'];

    // Obtener ICASE mensual
    $sql = "SELECT icase FROM icase_mensual WHERE socio_id = ? AND mes = ? AND anio = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("iii", $socio_id, $mes, $anio);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if ($result && $result['icase'] !== null) {
        $datos_icase[] = [
            'socio' => $nombre_empresa,
            'icase' => round($result['icase'], 2)
        ];
    }
}

// Ordenar por ICASE (descendente)
usort($datos_icase, function($a, $b) {
    return $b['icase'] - $a['icase'];
});

// Obtener datos de cumplimiento por ítem (promedio de todos los socios)
$items_result = $db->query("SELECT id, nombre FROM items ORDER BY id ASC");
$items = [];
while ($item = $items_result->fetch_assoc()) {
    $items[] = $item;
}

$datos_items = [];
foreach ($items as $item) {
    $item_id = $item['id'];
    $nombre_item = $item['nombre'];

    // Calcular promedio de todos los socios para este ítem
    $sql = "
        SELECT AVG(porcentaje) as promedio
        FROM porcentajes_items_mensual
        WHERE item_id = ? AND mes = ? AND anio = ?
    ";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("iii", $item_id, $mes, $anio);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if ($result && $result['promedio'] !== null) {
        $datos_items[] = [
            'item' => $nombre_item,
            'porcentaje' => round($result['promedio'], 2)
        ];
    }
}

// Contar inspecciones del mes
$sql_count = "
    SELECT COUNT(*) as total
    FROM inspecciones
    WHERE MONTH(fecha_inspeccion) = ? AND YEAR(fecha_inspeccion) = ?
";
$stmt = $db->prepare($sql_count);
$stmt->bind_param("ii", $mes, $anio);
$stmt->execute();
$total_inspecciones = $stmt->get_result()->fetch_assoc()['total'];
?>

<div class="card">
    <div class="card-header">
        <h3>📈 Reporte Mensual de Inspecciones</h3>
    </div>
    <div class="card-body">
        <!-- Selector de Mes -->
        <div style="margin-bottom: 25px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
            <form method="GET" style="display: flex; gap: 15px; align-items: end;">
                <input type="hidden" name="tipo" value="mensual">
                <div class="form-group" style="flex: 1; margin: 0;">
                    <label style="font-weight: bold;">Mes:</label>
                    <select name="mes" class="form-control">
                        <?php for($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $m == $mes ? 'selected' : ''; ?>>
                                <?php echo obtenerNombreMes($m); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group" style="flex: 1; margin: 0;">
                    <label style="font-weight: bold;">Año:</label>
                    <select name="anio" class="form-control">
                        <?php for($a = 2024; $a <= 2030; $a++): ?>
                            <option value="<?php echo $a; ?>" <?php echo $a == $anio ? 'selected' : ''; ?>>
                                <?php echo $a; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Ver Reporte</button>
                <a href="?tipo=mensual" class="btn btn-secondary">Mes Actual</a>
            </form>

            <div style="margin-top: 15px; padding: 10px; background: #fff; border-left: 4px solid #3498db; border-radius: 4px;">
                <strong>Período del Reporte:</strong> <?php echo obtenerNombreMes($mes) . ' ' . $anio; ?>
                <span style="margin-left: 15px; color: #7f8c8d;">
                    (<?php echo $total_inspecciones; ?> inspecciones registradas)
                </span>
            </div>
        </div>

        <?php if (count($datos_icase) > 0): ?>

            <!-- Gráfico de ICASE por Socio -->
            <div style="margin-bottom: 40px;">
                <h4 style="margin-bottom: 20px; color: #2c3e50;">📊 ICASE por Socio Estratégico</h4>
                <div class="chart-container" style="position: relative; height: <?php echo max(400, count($datos_icase) * 60); ?>px;">
                    <canvas id="chartIcase"></canvas>
                </div>

                <!-- Tabla de ICASE -->
                <div style="margin-top: 25px;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #34495e; color: white;">
                                <th style="padding: 12px; text-align: left; border: 1px solid #ddd;">Socio Estratégico</th>
                                <th style="padding: 12px; text-align: center; border: 1px solid #ddd;">ICASE (%)</th>
                                <th style="padding: 12px; text-align: center; border: 1px solid #ddd;">Desempeño</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($datos_icase as $dato): ?>
                                <?php
                                    $icase = $dato['icase'];
                                    if ($icase >= 90) {
                                        $color = '#27ae60';
                                        $bg = '#d4edda';
                                        $desempeno = 'Excelente';
                                    } elseif ($icase >= 70) {
                                        $color = '#f39c12';
                                        $bg = '#fff3cd';
                                        $desempeno = 'Bueno';
                                    } else {
                                        $color = '#e74c3c';
                                        $bg = '#f8d7da';
                                        $desempeno = 'Requiere Mejora';
                                    }
                                ?>
                                <tr style="border-bottom: 1px solid #ddd;">
                                    <td style="padding: 12px; border: 1px solid #ddd; font-weight: bold;">
                                        <?php echo htmlspecialchars($dato['socio']); ?>
                                    </td>
                                    <td style="padding: 12px; border: 1px solid #ddd; text-align: center; background: <?php echo $bg; ?>;">
                                        <strong style="color: <?php echo $color; ?>; font-size: 1.2em;">
                                            <?php echo number_format($icase, 2); ?>%
                                        </strong>
                                    </td>
                                    <td style="padding: 12px; border: 1px solid #ddd; text-align: center;">
                                        <span class="badge" style="background: <?php echo $color; ?>; color: white; padding: 5px 15px; border-radius: 15px;">
                                            <?php echo $desempeno; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if (count($datos_items) > 0): ?>
                <!-- Gráfico de Cumplimiento por Ítem -->
                <div style="margin-top: 50px;">
                    <h4 style="margin-bottom: 20px; color: #2c3e50;">📋 Cumplimiento Promedio por Ítem</h4>
                    <div class="chart-container" style="position: relative; height: <?php echo max(450, count($datos_items) * 50); ?>px;">
                        <canvas id="chartItems"></canvas>
                    </div>

                    <!-- Tabla de Items -->
                    <div style="margin-top: 25px;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: #34495e; color: white;">
                                    <th style="padding: 12px; text-align: left; border: 1px solid #ddd;">Ítem</th>
                                    <th style="padding: 12px; text-align: center; border: 1px solid #ddd;">Cumplimiento (%)</th>
                                    <th style="padding: 12px; text-align: center; border: 1px solid #ddd;">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($datos_items as $dato): ?>
                                    <?php
                                        $porcentaje = $dato['porcentaje'];
                                        if ($porcentaje >= 90) {
                                            $color = '#27ae60';
                                            $bg = '#d4edda';
                                            $estado = 'Excelente';
                                        } elseif ($porcentaje >= 70) {
                                            $color = '#f39c12';
                                            $bg = '#fff3cd';
                                            $estado = 'Bueno';
                                        } else {
                                            $color = '#e74c3c';
                                            $bg = '#f8d7da';
                                            $estado = 'Requiere Atención';
                                        }
                                    ?>
                                    <tr style="border-bottom: 1px solid #ddd;">
                                        <td style="padding: 12px; border: 1px solid #ddd; font-weight: bold;">
                                            <?php echo htmlspecialchars($dato['item']); ?>
                                        </td>
                                        <td style="padding: 12px; border: 1px solid #ddd; text-align: center; background: <?php echo $bg; ?>;">
                                            <strong style="color: <?php echo $color; ?>; font-size: 1.2em;">
                                                <?php echo number_format($porcentaje, 2); ?>%
                                            </strong>
                                        </td>
                                        <td style="padding: 12px; border: 1px solid #ddd; text-align: center;">
                                            <span class="badge" style="background: <?php echo $color; ?>; color: white; padding: 5px 15px; border-radius: 15px;">
                                                <?php echo $estado; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="alert alert-info">
                No hay datos de inspecciones para <?php echo obtenerNombreMes($mes) . ' ' . $anio; ?>.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (count($datos_icase) > 0): ?>
<script>
// Función para obtener colores por desempeño
function getColorByPerformance(value) {
    if (value >= 90) {
        return {
            bg: 'rgba(46, 204, 113, 0.7)',    // Verde
            border: 'rgba(46, 204, 113, 1)'
        };
    } else if (value >= 70) {
        return {
            bg: 'rgba(243, 156, 18, 0.7)',    // Amarillo
            border: 'rgba(243, 156, 18, 1)'
        };
    } else {
        return {
            bg: 'rgba(231, 76, 60, 0.7)',     // Rojo
            border: 'rgba(231, 76, 60, 1)'
        };
    }
}

// Preparar datos para gráfico de ICASE
const sociosIcase = <?php echo json_encode(array_column($datos_icase, 'socio')); ?>;
const icaseValues = <?php echo json_encode(array_column($datos_icase, 'icase')); ?>;

// Generar colores dinámicos para ICASE
const coloresIcase = icaseValues.map(value => getColorByPerformance(value));

// Crear gráfico de ICASE
const ctxIcase = document.getElementById('chartIcase').getContext('2d');
new Chart(ctxIcase, {
    type: 'bar',
    data: {
        labels: sociosIcase,
        datasets: [{
            label: 'ICASE (%)',
            data: icaseValues,
            backgroundColor: coloresIcase.map(c => c.bg),
            borderColor: coloresIcase.map(c => c.border),
            borderWidth: 2
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            title: {
                display: true,
                text: 'Índice de Cumplimiento Ambiental por Socio Estratégico (ICASE)',
                font: {
                    size: 16,
                    weight: 'bold'
                },
                padding: {
                    top: 10,
                    bottom: 20
                }
            },
            legend: {
                display: false
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return 'ICASE: ' + context.parsed.x.toFixed(2) + '%';
                    }
                }
            }
        },
        scales: {
            x: {
                beginAtZero: true,
                max: 100,
                ticks: {
                    callback: function(value) {
                        return value + '%';
                    },
                    font: {
                        size: 12
                    }
                },
                title: {
                    display: true,
                    text: 'Porcentaje de Cumplimiento',
                    font: {
                        size: 14,
                        weight: 'bold'
                    }
                }
            },
            y: {
                ticks: {
                    font: {
                        size: 13,
                        weight: 'bold'
                    }
                }
            }
        }
    }
});

<?php if (count($datos_items) > 0): ?>
// Preparar datos para gráfico de Items
const nombresItems = <?php echo json_encode(array_column($datos_items, 'item')); ?>;
const porcentajesItems = <?php echo json_encode(array_column($datos_items, 'porcentaje')); ?>;

// Generar colores dinámicos para Items
const coloresItems = porcentajesItems.map(value => getColorByPerformance(value));

// Crear gráfico de Items
const ctxItems = document.getElementById('chartItems').getContext('2d');
new Chart(ctxItems, {
    type: 'bar',
    data: {
        labels: nombresItems,
        datasets: [{
            label: 'Cumplimiento (%)',
            data: porcentajesItems,
            backgroundColor: coloresItems.map(c => c.bg),
            borderColor: coloresItems.map(c => c.border),
            borderWidth: 2
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            title: {
                display: true,
                text: 'Cumplimiento Promedio por Ítem',
                font: {
                    size: 16,
                    weight: 'bold'
                },
                padding: {
                    top: 10,
                    bottom: 20
                }
            },
            legend: {
                display: false
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return 'Cumplimiento: ' + context.parsed.x.toFixed(2) + '%';
                    }
                }
            }
        },
        scales: {
            x: {
                beginAtZero: true,
                max: 100,
                ticks: {
                    callback: function(value) {
                        return value + '%';
                    },
                    font: {
                        size: 12
                    }
                },
                title: {
                    display: true,
                    text: 'Porcentaje de Cumplimiento',
                    font: {
                        size: 14,
                        weight: 'bold'
                    }
                }
            },
            y: {
                ticks: {
                    font: {
                        size: 12,
                        weight: 'bold'
                    }
                }
            }
        }
    }
});
<?php endif; ?>
</script>
<?php endif; ?>
