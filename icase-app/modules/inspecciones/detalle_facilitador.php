<?php
/**
 * Detalle Completo de Inspección - Vista Facilitador
 * Muestra toda la información de una inspección específica para el facilitador
 */
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/icase_calculator.php';

protegerFacilitador();

$db = Database::getInstance();
$calculator = new IcaseCalculator();

// Obtener ID de inspección
$inspeccion_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$inspeccion_id) {
    redirigir(BASE_URL . 'modules/inspecciones/todas.php');
}

// Obtener información de la inspección
$stmt = $db->prepare("
    SELECT
        i.id,
        i.fecha_inspeccion,
        i.nombre_inspector,
        i.facilitador_nombre,
        i.lugar,
        i.fecha_registro,
        i.socio_inspector_id,
        i.socio_inspeccionado_id,
        si.nombre_empresa as inspector_empresa,
        so.nombre_empresa as inspeccionado_empresa
    FROM inspecciones i
    INNER JOIN socios si ON si.id = i.socio_inspector_id
    INNER JOIN socios so ON so.id = i.socio_inspeccionado_id
    WHERE i.id = ?
");
$stmt->bind_param("i", $inspeccion_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    redirigir(BASE_URL . 'modules/inspecciones/todas.php');
}

$inspeccion = $result->fetch_assoc();

// Obtener porcentajes por ítem de esta inspección
$porcentajes_items = [];
$stmt = $db->prepare("
    SELECT i.nombre, p.porcentaje, p.es_na
    FROM porcentajes_items_inspeccion p
    INNER JOIN items i ON i.id = p.item_id
    WHERE p.inspeccion_id = ?
    ORDER BY i.orden ASC
");
$stmt->bind_param("i", $inspeccion_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $porcentajes_items[] = $row;
}

// Obtener todas las respuestas del checklist organizadas por ítem
$stmt = $db->prepare("
    SELECT
        i.id as item_id,
        i.nombre as item_nombre,
        i.es_general,
        p.id as pregunta_id,
        p.texto_pregunta,
        p.orden,
        r.valor
    FROM items i
    INNER JOIN preguntas p ON p.item_id = i.id
    LEFT JOIN respuestas_checklist r ON r.pregunta_id = p.id AND r.inspeccion_id = ?
    ORDER BY i.orden ASC, p.orden ASC
");
$stmt->bind_param("i", $inspeccion_id);
$stmt->execute();
$result = $stmt->get_result();

$items_checklist = [];
while ($row = $result->fetch_assoc()) {
    $item_id = $row['item_id'];
    if (!isset($items_checklist[$item_id])) {
        $items_checklist[$item_id] = [
            'nombre' => $row['item_nombre'],
            'es_general' => $row['es_general'],
            'preguntas' => []
        ];
    }
    $items_checklist[$item_id]['preguntas'][] = [
        'id' => $row['pregunta_id'],
        'texto' => $row['texto_pregunta'],
        'valor' => $row['valor'] ?? 'NA'
    ];
}

// Obtener observaciones de esta inspección
$stmt = $db->prepare("
    SELECT
        o.id,
        o.evidencia_foto,
        o.descripcion,
        o.tipo,
        o.fecha_registro,
        o.fecha_vencimiento,
        o.estado,
        o.es_nula,
        l.id as levantamiento_id,
        l.evidencia_foto as lev_foto,
        l.descripcion_levantamiento as lev_descripcion,
        l.fecha_levantamiento
    FROM observaciones o
    LEFT JOIN levantamientos l ON l.observacion_id = o.id
    WHERE o.inspeccion_id = ?
    ORDER BY o.fecha_registro ASC
");
$stmt->bind_param("i", $inspeccion_id);
$stmt->execute();
$observaciones = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle de Inspección - ICASE</title>
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
                <li><a href="todas.php" class="active">Todas las Inspecciones</a></li>
                <li><a href="../observaciones/todas.php">Todas las Observaciones</a></li>
                <li><a href="../reportes/index.php">Reportes</a></li>
                <li><a href="../login/logout.php">Cerrar Sesión</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="top-bar">
                <h1>📋 Detalle de Inspección #<?php echo $inspeccion_id; ?></h1>
                <div style="display: flex; gap: 10px;">
                    <a href="editar.php?id=<?php echo $inspeccion_id; ?>" class="btn btn-warning">✏️ Editar</a>
                    <a href="todas.php" class="btn btn-secondary">← Volver</a>
                </div>
            </div>

            <!-- INFORMACIÓN GENERAL -->
            <div class="card">
                <div class="card-header">
                    <h3>Información General</h3>
                </div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div style="padding: 15px; background: #f8f9fa; border-radius: 8px;">
                            <h4 style="margin-top: 0; color: #3498db;">Inspector</h4>
                            <p><strong>Nombre:</strong> <?php echo htmlspecialchars($inspeccion['nombre_inspector']); ?></p>
                            <p><strong>Empresa:</strong> <?php echo htmlspecialchars($inspeccion['inspector_empresa']); ?></p>
                        </div>
                        <div style="padding: 15px; background: #f8f9fa; border-radius: 8px;">
                            <h4 style="margin-top: 0; color: #e74c3c;">Inspeccionado</h4>
                            <p><strong>Empresa:</strong> <?php echo htmlspecialchars($inspeccion['inspeccionado_empresa']); ?></p>
                        </div>
                    </div>
                    <div style="margin-top: 20px; padding: 15px; background: #fff; border: 1px solid #ddd; border-radius: 8px;">
                        <p><strong>Fecha de Inspección:</strong> <?php echo formatearFecha($inspeccion['fecha_inspeccion']); ?></p>
                        <p><strong>Facilitador:</strong> <?php echo htmlspecialchars($inspeccion['facilitador_nombre']); ?></p>
                        <p><strong>Lugar/Frente:</strong> <?php echo htmlspecialchars($inspeccion['lugar']); ?></p>
                        <p style="margin-bottom: 0;"><strong>Registrada el:</strong> <?php echo formatearFecha($inspeccion['fecha_registro']); ?></p>
                    </div>
                </div>
            </div>

            <!-- RESULTADOS POR ÍTEM -->
            <div class="card">
                <div class="card-header">
                    <h3>Resultados por Ítem</h3>
                    <p style="font-size: 0.9em; margin-top: 5px; color: #7f8c8d;">
                        Porcentajes calculados para esta inspección individual. El ICASE es un indicador mensual consolidado.
                    </p>
                </div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                        <?php foreach ($porcentajes_items as $item): ?>
                            <?php
                                $porcentaje = $item['porcentaje'];
                                if ($porcentaje >= 90) {
                                    $color = '#27ae60';
                                    $bg = '#d4edda';
                                } elseif ($porcentaje >= 70) {
                                    $color = '#f39c12';
                                    $bg = '#fff3cd';
                                } else {
                                    $color = '#e74c3c';
                                    $bg = '#f8d7da';
                                }
                            ?>
                            <div style="padding: 20px; border: 2px solid <?php echo $color; ?>; border-radius: 8px; text-align: center; background: <?php echo $bg; ?>;">
                                <h4 style="margin: 0 0 10px 0; font-size: 0.95em; color: #2c3e50;">
                                    <?php echo htmlspecialchars($item['nombre']); ?>
                                </h4>
                                <?php if ($item['es_na']): ?>
                                    <p style="font-size: 2.5em; margin: 0; color: #95a5a6; font-weight: bold;">N.A.</p>
                                <?php else: ?>
                                    <p style="font-size: 3em; margin: 0; font-weight: bold; color: <?php echo $color; ?>;">
                                        <?php echo number_format($item['porcentaje'], 1); ?>%
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- RESPUESTAS DEL CHECKLIST -->
            <div class="card">
                <div class="card-header">
                    <h3>Respuestas del Checklist (28 preguntas)</h3>
                </div>
                <div class="card-body">
                    <h4 style="color: #3498db; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #3498db;">
                        📋 CONTROLES GENERALES
                    </h4>
                    <?php foreach ($items_checklist as $item_id => $item): ?>
                        <?php if ($item['es_general']): ?>
                            <div style="margin-bottom: 30px; border: 1px solid #ddd; border-radius: 8px; overflow: hidden;">
                                <h5 style="background-color: #3498db; color: white; padding: 12px; margin: 0;">
                                    <?php echo htmlspecialchars($item['nombre']); ?>
                                </h5>
                                <table style="width: 100%; margin: 0;">
                                    <thead>
                                        <tr style="background: #ecf0f1;">
                                            <th style="text-align: left; padding: 10px;">Pregunta</th>
                                            <th style="text-align: center; width: 120px; padding: 10px;">Respuesta</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($item['preguntas'] as $pregunta): ?>
                                            <tr style="border-bottom: 1px solid #eee;">
                                                <td style="padding: 10px;"><?php echo htmlspecialchars($pregunta['texto']); ?></td>
                                                <td style="text-align: center; padding: 10px;">
                                                    <span class="badge badge-<?php
                                                        if ($pregunta['valor'] == '1') echo 'success';
                                                        elseif ($pregunta['valor'] == '0.5') echo 'warning';
                                                        elseif ($pregunta['valor'] == '0') echo 'danger';
                                                        else echo 'info';
                                                    ?>" style="font-size: 1.1em; padding: 6px 12px;">
                                                        <?php echo $pregunta['valor']; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <h4 style="color: #e67e22; margin: 40px 0 15px; padding-bottom: 10px; border-bottom: 2px solid #e67e22;">
                        🔧 CONTROLES ESPECÍFICOS
                    </h4>
                    <?php foreach ($items_checklist as $item_id => $item): ?>
                        <?php if (!$item['es_general']): ?>
                            <div style="margin-bottom: 30px; border: 1px solid #ddd; border-radius: 8px; overflow: hidden;">
                                <h5 style="background-color: #e67e22; color: white; padding: 12px; margin: 0;">
                                    <?php echo htmlspecialchars($item['nombre']); ?>
                                </h5>
                                <table style="width: 100%; margin: 0;">
                                    <thead>
                                        <tr style="background: #ecf0f1;">
                                            <th style="text-align: left; padding: 10px;">Pregunta</th>
                                            <th style="text-align: center; width: 120px; padding: 10px;">Respuesta</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($item['preguntas'] as $pregunta): ?>
                                            <tr style="border-bottom: 1px solid #eee;">
                                                <td style="padding: 10px;"><?php echo htmlspecialchars($pregunta['texto']); ?></td>
                                                <td style="text-align: center; padding: 10px;">
                                                    <span class="badge badge-<?php
                                                        if ($pregunta['valor'] == '1') echo 'success';
                                                        elseif ($pregunta['valor'] == '0.5') echo 'warning';
                                                        elseif ($pregunta['valor'] == '0') echo 'danger';
                                                        else echo 'info';
                                                    ?>" style="font-size: 1.1em; padding: 6px 12px;">
                                                        <?php echo $pregunta['valor']; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- OBSERVACIONES -->
            <div class="card">
                <div class="card-header">
                    <h3>📸 Observaciones Registradas (<?php echo $observaciones->num_rows; ?>)</h3>
                </div>
                <div class="card-body">
                    <?php if ($observaciones->num_rows > 0): ?>
                        <?php $contador = 1; ?>
                        <?php while ($obs = $observaciones->fetch_assoc()): ?>
                            <div style="margin-bottom: 30px; border: 1px solid #ddd; border-radius: 8px; padding: 20px; background: #fff;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0;">
                                    <h4 style="margin: 0;">📋 Observación #<?php echo $contador++; ?></h4>
                                    <div>
                                        <span class="badge badge-<?php echo obtenerClaseEstado($obs['estado']); ?>" style="font-size: 1em; padding: 8px 15px;">
                                            <?php echo strtoupper($obs['estado']); ?>
                                        </span>
                                        <?php if ($obs['es_nula']): ?>
                                            <span class="badge badge-danger" style="font-size: 1em; padding: 8px 15px; margin-left: 5px;">NULA</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                    <div>
                                        <p><strong>Tipo:</strong> <?php echo $obs['tipo'] ? 'Tipo ' . $obs['tipo'] : '<span style="color: #999;">Sin tipo asignado</span>'; ?></p>
                                        <p><strong>Fecha de registro:</strong> <?php echo formatearFecha($obs['fecha_registro']); ?></p>
                                        <p><strong>Fecha de vencimiento:</strong>
                                            <span style="color: <?php echo $obs['estado'] == 'vencida' ? '#e74c3c' : '#27ae60'; ?>; font-weight: bold;">
                                                <?php echo formatearFecha($obs['fecha_vencimiento']); ?>
                                            </span>
                                        </p>
                                        <p><strong>Descripción:</strong></p>
                                        <p style="padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">
                                            <?php echo nl2br(htmlspecialchars($obs['descripcion'])); ?>
                                        </p>
                                    </div>
                                    <div>
                                        <?php if ($obs['evidencia_foto']): ?>
                                            <strong>Evidencia Fotográfica:</strong>
                                            <a href="<?php echo BASE_URL . $obs['evidencia_foto']; ?>" target="_blank">
                                                <img src="<?php echo BASE_URL . $obs['evidencia_foto']; ?>"
                                                     style="width: 100%; max-width: 400px; height: auto; border-radius: 8px; border: 2px solid #ddd; margin-top: 10px; cursor: pointer;"
                                                     alt="Evidencia de observación">
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if ($obs['levantamiento_id']): ?>
                                    <div style="background-color: #d4edda; border: 2px solid #28a745; border-radius: 8px; padding: 20px; margin-top: 20px;">
                                        <h5 style="color: #155724; margin: 0 0 15px 0; font-size: 1.2em;">✅ Observación Levantada</h5>
                                        <p><strong>Fecha de levantamiento:</strong>
                                            <span style="color: #155724; font-weight: bold;">
                                                <?php echo formatearFecha($obs['fecha_levantamiento']); ?>
                                            </span>
                                        </p>
                                        <p><strong>Descripción del levantamiento:</strong></p>
                                        <p style="padding: 10px; background: #fff; border-left: 4px solid #28a745; border-radius: 4px;">
                                            <?php echo nl2br(htmlspecialchars($obs['lev_descripcion'])); ?>
                                        </p>
                                        <?php if ($obs['lev_foto']): ?>
                                            <strong>Evidencia del levantamiento:</strong>
                                            <a href="<?php echo BASE_URL . $obs['lev_foto']; ?>" target="_blank">
                                                <img src="<?php echo BASE_URL . $obs['lev_foto']; ?>"
                                                     style="max-width: 400px; height: auto; border-radius: 8px; border: 2px solid #28a745; margin-top: 10px; cursor: pointer;"
                                                     alt="Evidencia de levantamiento">
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div style="background: #fff3cd; border: 2px dashed #ffc107; border-radius: 8px; padding: 15px; margin-top: 20px; text-align: center;">
                                        <p style="margin: 0; color: #856404; font-weight: bold;">
                                            ⏳ Esta observación aún no ha sido levantada
                                        </p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="alert alert-info">
                            No se registraron observaciones en esta inspección.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </main>
    </div>

    <script src="../../assets/js/main.js"></script>
</body>
</html>
