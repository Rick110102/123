<?php
/**
 * Script de Instalación - Crear Directorios de Upload
 * Ejecutar UNA VEZ después de subir archivos a Hostinger
 */

require_once 'config/config.php';

echo "<h2>Script de Instalación - Sistema ICASE</h2>";
echo "<hr>";

$directorios = [
    UPLOAD_PATH . 'observaciones/',
    UPLOAD_PATH . 'levantamientos/'
];

$errores = 0;
$exitos = 0;

foreach ($directorios as $dir) {
    echo "<p>Verificando: <strong>$dir</strong>...";

    if (is_dir($dir)) {
        echo " <span style='color: green;'>✓ Ya existe</span></p>";

        // Verificar permisos de escritura
        if (is_writable($dir)) {
            echo "<p style='margin-left: 20px;'>Permisos: <span style='color: green;'>✓ Escritura OK</span></p>";
            $exitos++;
        } else {
            echo "<p style='margin-left: 20px;'>Permisos: <span style='color: red;'>✗ Sin permisos de escritura</span></p>";
            echo "<p style='margin-left: 20px; color: orange;'>Ejecuta: chmod 755 $dir</p>";
            $errores++;
        }
    } else {
        // Intentar crear directorio
        if (mkdir($dir, 0755, true)) {
            echo " <span style='color: green;'>✓ Creado exitosamente</span></p>";
            $exitos++;
        } else {
            echo " <span style='color: red;'>✗ Error al crear</span></p>";
            echo "<p style='margin-left: 20px; color: red;'>Crea manualmente el directorio y dale permisos 755</p>";
            $errores++;
        }
    }
}

echo "<hr>";
echo "<h3>Resumen:</h3>";
echo "<p><strong>Exitosos:</strong> <span style='color: green;'>$exitos</span></p>";
echo "<p><strong>Errores:</strong> <span style='color: " . ($errores > 0 ? 'red' : 'green') . "'>$errores</span></p>";

if ($errores === 0) {
    echo "<h3 style='color: green;'>✓ Instalación completada correctamente</h3>";
    echo "<p><a href='index.php' style='padding: 10px 20px; background-color: #3498db; color: white; text-decoration: none; border-radius: 5px;'>Ir al Sistema</a></p>";
    echo "<p style='color: orange;'><strong>IMPORTANTE:</strong> Elimina este archivo (install.php) después de la instalación por seguridad.</p>";
} else {
    echo "<h3 style='color: red;'>⚠ Hay errores que corregir</h3>";
    echo "<p>Sigue las instrucciones anteriores y vuelve a ejecutar este script.</p>";
}
?>
