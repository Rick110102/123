<?php
/**
 * Sistema de Cálculo del ICASE
 * Implementa la lógica completa de ponderación y redistribución de pesos
 * según especificaciones de la PARTE B del documento
 */

class IcaseCalculator {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Calcular porcentaje de cumplimiento de un ítem en una inspección específica
     * Según Sección 20 del documento
     */
    public function calcularPorcentajeItem($inspeccion_id, $item_id) {
        // Obtener preguntas del ítem
        $stmt = $this->db->prepare("SELECT id FROM preguntas WHERE item_id = ?");
        $stmt->bind_param("i", $item_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $preguntas_ids = [];
        while ($row = $result->fetch_assoc()) {
            $preguntas_ids[] = $row['id'];
        }

        if (empty($preguntas_ids)) {
            return ['porcentaje' => null, 'es_na' => true];
        }

        // Obtener respuestas de esta inspección para este ítem
        $ids_str = implode(',', $preguntas_ids);
        $sql = "SELECT valor FROM respuestas_checklist
                WHERE inspeccion_id = ? AND pregunta_id IN ($ids_str)";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $inspeccion_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $suma = 0;
        $total_aplicables = 0;
        $total_na = 0;

        while ($row = $result->fetch_assoc()) {
            $valor = $row['valor'];

            if ($valor === 'NA') {
                $total_na++;
            } else {
                // Convertir a float
                $suma += (float)$valor;
                $total_aplicables++;
            }
        }

        // Si todas las preguntas son N.A., el ítem es N.A.
        if ($total_aplicables === 0) {
            return ['porcentaje' => null, 'es_na' => true];
        }

        // Calcular porcentaje
        $porcentaje = ($suma / $total_aplicables) * 100;

        return [
            'porcentaje' => round($porcentaje, 2),
            'es_na' => false
        ];
    }

    /**
     * Guardar porcentajes por ítem de una inspección
     */
    public function guardarPorcentajesInspeccion($inspeccion_id) {
        // Obtener todos los ítems
        $items_result = $this->db->query("SELECT id FROM items ORDER BY orden ASC");

        while ($item = $items_result->fetch_assoc()) {
            $resultado = $this->calcularPorcentajeItem($inspeccion_id, $item['id']);

            // Verificar si ya existe
            $stmt = $this->db->prepare("
                SELECT id FROM porcentajes_items_inspeccion
                WHERE inspeccion_id = ? AND item_id = ?
            ");
            $stmt->bind_param("ii", $inspeccion_id, $item['id']);
            $stmt->execute();
            $existe = $stmt->get_result()->num_rows > 0;

            if ($existe) {
                // Actualizar
                $stmt = $this->db->prepare("
                    UPDATE porcentajes_items_inspeccion
                    SET porcentaje = ?, es_na = ?
                    WHERE inspeccion_id = ? AND item_id = ?
                ");
                $stmt->bind_param("diii",
                    $resultado['porcentaje'],
                    $resultado['es_na'],
                    $inspeccion_id,
                    $item['id']
                );
            } else {
                // Insertar
                $stmt = $this->db->prepare("
                    INSERT INTO porcentajes_items_inspeccion
                    (inspeccion_id, item_id, porcentaje, es_na)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->bind_param("iidi",
                    $inspeccion_id,
                    $item['id'],
                    $resultado['porcentaje'],
                    $resultado['es_na']
                );
            }

            $stmt->execute();
        }

        return true;
    }

    /**
     * Consolidar porcentajes mensuales de un socio
     * Según Sección 21 del documento
     */
    public function consolidarPorcentajesMensuales($socio_id, $mes, $anio) {
        // Obtener todas las inspecciones del socio en el mes
        $stmt = $this->db->prepare("
            SELECT id
            FROM inspecciones
            WHERE socio_inspeccionado_id = ?
            AND MONTH(fecha_inspeccion) = ?
            AND YEAR(fecha_inspeccion) = ?
        ");
        $stmt->bind_param("iii", $socio_id, $mes, $anio);
        $stmt->execute();
        $result = $stmt->get_result();

        $inspecciones_ids = [];
        while ($row = $result->fetch_assoc()) {
            $inspecciones_ids[] = $row['id'];
        }

        if (empty($inspecciones_ids)) {
            return false; // No hay inspecciones para consolidar
        }

        // Obtener todos los ítems
        $items_result = $this->db->query("SELECT id FROM items ORDER BY orden ASC");

        while ($item = $items_result->fetch_assoc()) {
            $item_id = $item['id'];

            // Obtener porcentajes de este ítem en todas las inspecciones del mes
            $ids_str = implode(',', $inspecciones_ids);
            $sql = "SELECT porcentaje, es_na
                    FROM porcentajes_items_inspeccion
                    WHERE item_id = ? AND inspeccion_id IN ($ids_str)";

            $stmt = $this->db->prepare($sql);
            $stmt->bind_param("i", $item_id);
            $stmt->execute();
            $result = $stmt->get_result();

            $suma_porcentajes = 0;
            $cantidad_aplicables = 0;

            while ($row = $result->fetch_assoc()) {
                if (!$row['es_na']) {
                    $suma_porcentajes += $row['porcentaje'];
                    $cantidad_aplicables++;
                }
            }

            // Determinar si el ítem consolidado es N.A.
            $es_na = ($cantidad_aplicables === 0);
            $porcentaje_consolidado = $es_na ? null : round($suma_porcentajes / $cantidad_aplicables, 2);

            // Guardar en porcentajes_items_mensual
            $stmt = $this->db->prepare("
                SELECT id FROM porcentajes_items_mensual
                WHERE socio_id = ? AND item_id = ? AND mes = ? AND anio = ?
            ");
            $stmt->bind_param("iiii", $socio_id, $item_id, $mes, $anio);
            $stmt->execute();
            $existe = $stmt->get_result()->num_rows > 0;

            if ($existe) {
                $stmt = $this->db->prepare("
                    UPDATE porcentajes_items_mensual
                    SET porcentaje_consolidado = ?, es_na = ?
                    WHERE socio_id = ? AND item_id = ? AND mes = ? AND anio = ?
                ");
                $stmt->bind_param("diiiii",
                    $porcentaje_consolidado,
                    $es_na,
                    $socio_id,
                    $item_id,
                    $mes,
                    $anio
                );
            } else {
                $stmt = $this->db->prepare("
                    INSERT INTO porcentajes_items_mensual
                    (socio_id, item_id, mes, anio, porcentaje_consolidado, es_na)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("iiiidi",
                    $socio_id,
                    $item_id,
                    $mes,
                    $anio,
                    $porcentaje_consolidado,
                    $es_na
                );
            }

            $stmt->execute();
        }

        return true;
    }

    /**
     * Calcular ICASE mensual con redistribución de pesos
     * Según Secciones 22, 23 y 24 del documento
     */
    public function calcularIcaseMensual($socio_id, $mes, $anio) {
        // Primero consolidar porcentajes mensuales
        $this->consolidarPorcentajesMensuales($socio_id, $mes, $anio);

        // Obtener porcentajes consolidados y pesos base
        $stmt = $this->db->prepare("
            SELECT
                i.id,
                i.nombre,
                i.peso_base,
                p.porcentaje_consolidado,
                p.es_na
            FROM items i
            LEFT JOIN porcentajes_items_mensual p ON p.item_id = i.id
            WHERE (p.socio_id = ? OR p.socio_id IS NULL)
            AND (p.mes = ? OR p.mes IS NULL)
            AND (p.anio = ? OR p.anio IS NULL)
            ORDER BY i.orden ASC
        ");
        $stmt->bind_param("iii", $socio_id, $mes, $anio);
        $stmt->execute();
        $result = $stmt->get_result();

        $items_data = [];
        $suma_pesos_aplicables = 0;
        $hay_items_aplicables = false;

        while ($row = $result->fetch_assoc()) {
            // Si es_na es NULL, significa que no hay datos para este ítem
            $es_na = ($row['es_na'] === null || $row['es_na'] == 1);

            $items_data[] = [
                'id' => $row['id'],
                'nombre' => $row['nombre'],
                'peso_base' => (float)$row['peso_base'],
                'porcentaje' => $row['porcentaje_consolidado'],
                'es_na' => $es_na
            ];

            if (!$es_na) {
                $suma_pesos_aplicables += (float)$row['peso_base'];
                $hay_items_aplicables = true;
            }
        }

        // Si no hay ítems aplicables, no se puede calcular ICASE
        if (!$hay_items_aplicables || $suma_pesos_aplicables == 0) {
            return null;
        }

        // Calcular factor de redistribución (Sección 23.3)
        $factor_redistribucion = 1.0 / $suma_pesos_aplicables;

        // Calcular ICASE (Sección 24)
        $icase = 0;
        foreach ($items_data as $item) {
            if (!$item['es_na']) {
                $peso_ajustado = $item['peso_base'] * $factor_redistribucion;
                $icase += $peso_ajustado * $item['porcentaje'];
            }
        }

        $icase = round($icase, 2);

        // Guardar ICASE mensual
        $stmt = $this->db->prepare("
            SELECT id FROM icase_mensual
            WHERE socio_id = ? AND mes = ? AND anio = ?
        ");
        $stmt->bind_param("iii", $socio_id, $mes, $anio);
        $stmt->execute();
        $existe = $stmt->get_result()->num_rows > 0;

        if ($existe) {
            $stmt = $this->db->prepare("
                UPDATE icase_mensual
                SET icase = ?, fecha_calculo = NOW()
                WHERE socio_id = ? AND mes = ? AND anio = ?
            ");
            $stmt->bind_param("diii", $icase, $socio_id, $mes, $anio);
        } else {
            $stmt = $this->db->prepare("
                INSERT INTO icase_mensual (socio_id, mes, anio, icase)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param("iiid", $socio_id, $mes, $anio, $icase);
        }

        $stmt->execute();

        return $icase;
    }

    /**
     * Obtener ICASE mensual de un socio
     */
    public function obtenerIcaseMensual($socio_id, $mes, $anio) {
        $stmt = $this->db->prepare("
            SELECT icase FROM icase_mensual
            WHERE socio_id = ? AND mes = ? AND anio = ?
        ");
        $stmt->bind_param("iii", $socio_id, $mes, $anio);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row['icase'];
        }

        // Si no existe, calcularlo
        return $this->calcularIcaseMensual($socio_id, $mes, $anio);
    }

    /**
     * Obtener porcentajes consolidados por ítem de un socio en un mes
     */
    public function obtenerPorcentajesItemsMensual($socio_id, $mes, $anio) {
        $stmt = $this->db->prepare("
            SELECT
                i.id,
                i.nombre,
                p.porcentaje_consolidado,
                p.es_na
            FROM items i
            LEFT JOIN porcentajes_items_mensual p ON p.item_id = i.id
            WHERE (p.socio_id = ? OR p.socio_id IS NULL)
            AND (p.mes = ? OR p.mes IS NULL)
            AND (p.anio = ? OR p.anio IS NULL)
            ORDER BY i.orden ASC
        ");
        $stmt->bind_param("iii", $socio_id, $mes, $anio);
        $stmt->execute();
        $result = $stmt->get_result();

        $porcentajes = [];
        while ($row = $result->fetch_assoc()) {
            $porcentajes[] = [
                'item_id' => $row['id'],
                'item_nombre' => $row['nombre'],
                'porcentaje' => $row['porcentaje_consolidado'],
                'es_na' => ($row['es_na'] === null || $row['es_na'] == 1)
            ];
        }

        return $porcentajes;
    }

    /**
     * Recalcular ICASE de todos los socios para un mes
     */
    public function recalcularIcaseTodosSocios($mes, $anio) {
        $result = $this->db->query("SELECT id FROM socios");

        while ($row = $result->fetch_assoc()) {
            $this->calcularIcaseMensual($row['id'], $mes, $anio);
        }

        return true;
    }

    /**
     * Actualizar estados de observaciones
     */
    public function actualizarEstadosObservaciones() {
        $hoy = date('Y-m-d');

        // Actualizar observaciones vencidas
        $sql = "UPDATE observaciones
                SET estado = 'vencida'
                WHERE fecha_vencimiento < ?
                AND estado != 'completada'
                AND es_nula = 0";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("s", $hoy);
        $stmt->execute();

        // Actualizar observaciones en plazo
        $sql = "UPDATE observaciones
                SET estado = 'en_plazo'
                WHERE fecha_vencimiento >= ?
                AND estado != 'completada'
                AND es_nula = 0";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("s", $hoy);
        $stmt->execute();

        return true;
    }
}
