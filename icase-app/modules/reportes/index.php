<?php
/**
 * Reportes - Vista Facilitador
 * Muestra reportes semanales y mensuales
 */
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/icase_calculator.php';

protegerFacilitador();

$db = Database::getInstance();
$calculator = new IcaseCalculator();

// Actualizar estados de observaciones
$calculator->actualizarEstadosObservaciones();

// Determinar si mostrar reporte semanal o mensual
$tipo_reporte = $_GET['tipo'] ?? 'semanal';

// Obtener socios activos
$socios_result = $db->query("SELECT id, nombre_empresa FROM socios ORDER BY nombre_empresa ASC");
$socios = [];
while ($socio = $socios_result->fetch_assoc()) {
    $socios[] = $socio;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - ICASE</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
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
                <li><a href="../inspecciones/todas.php">Todas las Inspecciones</a></li>
                <li><a href="../observaciones/todas.php">Todas las Observaciones</a></li>
                <li><a href="#" class="active">Reportes</a></li>
                <li><a href="../login/logout.php">Cerrar Sesión</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="top-bar">
                <h1>Reportes</h1>
                <div class="user-info">
                    <div class="user-avatar">F</div>
                    <span>Facilitador</span>
                </div>
            </div>

            <!-- Selector de Tipo de Reporte -->
            <div class="card">
                <div class="card-body">
                    <div style="display: flex; gap: 15px;">
                        <a href="?tipo=semanal" class="btn <?php echo $tipo_reporte == 'semanal' ? 'btn-primary' : 'btn-secondary'; ?>" style="flex: 1;">
                            📊 Reporte Semanal de Observaciones
                        </a>
                        <a href="?tipo=mensual" class="btn <?php echo $tipo_reporte == 'mensual' ? 'btn-primary' : 'btn-secondary'; ?>" style="flex: 1;">
                            📈 Reporte Mensual de Inspecciones
                        </a>
                    </div>
                </div>
            </div>

            <?php if ($tipo_reporte == 'semanal'): ?>
                <!-- REPORTE SEMANAL DE OBSERVACIONES -->
                <?php include 'semanal.php'; ?>

            <?php else: ?>
                <!-- REPORTE MENSUAL DE INSPECCIONES -->
                <?php include 'mensual.php'; ?>

            <?php endif; ?>

        </main>
    </div>

    <script src="../../assets/js/main.js"></script>
</body>
</html>
