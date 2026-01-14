<?php
/**
 * Script de Depuración - Verificar datos del sistema
 * ELIMINAR DESPUÉS DE USAR
 */
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/icase_calculator.php';

$db = Database::getInstance();
$calculator = new IcaseCalculator();

echo "<h2>Script de Depuración - Sistema ICASE</h2>";
echo "<style>
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #4CAF50; color: white; }
    .error { color: red; font-weight: bold; }
    .success { color: green; font-weight: bold; }
    .warning { color: orange; font-weight: bold; }
</style>";

echo "<hr>";

// 1. Verificar conexión a BD
echo "<h3>1. Conexión a Base de Datos</h3>";
try {
    $db->query("SELECT 1");
    echo "<p class='success'>✓ Conexión exitosa</p>";
} catch (Exception $e) {
    echo "<p class='error'>✗ Error de conexión: " . $e->getMessage() . "</p>";
    exit;
}

// 2. Contar registros principales
echo "<h3>2. Registros en Base de Datos</h3>";
$tables = [
    'usuarios' => 'Usuarios',
    'socios' => 'Socios',
    'items' => 'Ítems de evaluación',
    'preguntas' => 'Preguntas del checklist',
    'inspecciones' => 'Inspecciones registradas',
    'respuestas_checklist' => 'Respuestas del checklist',
    'observaciones' => 'Observaciones',
    'porcentajes_items_inspeccion' => 'Porcentajes por inspección',
    'porcentajes_items_mensual' => 'Porcentajes mensuales',
    'icase_mensual' => 'ICASE mensuales'
];

echo "<table>";
echo "<tr><th>Tabla</th><th>Cantidad</th></tr>";
foreach ($tables as $table => $nombre) {
    $result = $db->query("SELECT COUNT(*) as total FROM $table");
    $count = $result->fetch_assoc()['total'];
    echo "<tr><td>$nombre ($table)</td><td>$count</td></tr>";
}
echo "</table>";

// 3. Verificar inspecciones del mes actual
echo "<h3>3. Inspecciones del Mes Actual</h3>";
$mes = date('n');
$anio = date('Y');
echo "<p>Buscando inspecciones de " . obtenerNombreMes($mes) . " $anio</p>";

$stmt = $db->prepare("
    SELECT i.id, i.fecha_inspeccion, s.nombre_empresa
    FROM inspecciones i
    INNER JOIN socios s ON s.id = i.socio_inspeccionado_id
    WHERE MONTH(i.fecha_inspeccion) = ? AND YEAR(i.fecha_inspeccion) = ?
");
$stmt->bind_param("ii", $mes, $anio);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo "<table>";
    echo "<tr><th>ID</th><th>Fecha</th><th>Socio</th><th>Porcentajes calculados</th></tr>";
    while ($row = $result->fetch_assoc()) {
        $stmt2 = $db->prepare("SELECT COUNT(*) as total FROM porcentajes_items_inspeccion WHERE inspeccion_id = ?");
        $stmt2->bind_param("i", $row['id']);
        $stmt2->execute();
        $porc = $stmt2->get_result()->fetch_assoc()['total'];

        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['fecha_inspeccion'] . "</td>";
        echo "<td>" . $row['nombre_empresa'] . "</td>";
        echo "<td>" . ($porc > 0 ? "<span class='success'>$porc/7</span>" : "<span class='error'>NO CALCULADOS</span>") . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p class='warning'>⚠ No hay inspecciones para este mes</p>";
}

// 4. Verificar ICASE calculados
echo "<h3>4. ICASE Calculados</h3>";
$result = $db->query("
    SELECT s.nombre_empresa, i.mes, i.anio, i.icase
    FROM icase_mensual i
    INNER JOIN socios s ON s.id = i.socio_id
    ORDER BY i.anio DESC, i.mes DESC
    LIMIT 10
");

if ($result->num_rows > 0) {
    echo "<table>";
    echo "<tr><th>Socio</th><th>Mes/Año</th><th>ICASE</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['nombre_empresa'] . "</td>";
        echo "<td>" . obtenerNombreMes($row['mes']) . " " . $row['anio'] . "</td>";
        echo "<td class='success'>" . number_format($row['icase'], 2) . "%</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p class='warning'>⚠ No hay ICASE calculados aún</p>";
}

// 5. Calcular ICASE manualmente para el mes actual
echo "<h3>5. Intentar Calcular ICASE del Mes Actual</h3>";
$socios_result = $db->query("SELECT id, nombre_empresa FROM socios");
echo "<table>";
echo "<tr><th>Socio</th><th>ICASE Calculado</th><th>Estado</th></tr>";
while ($socio = $socios_result->fetch_assoc()) {
    try {
        $icase = $calculator->calcularIcaseMensual($socio['id'], $mes, $anio);
        if ($icase !== null) {
            echo "<tr>";
            echo "<td>" . $socio['nombre_empresa'] . "</td>";
            echo "<td class='success'>" . number_format($icase, 2) . "%</td>";
            echo "<td class='success'>✓ OK</td>";
            echo "</tr>";
        } else {
            echo "<tr>";
            echo "<td>" . $socio['nombre_empresa'] . "</td>";
            echo "<td>-</td>";
            echo "<td class='warning'>Sin datos</td>";
            echo "</tr>";
        }
    } catch (Exception $e) {
        echo "<tr>";
        echo "<td>" . $socio['nombre_empresa'] . "</td>";
        echo "<td>-</td>";
        echo "<td class='error'>Error: " . $e->getMessage() . "</td>";
        echo "</tr>";
    }
}
echo "</table>";

// 6. Verificar directorios de upload
echo "<h3>6. Directorios de Upload</h3>";
$dirs = [
    UPLOAD_PATH . 'observaciones/',
    UPLOAD_PATH . 'levantamientos/'
];

echo "<table>";
echo "<tr><th>Directorio</th><th>Existe</th><th>Escribible</th></tr>";
foreach ($dirs as $dir) {
    $existe = is_dir($dir);
    $escribible = $existe && is_writable($dir);

    echo "<tr>";
    echo "<td>$dir</td>";
    echo "<td>" . ($existe ? "<span class='success'>✓ Sí</span>" : "<span class='error'>✗ No</span>") . "</td>";
    echo "<td>" . ($escribible ? "<span class='success'>✓ Sí</span>" : "<span class='error'>✗ No</span>") . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<hr>";
echo "<p style='background: #fff3cd; padding: 10px; border-left: 4px solid #ffc107;'>";
echo "<strong>Siguiente paso:</strong> ";
echo "Si ves errores arriba, anota cuáles son y contáctame para ayudarte a resolverlos.<br>";
echo "<strong>IMPORTANTE:</strong> Elimina este archivo (debug.php) después de usar por seguridad.";
echo "</p>";
?>
