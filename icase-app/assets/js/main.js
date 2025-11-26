/**
 * JavaScript Principal - Sistema ICASE
 */

// ============================================================================
// FUNCIONES PARA COLORES DINÁMICOS EN GRÁFICOS
// ============================================================================

/**
 * Obtener color según porcentaje de desempeño
 * Verde: >90%, Amarillo: 70-90%, Rojo: <70%
 */
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

/**
 * Generar array de colores para múltiples valores
 */
function getColorsArrayByPerformance(values) {
    const backgrounds = [];
    const borders = [];

    values.forEach(value => {
        const color = getColorByPerformance(value);
        backgrounds.push(color.bg);
        borders.push(color.border);
    });

    return { backgrounds, borders };
}

// ============================================================================
// SISTEMA DE NOTIFICACIONES CON TIMER DE 30 SEGUNDOS
// ============================================================================
class NotificationSystem {
    constructor() {
        this.checkInterval = 5000; // Verificar cada 5 segundos
        this.autoCloseTime = 30000; // 30 segundos
        this.currentTimer = null;
        this.init();
    }

    init() {
        this.checkNotifications();
        setInterval(() => this.checkNotifications(), this.checkInterval);
    }

    async checkNotifications() {
        try {
            const response = await fetch('../../assets/js/notifications.php?action=obtener');
            const data = await response.json();

            if (data.notificaciones && data.notificaciones.length > 0) {
                this.mostrarNotificaciones(data.notificaciones);
            }
        } catch (error) {
            console.error('Error al verificar notificaciones:', error);
        }
    }

    mostrarNotificaciones(notificaciones) {
        // No mostrar si ya hay un popup abierto
        if (document.querySelector('.notification-overlay')) {
            return;
        }

        const overlay = document.createElement('div');
        overlay.className = 'notification-overlay';

        const popup = document.createElement('div');
        popup.className = 'notification-popup';

        let html = `
            <button class="notification-close" onclick="notificationSystem.cerrarNotificaciones()">&times;</button>
            <h3 style="margin-bottom: 20px;">Notificaciones</h3>
            <div class="notification-list">
        `;

        notificaciones.forEach(notif => {
            const clase = this.obtenerClaseNotificacion(notif.tipo);
            html += `
                <div class="notification-item ${clase}">
                    <p><strong>${this.obtenerTituloNotificacion(notif.tipo)}</strong></p>
                    <p>${notif.mensaje}</p>
                    <small>${this.formatearFecha(notif.fecha_creacion)}</small>
                </div>
            `;
        });

        html += `
            </div>
            <div style="margin-top: 20px; text-align: center;">
                <button class="btn btn-secondary btn-sm" onclick="notificationSystem.cerrarNotificaciones()">
                    Cerrar
                </button>
            </div>
        `;

        popup.innerHTML = html;
        overlay.appendChild(popup);
        document.body.appendChild(overlay);

        // Guardar IDs de notificaciones para marcar como leídas
        this.notificacionesActuales = notificaciones.map(n => n.id);

        // Timer de 30 segundos
        this.currentTimer = setTimeout(() => {
            this.cerrarNotificaciones();
        }, this.autoCloseTime);
    }

    async cerrarNotificaciones() {
        if (this.currentTimer) {
            clearTimeout(this.currentTimer);
        }

        // Marcar todas como leídas
        try {
            await fetch('../../assets/js/notifications.php?action=marcar_todas_leidas', {
                method: 'POST'
            });
        } catch (error) {
            console.error('Error al marcar notificaciones:', error);
        }

        const overlay = document.querySelector('.notification-overlay');
        if (overlay) {
            overlay.remove();
        }
    }

    obtenerClaseNotificacion(tipo) {
        switch (tipo) {
            case 'observacion_vencida':
                return 'danger';
            case 'observacion_proxima':
                return 'warning';
            default:
                return '';
        }
    }

    obtenerTituloNotificacion(tipo) {
        const titulos = {
            'nueva_inspeccion': 'Nueva Inspección',
            'observacion_levantada': 'Observación Levantada',
            'observacion_vencida': 'Observación Vencida',
            'observacion_proxima': 'Observación Próxima a Vencer'
        };
        return titulos[tipo] || 'Notificación';
    }

    formatearFecha(fecha) {
        const d = new Date(fecha);
        return d.toLocaleString('es-ES');
    }
}

// Inicializar sistema de notificaciones
let notificationSystem;
document.addEventListener('DOMContentLoaded', function() {
    notificationSystem = new NotificationSystem();
});

// ============================================================================
// SISTEMA DE PESTAÑAS (TABS)
// ============================================================================
function cambiarTab(tabId) {
    // Ocultar todos los contenidos
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.remove('active');
    });

    // Desactivar todas las pestañas
    document.querySelectorAll('.tab').forEach(tab => {
        tab.classList.remove('active');
    });

    // Mostrar contenido seleccionado
    document.getElementById(tabId).classList.add('active');

    // Activar pestaña seleccionada
    event.target.classList.add('active');
}

// ============================================================================
// FUNCIONES DE VALIDACIÓN DE FORMULARIOS
// ============================================================================
function validarPestana1() {
    const campos = [
        'socio_inspeccionado',
        'nombre_inspector',
        'facilitador',
        'lugar',
        'fecha_inspeccion'
    ];

    for (let campo of campos) {
        const elemento = document.getElementById(campo);
        if (!elemento || !elemento.value) {
            alert('Por favor complete todos los campos de Datos Generales');
            return false;
        }
    }

    return true;
}

function validarPestana2() {
    const preguntas = document.querySelectorAll('input[type="radio"]');
    const preguntasIds = new Set();

    preguntas.forEach(radio => {
        const name = radio.getAttribute('name');
        preguntasIds.add(name);
    });

    // Verificar que cada pregunta tenga una respuesta seleccionada
    for (let preguntaName of preguntasIds) {
        const seleccionado = document.querySelector(`input[name="${preguntaName}"]:checked`);
        if (!seleccionado) {
            alert('Por favor responda todas las preguntas del checklist');
            return false;
        }
    }

    return true;
}

function validarObservacion() {
    const foto = document.getElementById('obs_foto');
    const descripcion = document.getElementById('obs_descripcion');

    if (!foto || !foto.files || foto.files.length === 0) {
        alert('Debe cargar una foto de la observación');
        return false;
    }

    if (!descripcion || !descripcion.value.trim()) {
        alert('Debe proporcionar una descripción de la observación');
        return false;
    }

    return true;
}

// ============================================================================
// MANEJO DE OBSERVACIONES
// ============================================================================
let observacionesRegistradas = [];

function agregarObservacion() {
    if (!validarObservacion()) {
        return;
    }

    const foto = document.getElementById('obs_foto').files[0];
    const descripcion = document.getElementById('obs_descripcion').value;
    const tipo = document.getElementById('obs_tipo').value;
    const fechaVencimiento = document.getElementById('obs_fecha_vencimiento').value;

    if (!fechaVencimiento) {
        alert('Debe seleccionar una fecha de vencimiento');
        return;
    }

    const observacion = {
        foto: foto,
        descripcion: descripcion,
        tipo: tipo,
        fecha_vencimiento: fechaVencimiento,
        fecha_registro: new Date().toISOString().split('T')[0]
    };

    observacionesRegistradas.push(observacion);
    actualizarListaObservaciones();

    // Limpiar formulario
    document.getElementById('obs_foto').value = '';
    document.getElementById('obs_descripcion').value = '';
    document.getElementById('obs_tipo').value = 'Ninguno';
    document.getElementById('obs_fecha_vencimiento').value = '';
}

function actualizarListaObservaciones() {
    const lista = document.getElementById('lista_observaciones');
    if (!lista) return;

    if (observacionesRegistradas.length === 0) {
        lista.innerHTML = '<p>No hay observaciones registradas</p>';
        return;
    }

    let html = '';
    observacionesRegistradas.forEach((obs, index) => {
        html += `
            <div class="observacion-item">
                <div class="observacion-header">
                    <strong>Observación ${index + 1}</strong>
                    <button type="button" class="btn btn-danger btn-sm" onclick="eliminarObservacion(${index})">
                        Eliminar
                    </button>
                </div>
                <p><strong>Descripción:</strong> ${obs.descripcion}</p>
                <p><strong>Tipo:</strong> ${obs.tipo}</p>
                <p><strong>Vencimiento:</strong> ${formatearFechaLocal(obs.fecha_vencimiento)}</p>
            </div>
        `;
    });

    lista.innerHTML = html;
}

function eliminarObservacion(index) {
    if (confirm('¿Está seguro de eliminar esta observación?')) {
        observacionesRegistradas.splice(index, 1);
        actualizarListaObservaciones();
    }
}

// ============================================================================
// FUNCIONES DE UTILIDAD
// ============================================================================
function formatearFechaLocal(fecha) {
    const partes = fecha.split('-');
    return `${partes[2]}/${partes[1]}/${partes[0]}`;
}

function confirmarAccion(mensaje) {
    return confirm(mensaje);
}

function mostrarCarga(elemento) {
    if (elemento) {
        elemento.innerHTML = '<div class="loading">Cargando</div>';
    }
}

// ============================================================================
// FILTROS
// ============================================================================
function aplicarFiltros() {
    const mes = document.getElementById('filtro_mes').value;
    const anio = document.getElementById('filtro_anio').value;

    // Recargar página con parámetros
    const url = new URL(window.location.href);
    url.searchParams.set('mes', mes);
    url.searchParams.set('anio', anio);
    window.location.href = url.toString();
}

// ============================================================================
// PREVIEW DE IMÁGENES
// ============================================================================
function previewImagen(input, previewId) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();

        reader.onload = function(e) {
            const preview = document.getElementById(previewId);
            if (preview) {
                preview.innerHTML = `<img src="${e.target.result}" style="max-width: 300px; height: auto;">`;
            }
        };

        reader.readAsDataURL(input.files[0]);
    }
}

// ============================================================================
// LOGOUT
// ============================================================================
function logout() {
    if (confirm('¿Está seguro de cerrar sesión?')) {
        window.location.href = '../../modules/login/logout.php';
    }
}
