<?php
/**
 * Módulo de Registro de Inspecciones (3 Pestañas)
 */
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/icase_calculator.php';

protegerSocio();

$db = Database::getInstance();
$calculator = new IcaseCalculator();
$socio_id = obtenerSocioId();
$mensaje = '';
$error = '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['finalizar_inspeccion'])) {
    try {
        $db->getConnection()->begin_transaction();

        // Datos Generales (Pestaña 1)
        $socio_inspeccionado_id = (int)$_POST['socio_inspeccionado'];
        $nombre_inspector = limpiar($_POST['nombre_inspector']);
        $facilitador = limpiar($_POST['facilitador']);
        $lugar = limpiar($_POST['lugar']);
        $fecha_inspeccion = $_POST['fecha_inspeccion'];

        // Insertar inspección
        $stmt = $db->prepare("
            INSERT INTO inspecciones
            (socio_inspector_id, socio_inspeccionado_id, nombre_inspector, facilitador_nombre, lugar, fecha_inspeccion)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iissss",
            $socio_id,
            $socio_inspeccionado_id,
            $nombre_inspector,
            $facilitador,
            $lugar,
            $fecha_inspeccion
        );
        $stmt->execute();
        $inspeccion_id = $db->lastInsertId();

        // Guardar respuestas del checklist (Pestaña 2)
        $preguntas_result = $db->query("SELECT id FROM preguntas ORDER BY item_id, orden ASC");
        while ($pregunta = $preguntas_result->fetch_assoc()) {
            $pregunta_id = $pregunta['id'];
            $valor = $_POST["pregunta_$pregunta_id"] ?? 'NA';

            $stmt = $db->prepare("
                INSERT INTO respuestas_checklist (inspeccion_id, pregunta_id, valor)
                VALUES (?, ?, ?)
            ");
            $stmt->bind_param("iis", $inspeccion_id, $pregunta_id, $valor);
            $stmt->execute();
        }

        // Calcular y guardar porcentajes por ítem
        $calculator->guardarPorcentajesInspeccion($inspeccion_id);

        // Procesar observaciones (Pestaña 3) si existen
        if (isset($_FILES['observaciones_fotos']) && !empty($_FILES['observaciones_fotos']['name'][0])) {
            $total_obs = count($_FILES['observaciones_fotos']['name']);

            for ($i = 0; $i < $total_obs; $i++) {
                if ($_FILES['observaciones_fotos']['error'][$i] === UPLOAD_ERR_OK) {
                    // Subir foto
                    $file = [
                        'name' => $_FILES['observaciones_fotos']['name'][$i],
                        'type' => $_FILES['observaciones_fotos']['type'][$i],
                        'tmp_name' => $_FILES['observaciones_fotos']['tmp_name'][$i],
                        'error' => $_FILES['observaciones_fotos']['error'][$i],
                        'size' => $_FILES['observaciones_fotos']['size'][$i]
                    ];

                    $upload_result = subirImagen($file, 'observaciones');

                    if ($upload_result['success']) {
                        $descripcion = limpiar($_POST["obs_descripcion_$i"]);
                        $tipo = $_POST["obs_tipo_$i"] ?? null;
                        if ($tipo === 'Ninguno') $tipo = null;
                        $fecha_vencimiento = $_POST["obs_vencimiento_$i"];

                        $stmt = $db->prepare("
                            INSERT INTO observaciones
                            (inspeccion_id, foto_url, descripcion, tipo, fecha_registro, fecha_vencimiento, estado)
                            VALUES (?, ?, ?, ?, CURDATE(), ?, 'en_plazo')
                        ");
                        $stmt->bind_param("issis",
                            $inspeccion_id,
                            $upload_result['url'],
                            $descripcion,
                            $tipo,
                            $fecha_vencimiento
                        );
                        $stmt->execute();
                    }
                }
            }
        }

        // Crear notificación para el socio inspeccionado
        $stmt = $db->prepare("
            SELECT usuario_id FROM socios WHERE id = ?
        ");
        $stmt->bind_param("i", $socio_inspeccionado_id);
        $stmt->execute();
        $usuario_inspeccionado_id = $stmt->get_result()->fetch_assoc()['usuario_id'];

        $mensaje_notif = "Se ha registrado una nueva inspección en $lugar el $fecha_inspeccion";
        $stmt = $db->prepare("
            INSERT INTO notificaciones (usuario_id, tipo, mensaje, referencia_id)
            VALUES (?, 'nueva_inspeccion', ?, ?)
        ");
        $stmt->bind_param("isi", $usuario_inspeccionado_id, $mensaje_notif, $inspeccion_id);
        $stmt->execute();

        $db->getConnection()->commit();
        $mensaje = "Inspección registrada exitosamente";

        // Recalcular ICASE del mes
        $mes = date('n', strtotime($fecha_inspeccion));
        $anio = date('Y', strtotime($fecha_inspeccion));
        $calculator->calcularIcaseMensual($socio_inspeccionado_id, $mes, $anio);

    } catch (Exception $e) {
        $db->getConnection()->rollback();
        $error = "Error al registrar la inspección: " . $e->getMessage();
    }
}

// Obtener lista de socios
$socios_result = $db->query("SELECT id, nombre_empresa FROM socios ORDER BY nombre_empresa ASC");
$socios = [];
while ($socio = $socios_result->fetch_assoc()) {
    $socios[] = $socio;
}

// Obtener preguntas organizadas por ítem
$items_result = $db->query("
    SELECT i.id as item_id, i.nombre as item_nombre, i.es_general,
           p.id as pregunta_id, p.texto_pregunta, p.orden
    FROM items i
    INNER JOIN preguntas p ON p.item_id = i.id
    ORDER BY i.orden ASC, p.orden ASC
");

$items_preguntas = [];
while ($row = $items_result->fetch_assoc()) {
    $item_id = $row['item_id'];
    if (!isset($items_preguntas[$item_id])) {
        $items_preguntas[$item_id] = [
            'nombre' => $row['item_nombre'],
            'es_general' => $row['es_general'],
            'preguntas' => []
        ];
    }
    $items_preguntas[$item_id]['preguntas'][] = [
        'id' => $row['pregunta_id'],
        'texto' => $row['texto_pregunta'],
        'orden' => $row['orden']
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Inspección - ICASE</title>
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
                <li><a href="#" class="active">Registrar Inspección</a></li>
                <li><a href="recibidas.php">Inspecciones Recibidas</a></li>
                <li><a href="../login/logout.php">Cerrar Sesión</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="top-bar">
                <h1>Registrar Nueva Inspección</h1>
            </div>

            <?php if ($mensaje): ?>
                <div class="alert alert-success">
                    <?php echo $mensaje; ?>
                    <br><a href="../dashboard/socio.php">Volver al Dashboard</a>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if (!$mensaje): ?>
            <div class="card">
                <form method="POST" enctype="multipart/form-data" id="formInspeccion">

                    <!-- PESTAÑAS -->
                    <div class="tabs">
                        <button type="button" class="tab active" onclick="cambiarTab('tab1')">
                            1. Datos Generales
                        </button>
                        <button type="button" class="tab" onclick="cambiarTab('tab2')">
                            2. Evaluación (Checklist)
                        </button>
                        <button type="button" class="tab" onclick="cambiarTab('tab3')">
                            3. Observaciones
                        </button>
                    </div>

                    <!-- PESTAÑA 1: DATOS GENERALES -->
                    <div id="tab1" class="tab-content active">
                        <h3>Datos Generales de la Inspección</h3>

                        <div class="form-group">
                            <label>Socio Inspeccionado *</label>
                            <select name="socio_inspeccionado" id="socio_inspeccionado" class="form-control" required>
                                <option value="">-- Seleccione --</option>
                                <?php foreach ($socios as $s): ?>
                                    <option value="<?php echo $s['id']; ?>">
                                        <?php echo htmlspecialchars($s['nombre_empresa']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Nombre del Inspector *</label>
                            <input type="text" name="nombre_inspector" id="nombre_inspector" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label>Facilitador *</label>
                            <select name="facilitador" id="facilitador" class="form-control" required>
                                <?php foreach (FACILITADORES as $fac): ?>
                                    <option value="<?php echo $fac; ?>"><?php echo $fac; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Lugar / Frente de Trabajo *</label>
                            <input type="text" name="lugar" id="lugar" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label>Fecha de Inspección *</label>
                            <input type="date" name="fecha_inspeccion" id="fecha_inspeccion" class="form-control"
                                   value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <button type="button" class="btn btn-primary" onclick="if(validarPestana1()) cambiarTab('tab2')">
                            Siguiente: Evaluación →
                        </button>
                    </div>

                    <!-- PESTAÑA 2: CHECKLIST -->
                    <div id="tab2" class="tab-content">
                        <h3>Evaluación - Checklist de Inspección</h3>
                        <p><strong>Valores:</strong> 0 = No cumple | 0.5 = Cumplimiento parcial | 1 = Cumplimiento total | N.A. = No aplica</p>

                        <h4 style="color: var(--primary-color); margin-top: 30px;">CONTROLES GENERALES</h4>
                        <?php foreach ($items_preguntas as $item_id => $item): ?>
                            <?php if ($item['es_general']): ?>
                                <div class="checklist-section">
                                    <h4><?php echo htmlspecialchars($item['nombre']); ?></h4>
                                    <?php foreach ($item['preguntas'] as $pregunta): ?>
                                        <div class="checklist-item">
                                            <label><?php echo htmlspecialchars($pregunta['texto']); ?></label>
                                            <div class="radio-group">
                                                <label>
                                                    <input type="radio" name="pregunta_<?php echo $pregunta['id']; ?>" value="0" required>
                                                    0
                                                </label>
                                                <label>
                                                    <input type="radio" name="pregunta_<?php echo $pregunta['id']; ?>" value="0.5">
                                                    0.5
                                                </label>
                                                <label>
                                                    <input type="radio" name="pregunta_<?php echo $pregunta['id']; ?>" value="1">
                                                    1
                                                </label>
                                                <label>
                                                    <input type="radio" name="pregunta_<?php echo $pregunta['id']; ?>" value="NA">
                                                    N.A.
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>

                        <h4 style="color: var(--primary-color); margin-top: 30px;">CONTROLES ESPECÍFICOS</h4>
                        <?php foreach ($items_preguntas as $item_id => $item): ?>
                            <?php if (!$item['es_general']): ?>
                                <div class="checklist-section">
                                    <h4><?php echo htmlspecialchars($item['nombre']); ?></h4>
                                    <?php foreach ($item['preguntas'] as $pregunta): ?>
                                        <div class="checklist-item">
                                            <label><?php echo htmlspecialchars($pregunta['texto']); ?></label>
                                            <div class="radio-group">
                                                <label>
                                                    <input type="radio" name="pregunta_<?php echo $pregunta['id']; ?>" value="0" required>
                                                    0
                                                </label>
                                                <label>
                                                    <input type="radio" name="pregunta_<?php echo $pregunta['id']; ?>" value="0.5">
                                                    0.5
                                                </label>
                                                <label>
                                                    <input type="radio" name="pregunta_<?php echo $pregunta['id']; ?>" value="1">
                                                    1
                                                </label>
                                                <label>
                                                    <input type="radio" name="pregunta_<?php echo $pregunta['id']; ?>" value="NA">
                                                    N.A.
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>

                        <div style="display: flex; gap: 15px; margin-top: 30px;">
                            <button type="button" class="btn btn-secondary" onclick="cambiarTab('tab1')">
                                ← Anterior
                            </button>
                            <button type="button" class="btn btn-primary" onclick="if(validarPestana2()) cambiarTab('tab3')">
                                Siguiente: Observaciones →
                            </button>
                        </div>
                    </div>

                    <!-- PESTAÑA 3: OBSERVACIONES -->
                    <div id="tab3" class="tab-content">
                        <h3>Observaciones de Campo</h3>
                        <p>Las observaciones son opcionales, pero si registra una, debe incluir foto Y descripción.</p>

                        <div id="observaciones-container">
                            <!-- Las observaciones se agregan dinámicamente -->
                        </div>

                        <button type="button" class="btn btn-success" onclick="agregarObservacionForm()">
                            + Agregar Observación
                        </button>

                        <div style="margin-top: 30px; display: flex; gap: 15px;">
                            <button type="button" class="btn btn-secondary" onclick="cambiarTab('tab2')">
                                ← Anterior
                            </button>
                            <button type="submit" name="finalizar_inspeccion" class="btn btn-primary">
                                Finalizar y Guardar Inspección
                            </button>
                        </div>
                    </div>

                </form>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <script src="../../assets/js/main.js"></script>
    <script>
        let contadorObs = 0;

        function agregarObservacionForm() {
            const container = document.getElementById('observaciones-container');
            const obsDiv = document.createElement('div');
            obsDiv.className = 'observacion-item';
            obsDiv.innerHTML = `
                <h4>Observación ${contadorObs + 1}</h4>
                <div class="form-group">
                    <label>Foto de la Observación *</label>
                    <input type="file" name="observaciones_fotos[]" accept="image/*" required class="form-control">
                </div>
                <div class="form-group">
                    <label>Descripción *</label>
                    <textarea name="obs_descripcion_${contadorObs}" required class="form-control"></textarea>
                </div>
                <div class="form-group">
                    <label>Tipo de Observación</label>
                    <select name="obs_tipo_${contadorObs}" class="form-control">
                        <?php foreach (TIPOS_OBSERVACION as $tipo): ?>
                            <option><?php echo $tipo; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Fecha de Vencimiento *</label>
                    <input type="date" name="obs_vencimiento_${contadorObs}" required class="form-control"
                           min="<?php echo date('Y-m-d'); ?>">
                </div>
                <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()">
                    Eliminar Observación
                </button>
            `;
            container.appendChild(obsDiv);
            contadorObs++;
        }

        function cambiarTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });

            document.getElementById(tabId).classList.add('active');

            const tabs = document.querySelectorAll('.tab');
            const index = parseInt(tabId.replace('tab', '')) - 1;
            tabs[index].classList.add('active');
        }
    </script>
</body>
</html>
