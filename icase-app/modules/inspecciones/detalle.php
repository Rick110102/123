<?php
/**
 * Detalle Completo de Inspección
 * Muestra toda la información de una inspección específica
 */
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/icase_calculator.php';

protegerSocio();

$db = Database::getInstance();
$calculator = new IcaseCalculator();
$socio_id = obtenerSocioId();

// Obtener ID de inspección
$inspeccion_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$inspeccion_id) {
    redirigir(BASE_URL . 'modules/inspecciones/recibidas.php');
}

// Verificar que la inspección pertenece al socio logueado
$stmt = $db->prepare("
    SELECT
        i.id,
        i.fecha_inspeccion,
        i.nombre_inspector,
        i.facilitador_nombre,
        i.lugar,
        i.fecha_registro,
        si.nombre_empresa as inspector_empresa,
        so.nombre_empresa as inspeccionado_empresa
    FROM inspecciones i
    INNER JOIN socios si ON si.id = i.socio_inspector_id
    INNER JOIN socios so ON so.id = i.socio_inspeccionado_id
    WHERE i.id = ? AND i.socio_inspeccionado_id = ?
");
$stmt->bind_param("ii", $inspeccion_id, $socio_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    redirigir(BASE_URL . 'modules/inspecciones/recibidas.php');
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
        o.foto_url,
        o.descripcion,
        o.tipo,
        o.fecha_registro,
        o.fecha_vencimiento,
        o.estado,
        o.es_nula,
        l.id as levantamiento_id,
        l.foto_url as lev_foto_url,
        l.descripcion as lev_descripcion,
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
                <p><?php echo htmlspecialchars($_SESSION['nombre_usuario']); ?></p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="../dashboard/socio.php">Dashboard</a></li>
                <li><a href="registrar.php">Registrar Inspección</a></li>
                <li><a href="recibidas.php" class="active">Inspecciones Recibidas</a></li>
                <li><a href="../login/logout.php">Cerrar Sesión</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="top-bar">
                <h1>Detalle de Inspección</h1>
                <a href="recibidas.php" class="btn btn-secondary">← Volver a Inspecciones</a>
            </div>

            <!-- INFORMACIÓN GENERAL -->
            <div class="card">
                <div class="card-header">
                    <h3>Información General</h3>
                </div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div>
                            <p><strong>Fecha de Inspección:</strong> <?php echo formatearFecha($inspeccion['fecha_inspeccion']); ?></p>
                            <p><strong>Inspector:</strong> <?php echo htmlspecialchars($inspeccion['nombre_inspector']); ?></p>
                            <p><strong>Empresa Inspectora:</strong> <?php echo htmlspecialchars($inspeccion['inspector_empresa']); ?></p>
                        </div>
                        <div>
                            <p><strong>Facilitador:</strong> <?php echo htmlspecialchars($inspeccion['facilitador_nombre']); ?></p>
                            <p><strong>Lugar/Frente:</strong> <?php echo htmlspecialchars($inspeccion['lugar']); ?></p>
                            <p><strong>Registrada el:</strong> <?php echo formatearFecha($inspeccion['fecha_registro']); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RESULTADOS POR ÍTEM -->
            <div class="card">
                <div class="card-header">
                    <h3>Resultados por Ítem</h3>
                    <p style="font-size: 0.9em; margin-top: 5px; color: #7f8c8d;">
                        Nota: El ICASE es un indicador mensual consolidado, no se calcula por inspección individual.
                    </p>
                </div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                        <?php foreach ($porcentajes_items as $item): ?>
                            <div style="padding: 15px; border: 1px solid var(--border-color); border-radius: 5px; text-align: center;">
                                <h4 style="margin: 0 0 10px 0; font-size: 0.9em;"><?php echo htmlspecialchars($item['nombre']); ?></h4>
                                <?php if ($item['es_na']): ?>
                                    <p style="font-size: 2em; margin: 0; color: #95a5a6;">N.A.</p>
                                <?php else: ?>
                                    <p style="font-size: 2.5em; margin: 0; font-weight: bold; color: var(--<?php echo obtenerClaseIcase($item['porcentaje']); ?>-color);">
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
                    <h4 style="color: var(--primary-color); margin-bottom: 15px;">CONTROLES GENERALES</h4>
                    <?php foreach ($items_checklist as $item_id => $item): ?>
                        <?php if ($item['es_general']): ?>
                            <div style="margin-bottom: 30px;">
                                <h5 style="background-color: var(--light-bg); padding: 10px; border-left: 4px solid var(--secondary-color);">
                                    <?php echo htmlspecialchars($item['nombre']); ?>
                                </h5>
                                <table style="width: 100%; margin-top: 10px;">
                                    <thead>
                                        <tr>
                                            <th style="text-align: left;">Pregunta</th>
                                            <th style="text-align: center; width: 100px;">Respuesta</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($item['preguntas'] as $pregunta): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($pregunta['texto']); ?></td>
                                                <td style="text-align: center;">
                                                    <span class="badge badge-<?php
                                                        if ($pregunta['valor'] == '1') echo 'success';
                                                        elseif ($pregunta['valor'] == '0.5') echo 'warning';
                                                        elseif ($pregunta['valor'] == '0') echo 'danger';
                                                        else echo 'info';
                                                    ?>">
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

                    <h4 style="color: var(--primary-color); margin: 30px 0 15px;">CONTROLES ESPECÍFICOS</h4>
                    <?php foreach ($items_checklist as $item_id => $item): ?>
                        <?php if (!$item['es_general']): ?>
                            <div style="margin-bottom: 30px;">
                                <h5 style="background-color: var(--light-bg); padding: 10px; border-left: 4px solid var(--secondary-color);">
                                    <?php echo htmlspecialchars($item['nombre']); ?>
                                </h5>
                                <table style="width: 100%; margin-top: 10px;">
                                    <thead>
                                        <tr>
                                            <th style="text-align: left;">Pregunta</th>
                                            <th style="text-align: center; width: 100px;">Respuesta</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($item['preguntas'] as $pregunta): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($pregunta['texto']); ?></td>
                                                <td style="text-align: center;">
                                                    <span class="badge badge-<?php
                                                        if ($pregunta['valor'] == '1') echo 'success';
                                                        elseif ($pregunta['valor'] == '0.5') echo 'warning';
                                                        elseif ($pregunta['valor'] == '0') echo 'danger';
                                                        else echo 'info';
                                                    ?>">
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
                    <h3>Observaciones Registradas (<?php echo $observaciones->num_rows; ?>)</h3>
                </div>
                <div class="card-body">
                    <?php if ($observaciones->num_rows > 0): ?>
                        <?php $contador = 1; ?>
                        <?php while ($obs = $observaciones->fetch_assoc()): ?>
                            <div class="observacion-item">
                                <div class="observacion-header">
                                    <h4>Observación #<?php echo $contador++; ?></h4>
                                    <span class="badge badge-<?php echo obtenerClaseEstado($obs['estado']); ?>">
                                        <?php echo strtoupper($obs['estado']); ?>
                                    </span>
                                </div>

                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px;">
                                    <div>
                                        <p><strong>Tipo:</strong> <?php echo $obs['tipo'] ? 'Tipo ' . $obs['tipo'] : 'Ninguno'; ?></p>
                                        <p><strong>Fecha de registro:</strong> <?php echo formatearFecha($obs['fecha_registro']); ?></p>
                                        <p><strong>Fecha de vencimiento:</strong> <?php echo formatearFecha($obs['fecha_vencimiento']); ?></p>
                                        <?php if ($obs['es_nula']): ?>
                                            <p style="color: var(--danger-color);"><strong>Estado:</strong> Declarada NULA por facilitador</p>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <img src="<?php echo htmlspecialchars($obs['foto_url']); ?>" class="observacion-imagen" alt="Foto de observación">
                                    </div>
                                </div>

                                <p style="margin-top: 15px;"><strong>Descripción:</strong></p>
                                <p><?php echo nl2br(htmlspecialchars($obs['descripcion'])); ?></p>

                                <?php if ($obs['levantamiento_id']): ?>
                                    <div style="background-color: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px; padding: 15px; margin-top: 15px;">
                                        <h5 style="color: #155724; margin: 0 0 10px 0;">✓ Observación Levantada</h5>
                                        <p><strong>Fecha de levantamiento:</strong> <?php echo formatearFecha($obs['fecha_levantamiento']); ?></p>
                                        <p><strong>Descripción:</strong> <?php echo nl2br(htmlspecialchars($obs['lev_descripcion'])); ?></p>
                                        <img src="<?php echo htmlspecialchars($obs['lev_foto_url']); ?>" style="max-width: 300px; margin-top: 10px;" alt="Foto de levantamiento">
                                    </div>
                                <?php elseif ($obs['estado'] != 'completada' && !$obs['es_nula']): ?>
                                    <div style="margin-top: 15px;">
                                        <a href="../observaciones/levantar.php?id=<?php echo $obs['id']; ?>" class="btn btn-success">
                                            Levantar Observación
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p style="text-align: center; color: #7f8c8d; padding: 20px;">
                            No se registraron observaciones en esta inspección.
                        </p>
                    <?php endif; ?>
                </div>
            </div>

        </main>
    </div>

    <script src="../../assets/js/main.js"></script>
</body>
</html>
