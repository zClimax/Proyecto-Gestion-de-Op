<?php
// Incluir configuración básica
require_once '../../includes/header.php';
require_once '../../config/database.php';
require_once '../../models/Problema.php';

// Verificar permiso de gestión de problemas
check_permission('gestionar_problemas');

// Verificar parámetros
if (!isset($_GET['problema_id']) || !isset($_GET['incidencia_id'])) {
    header('Location: problemas.php?error=missing_parameters');
    exit;
}

$problema_id = intval($_GET['problema_id']);
$incidencia_id = intval($_GET['incidencia_id']);

// Conexión a la base de datos
$database = new Database();
$conn = $database->getConnection();
$problema = new Problema($conn);

// Verificar que el problema existe
if (!$problema->getById($problema_id)) {
    header('Location: problemas.php?error=problema_not_found');
    exit;
}

// Desasociar la incidencia
if ($problema->desasignarIncidencia($problema_id, $incidencia_id)) {
    header('Location: ver-problema.php?id=' . $problema_id . '&success=incidencia_desasociada');
} else {
    header('Location: ver-problema.php?id=' . $problema_id . '&error=desasociar_failed');
}
?>