<?php
/**
 * Cerrar Sesión
 */
require_once '../../config/config.php';
require_once '../../includes/functions.php';

iniciarSesion();
session_destroy();
redirigir(BASE_URL . 'index.php');
