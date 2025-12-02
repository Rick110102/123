<?php
/**
 * Reporte Semanal de Observaciones
 * Muestra observaciones en plazo vs vencidas por socio
 */

// Calcular fechas de la semana actual
$hoy = new DateTime();
$inicio_semana = clone $hoy;
$inicio_semana->modify('monday this week');
$fin_semana = clone $inicio_semana;
$fin_semana->modify('+6 days');

// Permitir selección de semana personalizada
if (isset($_GET['fecha_inicio'])) {
    $inicio_semana = new DateTime($_GET['fecha_inicio']);
    $fin_semana = clone $inicio_semana;
    $fin_semana->modify('+6 days');
}

$fecha_inicio_str = $inicio_semana->format('Y-m-d');
$fecha_fin_str = $fin_semana->format('Y-m-d');

// Obtener datos de observaciones por socio para la semana
$datos_observaciones = [];
foreach ($socios as $socio) {
    $socio_id = $socio['id'];
    $nombre_empresa = $socio['nombre_empresa'];

    // Contar observaciones EN PLAZO
    $sql_plazo = "
        SELECT COUNT(*) as total
        FROM observaciones o
        INNER JOIN inspecciones i ON i.id = o.inspeccion_id
        WHERE i.socio_inspeccionado_id = ?
        AND o.fecha_registro BETWEEN ? AND ?
        AND o.estado = 'en_plazo'
        AND o.es_nula = 0
    ";
    $stmt = $db->prepare($sql_plazo);
    $stmt->bind_param("iss", $socio_id, $fecha_inicio_str, $fecha_fin_str);
    $stmt->execute();
    $en_plazo = $stmt->get_result()->fetch_assoc()['total'];

    // Contar observaciones VENCIDAS
    $sql_vencidas = "
        SELECT COUNT(*) as total
        FROM observaciones o
        INNER JOIN inspecciones i ON i.id = o.inspeccion_id
        WHERE i.socio_inspeccionado_id = ?
        AND o.fecha_registro BETWEEN ? AND ?
        AND o.estado = 'vencida'
        AND o.es_nula = 0
    ";
    $stmt = $db->prepare($sql_vencidas);
    $stmt->bind_param("iss", $socio_id, $fecha_inicio_str, $fecha_fin_str);
    $stmt->execute();
    $vencidas = $stmt->get_result()->fetch_assoc()['total'];

    // Solo incluir socios con observaciones
    if ($en_plazo > 0 || $vencidas > 0) {
        $datos_observaciones[] = [
            'socio' => $nombre_empresa,
            'en_plazo' => $en_plazo,
            'vencida' => $vencidas,
            'total' => $en_plazo + $vencidas
        ];
    }
}

// Ordenar por total de observaciones (descendente)
usort($datos_observaciones, function($a, $b) {
    return $b['total'] - $a['total'];
});
?>

<div class="card">
    <div class="card-header">
        <h3>📊 Reporte Semanal de Observaciones Registradas</h3>
    </div>
    <div class="card-body">
        <!-- Selector de Semana -->
        <div style="margin-bottom: 25px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
            <form method="GET" style="display: flex; gap: 15px; align-items: end;">
                <input type="hidden" name="tipo" value="semanal">
                <div class="form-group" style="flex: 1; margin: 0;">
                    <label style="font-weight: bold;">Seleccionar Fecha de Inicio de Semana (Lunes):</label>
                    <input type="date" name="fecha_inicio" class="form-control"
                           value="<?php echo $fecha_inicio_str; ?>">
                </div>
                <button type="submit" class="btn btn-primary">Ver Semana</button>
                <a href="?tipo=semanal" class="btn btn-secondary">Semana Actual</a>
            </form>

            <div style="margin-top: 15px; padding: 10px; background: #fff; border-left: 4px solid #3498db; border-radius: 4px;">
                <strong>Período del Reporte:</strong>
                <?php echo $inicio_semana->format('d/m/Y'); ?> - <?php echo $fin_semana->format('d/m/Y'); ?>
                <span style="margin-left: 15px; color: #7f8c8d;">
                    (Semana <?php echo $inicio_semana->format('W'); ?> de <?php echo $inicio_semana->format('Y'); ?>)
                </span>
            </div>
        </div>

        <?php if (count($datos_observaciones) > 0): ?>
            <!-- Leyenda -->
            <div style="display: flex; justify-content: center; gap: 30px; margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 20px; height: 20px; background: #f39c12; border-radius: 4px;"></div>
                    <span style="font-weight: bold;">EN PLAZO</span>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 20px; height: 20px; background: #e74c3c; border-radius: 4px;"></div>
                    <span style="font-weight: bold;">VENCIDO</span>
                </div>
            </div>

            <!-- Gráfico -->
            <div class="chart-container" style="position: relative; height: <?php echo max(400, count($datos_observaciones) * 60); ?>px; margin: 25px 0;">
                <canvas id="chartObservaciones"></canvas>
            </div>

            <!-- Tabla de Datos -->
            <div style="margin-top: 30px;">
                <h4 style="margin-bottom: 15px;">📋 Detalle Numérico</h4>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #34495e; color: white;">
                            <th style="padding: 12px; text-align: left; border: 1px solid #ddd;">Socio Estratégico</th>
                            <th style="padding: 12px; text-align: center; border: 1px solid #ddd;">En Plazo</th>
                            <th style="padding: 12px; text-align: center; border: 1px solid #ddd;">Vencidas</th>
                            <th style="padding: 12px; text-align: center; border: 1px solid #ddd;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($datos_observaciones as $dato): ?>
                            <tr style="border-bottom: 1px solid #ddd;">
                                <td style="padding: 12px; border: 1px solid #ddd; font-weight: bold;">
                                    <?php echo htmlspecialchars($dato['socio']); ?>
                                </td>
                                <td style="padding: 12px; border: 1px solid #ddd; text-align: center; background: #fef5e7;">
                                    <strong style="color: #f39c12; font-size: 1.1em;">
                                        <?php echo $dato['en_plazo']; ?> OBS
                                    </strong>
                                </td>
                                <td style="padding: 12px; border: 1px solid #ddd; text-align: center; background: #fadbd8;">
                                    <strong style="color: #e74c3c; font-size: 1.1em;">
                                        <?php echo $dato['vencida']; ?> OBS
                                    </strong>
                                </td>
                                <td style="padding: 12px; border: 1px solid #ddd; text-align: center; background: #eaecee;">
                                    <strong style="font-size: 1.1em;">
                                        <?php echo $dato['total']; ?> OBS
                                    </strong>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background: #34495e; color: white; font-weight: bold;">
                            <td style="padding: 12px; border: 1px solid #ddd;">TOTALES</td>
                            <td style="padding: 12px; border: 1px solid #ddd; text-align: center;">
                                <?php echo array_sum(array_column($datos_observaciones, 'en_plazo')); ?> OBS
                            </td>
                            <td style="padding: 12px; border: 1px solid #ddd; text-align: center;">
                                <?php echo array_sum(array_column($datos_observaciones, 'vencida')); ?> OBS
                            </td>
                            <td style="padding: 12px; border: 1px solid #ddd; text-align: center;">
                                <?php echo array_sum(array_column($datos_observaciones, 'total')); ?> OBS
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

        <?php else: ?>
            <div class="alert alert-info">
                No se registraron observaciones durante esta semana (<?php echo $inicio_semana->format('d/m/Y'); ?> - <?php echo $fin_semana->format('d/m/Y'); ?>).
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (count($datos_observaciones) > 0): ?>
<script>
// Preparar datos para el gráfico
const socios = <?php echo json_encode(array_column($datos_observaciones, 'socio')); ?>;
const enPlazo = <?php echo json_encode(array_column($datos_observaciones, 'en_plazo')); ?>;
const vencidas = <?php echo json_encode(array_column($datos_observaciones, 'vencida')); ?>;

// Crear gráfico de barras horizontales
const ctx = document.getElementById('chartObservaciones').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: socios,
        datasets: [
            {
                label: 'EN PLAZO',
                data: enPlazo,
                backgroundColor: 'rgba(243, 156, 18, 0.8)',  // Amarillo
                borderColor: 'rgba(243, 156, 18, 1)',
                borderWidth: 2
            },
            {
                label: 'VENCIDO',
                data: vencidas,
                backgroundColor: 'rgba(231, 76, 60, 0.8)',  // Rojo
                borderColor: 'rgba(231, 76, 60, 1)',
                borderWidth: 2
            }
        ]
    },
    options: {
        indexAxis: 'y',  // Barras horizontales
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            title: {
                display: true,
                text: 'REPORTE DE OBSERVACIONES REGISTRADAS',
                font: {
                    size: 18,
                    weight: 'bold'
                },
                padding: {
                    top: 10,
                    bottom: 20
                }
            },
            legend: {
                display: true,
                position: 'top',
                labels: {
                    font: {
                        size: 14,
                        weight: 'bold'
                    },
                    padding: 15
                }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': ' + context.parsed.x + ' OBS';
                    }
                }
            }
        },
        scales: {
            x: {
                beginAtZero: true,
                stacked: false,
                ticks: {
                    stepSize: 1,
                    font: {
                        size: 12
                    }
                },
                title: {
                    display: true,
                    text: 'Número de Observaciones',
                    font: {
                        size: 14,
                        weight: 'bold'
                    }
                }
            },
            y: {
                stacked: false,
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
</script>
<?php endif; ?>
