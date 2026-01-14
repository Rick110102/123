<?php
/**
 * Editar Inspección - Vista Facilitador
 * Permite al facilitador editar las respuestas del checklist de una inspección
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

$mensaje = '';
$error = '';

// Procesar actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->getConnection()->begin_transaction();

        // Actualizar respuestas del checklist
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'pregunta_') === 0) {
                $pregunta_id = (int)str_replace('pregunta_', '', $key);
                $valor = $value === 'NA' ? 'NA' : (float)$value;

                // Verificar si ya existe la respuesta
                $stmt = $db->prepare("SELECT id FROM respuestas_checklist WHERE inspeccion_id = ? AND pregunta_id = ?");
                $stmt->bind_param("ii", $inspeccion_id, $pregunta_id);
                $stmt->execute();
                $existe = $stmt->get_result()->fetch_assoc();

                if ($existe) {
                    // Actualizar
                    $stmt = $db->prepare("UPDATE respuestas_checklist SET valor = ? WHERE inspeccion_id = ? AND pregunta_id = ?");
                    if ($valor === 'NA') {
                        $stmt->bind_param("sii", $valor, $inspeccion_id, $pregunta_id);
                    } else {
                        $stmt->bind_param("dii", $valor, $inspeccion_id, $pregunta_id);
                    }
                    $stmt->execute();
                } else {
                    // Insertar
                    $stmt = $db->prepare("INSERT INTO respuestas_checklist (inspeccion_id, pregunta_id, valor) VALUES (?, ?, ?)");
                    if ($valor === 'NA') {
                        $stmt->bind_param("iis", $inspeccion_id, $pregunta_id, $valor);
                    } else {
                        $stmt->bind_param("iid", $inspeccion_id, $pregunta_id, $valor);
                    }
                    $stmt->execute();
                }
            }
        }

        // Obtener información de la inspección para recalcular
        $stmt = $db->prepare("SELECT socio_inspeccionado_id, MONTH(fecha_inspeccion) as mes, YEAR(fecha_inspeccion) as anio FROM inspecciones WHERE id = ?");
        $stmt->bind_param("i", $inspeccion_id);
        $stmt->execute();
        $insp_info = $stmt->get_result()->fetch_assoc();

        // Recalcular porcentajes por ítem para esta inspección
        $items_result = $db->query("SELECT id FROM items ORDER BY id ASC");
        while ($item = $items_result->fetch_assoc()) {
            $calculator->calcularPorcentajeItem($inspeccion_id, $item['id']);
        }

        // Recalcular porcentajes mensuales
        $calculator->consolidarPorcentajesMensuales($insp_info['socio_inspeccionado_id'], $insp_info['mes'], $insp_info['anio']);

        // Recalcular ICASE mensual
        $calculator->calcularIcaseMensual($insp_info['socio_inspeccionado_id'], $insp_info['mes'], $insp_info['anio']);

        $db->getConnection()->commit();
        $mensaje = "✓ Inspección actualizada exitosamente. Los cálculos han sido recalculados.";

    } catch (Exception $e) {
        $db->getConnection()->rollback();
        $error = "Error al actualizar la inspección: " . $e->getMessage();
    }
}

// Obtener información de la inspección
$stmt = $db->prepare("
    SELECT
        i.id,
        i.fecha_inspeccion,
        i.nombre_inspector,
        i.facilitador_nombre,
        i.lugar,
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

// Obtener todas las preguntas organizadas por ítem con sus respuestas actuales
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Inspección - ICASE</title>
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
                <h1>✏️ Editar Inspección #<?php echo $inspeccion_id; ?></h1>
                <div style="display: flex; gap: 10px;">
                    <a href="detalle_facilitador.php?id=<?php echo $inspeccion_id; ?>" class="btn btn-info">👁️ Ver Detalle</a>
                    <a href="todas.php" class="btn btn-secondary">← Volver</a>
                </div>
            </div>

            <?php if ($mensaje): ?>
                <div class="alert alert-success"><?php echo $mensaje; ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- INFORMACIÓN GENERAL -->
            <div class="card">
                <div class="card-header">
                    <h3>Información General (No Editable)</h3>
                </div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div style="padding: 15px; background: #f8f9fa; border-radius: 8px;">
                            <p><strong>Fecha de Inspección:</strong> <?php echo formatearFecha($inspeccion['fecha_inspeccion']); ?></p>
                            <p><strong>Inspector:</strong> <?php echo htmlspecialchars($inspeccion['nombre_inspector']); ?></p>
                            <p style="margin-bottom: 0;"><strong>Empresa Inspectora:</strong> <?php echo htmlspecialchars($inspeccion['inspector_empresa']); ?></p>
                        </div>
                        <div style="padding: 15px; background: #f8f9fa; border-radius: 8px;">
                            <p><strong>Empresa Inspeccionada:</strong> <?php echo htmlspecialchars($inspeccion['inspeccionado_empresa']); ?></p>
                            <p><strong>Facilitador:</strong> <?php echo htmlspecialchars($inspeccion['facilitador_nombre']); ?></p>
                            <p style="margin-bottom: 0;"><strong>Lugar/Frente:</strong> <?php echo htmlspecialchars($inspeccion['lugar']); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FORMULARIO DE EDICIÓN DEL CHECKLIST -->
            <form method="POST" action="">
                <!-- CONTROLES GENERALES -->
                <div class="card">
                    <div class="card-header">
                        <h3>📋 CONTROLES GENERALES</h3>
                    </div>
                    <div class="card-body">
                        <?php foreach ($items_checklist as $item_id => $item): ?>
                            <?php if ($item['es_general']): ?>
                                <div style="margin-bottom: 30px; border: 1px solid #ddd; border-radius: 8px; padding: 20px; background: #f8f9fa;">
                                    <h4 style="margin: 0 0 20px 0; color: #3498db; padding-bottom: 10px; border-bottom: 2px solid #3498db;">
                                        <?php echo htmlspecialchars($item['nombre']); ?>
                                    </h4>
                                    <?php foreach ($item['preguntas'] as $pregunta): ?>
                                        <div style="margin-bottom: 15px; padding: 15px; background: #fff; border-radius: 5px;">
                                            <label style="display: block; margin-bottom: 10px; font-weight: bold;">
                                                <?php echo htmlspecialchars($pregunta['texto']); ?>
                                            </label>
                                            <div style="display: flex; gap: 15px;">
                                                <label style="cursor: pointer;">
                                                    <input type="radio" name="pregunta_<?php echo $pregunta['id']; ?>" value="1"
                                                           <?php echo $pregunta['valor'] == '1' ? 'checked' : ''; ?> required>
                                                    <span style="margin-left: 5px; color: #27ae60; font-weight: bold;">1 - Cumple</span>
                                                </label>
                                                <label style="cursor: pointer;">
                                                    <input type="radio" name="pregunta_<?php echo $pregunta['id']; ?>" value="0.5"
                                                           <?php echo $pregunta['valor'] == '0.5' ? 'checked' : ''; ?>>
                                                    <span style="margin-left: 5px; color: #f39c12; font-weight: bold;">0.5 - Cumple Parcialmente</span>
                                                </label>
                                                <label style="cursor: pointer;">
                                                    <input type="radio" name="pregunta_<?php echo $pregunta['id']; ?>" value="0"
                                                           <?php echo $pregunta['valor'] == '0' ? 'checked' : ''; ?>>
                                                    <span style="margin-left: 5px; color: #e74c3c; font-weight: bold;">0 - No Cumple</span>
                                                </label>
                                                <label style="cursor: pointer;">
                                                    <input type="radio" name="pregunta_<?php echo $pregunta['id']; ?>" value="NA"
                                                           <?php echo $pregunta['valor'] == 'NA' ? 'checked' : ''; ?>>
                                                    <span style="margin-left: 5px; color: #95a5a6; font-weight: bold;">N.A. - No Aplica</span>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- CONTROLES ESPECÍFICOS -->
                <div class="card">
                    <div class="card-header">
                        <h3>🔧 CONTROLES ESPECÍFICOS</h3>
                    </div>
                    <div class="card-body">
                        <?php foreach ($items_checklist as $item_id => $item): ?>
                            <?php if (!$item['es_general']): ?>
                                <div style="margin-bottom: 30px; border: 1px solid #ddd; border-radius: 8px; padding: 20px; background: #fff9e6;">
                                    <h4 style="margin: 0 0 20px 0; color: #e67e22; padding-bottom: 10px; border-bottom: 2px solid #e67e22;">
                                        <?php echo htmlspecialchars($item['nombre']); ?>
                                    </h4>
                                    <?php foreach ($item['preguntas'] as $pregunta): ?>
                                        <div style="margin-bottom: 15px; padding: 15px; background: #fff; border-radius: 5px;">
                                            <label style="display: block; margin-bottom: 10px; font-weight: bold;">
                                                <?php echo htmlspecialchars($pregunta['texto']); ?>
                                            </label>
                                            <div style="display: flex; gap: 15px;">
                                                <label style="cursor: pointer;">
                                                    <input type="radio" name="pregunta_<?php echo $pregunta['id']; ?>" value="1"
                                                           <?php echo $pregunta['valor'] == '1' ? 'checked' : ''; ?> required>
                                                    <span style="margin-left: 5px; color: #27ae60; font-weight: bold;">1 - Cumple</span>
                                                </label>
                                                <label style="cursor: pointer;">
                                                    <input type="radio" name="pregunta_<?php echo $pregunta['id']; ?>" value="0.5"
                                                           <?php echo $pregunta['valor'] == '0.5' ? 'checked' : ''; ?>>
                                                    <span style="margin-left: 5px; color: #f39c12; font-weight: bold;">0.5 - Cumple Parcialmente</span>
                                                </label>
                                                <label style="cursor: pointer;">
                                                    <input type="radio" name="pregunta_<?php echo $pregunta['id']; ?>" value="0"
                                                           <?php echo $pregunta['valor'] == '0' ? 'checked' : ''; ?>>
                                                    <span style="margin-left: 5px; color: #e74c3c; font-weight: bold;">0 - No Cumple</span>
                                                </label>
                                                <label style="cursor: pointer;">
                                                    <input type="radio" name="pregunta_<?php echo $pregunta['id']; ?>" value="NA"
                                                           <?php echo $pregunta['valor'] == 'NA' ? 'checked' : ''; ?>>
                                                    <span style="margin-left: 5px; color: #95a5a6; font-weight: bold;">N.A. - No Aplica</span>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- BOTONES DE ACCIÓN -->
                <div class="card">
                    <div class="card-body">
                        <div style="display: flex; gap: 15px; justify-content: center;">
                            <button type="submit" class="btn btn-primary" style="padding: 15px 40px; font-size: 1.1em;">
                                💾 Guardar Cambios
                            </button>
                            <a href="detalle_facilitador.php?id=<?php echo $inspeccion_id; ?>" class="btn btn-secondary" style="padding: 15px 40px; font-size: 1.1em;">
                                ✖️ Cancelar
                            </a>
                        </div>
                        <p style="text-align: center; margin-top: 15px; color: #7f8c8d; font-style: italic;">
                            Al guardar, se recalcularán automáticamente los porcentajes de ítems y el ICASE mensual.
                        </p>
                    </div>
                </div>
            </form>

        </main>
    </div>

    <script src="../../assets/js/main.js"></script>
    <script>
    // Confirmación antes de salir si hay cambios
    let formModificado = false;
    const form = document.querySelector('form');
    const inputs = form.querySelectorAll('input[type="radio"]');

    inputs.forEach(input => {
        input.addEventListener('change', () => {
            formModificado = true;
        });
    });

    window.addEventListener('beforeunload', (e) => {
        if (formModificado) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    form.addEventListener('submit', () => {
        formModificado = false;
    });
    </script>
</body>
</html>
