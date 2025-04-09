<?php
// Incluir el encabezado
require_once '../../includes/header.php';

// Verificar permiso de gestión de incidencias
check_permission('gestionar_incidencias');

// Incluir configuración de base de datos
require_once '../../config/database.php';
require_once '../../models/Incidencia.php';

// Verificar que se ha proporcionado un ID de incidencia
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: incidencias.php?error=missing_id");
    exit;
}

$incidencia_id = intval($_GET['id']);

// Conexión a la base de datos
$database = new Database();
$conn = $database->getConnection();
$incidencia_model = new Incidencia($conn);

// Obtener detalles de la incidencia
if (!$incidencia_model->getById($incidencia_id)) {
    header("Location: incidencias.php?error=not_found");
    exit;
}

// Obtener comentarios y seguimiento de la incidencia
$query_comentarios = "SELECT c.ID, c.Comentario, c.TipoComentario, c.FechaRegistro, 
                             c.Publico, u.Username, e.Nombre as NombreEmpleado
                      FROM INCIDENCIA_COMENTARIO c
                      LEFT JOIN USUARIO u ON c.ID_Usuario = u.ID
                      LEFT JOIN EMPLEADO e ON u.ID_Empleado = e.ID
                      WHERE c.ID_Incidencia = ?
                      ORDER BY c.FechaRegistro ASC";
$stmt_comentarios = $conn->prepare($query_comentarios);
$stmt_comentarios->execute([$incidencia_id]);

// Obtener historial de estados
$query_historial = "SELECT h.ID, h.ID_EstadoAnterior, h.ID_EstadoNuevo, h.FechaCambio,
                           s1.Descripcion as EstadoAnterior, s2.Descripcion as EstadoNuevo,
                           u.Username, e.Nombre as NombreEmpleado
                    FROM INCIDENCIA_HISTORIAL h
                    LEFT JOIN ESTATUS_INCIDENCIA s1 ON h.ID_EstadoAnterior = s1.ID
                    LEFT JOIN ESTATUS_INCIDENCIA s2 ON h.ID_EstadoNuevo = s2.ID
                    LEFT JOIN USUARIO u ON h.ID_Usuario = u.ID
                    LEFT JOIN EMPLEADO e ON u.ID_Empleado = e.ID
                    WHERE h.ID_Incidencia = ?
                    ORDER BY h.FechaCambio ASC";
$stmt_historial = $conn->prepare($query_historial);
$stmt_historial->execute([$incidencia_id]);

// Obtener respuestas a preguntas de control
$query_respuestas = "SELECT r.ID, r.Respuesta, r.FechaRegistro, p.Pregunta, p.Tipo
                    FROM CONTROL_RESPUESTA r
                    JOIN CONTROL_PREGUNTA p ON r.ID_Pregunta = p.ID
                    WHERE r.ID_Incidencia = ?
                    ORDER BY p.Orden";
$stmt_respuestas = $conn->prepare($query_respuestas);
$stmt_respuestas->execute([$incidencia_id]);

// Obtener solución si existe
$query_solucion = "SELECT s.ID, s.Descripcion, s.FechaRegistro, e.Nombre as Tecnico
                  FROM INCIDENCIA_SOLUCION s
                  LEFT JOIN USUARIO u ON s.ID_Usuario = u.ID
                  LEFT JOIN EMPLEADO e ON u.ID_Empleado = e.ID
                  WHERE s.ID_Incidencia = ?
                  ORDER BY s.FechaRegistro DESC";
$stmt_solucion = $conn->prepare($query_solucion);
$stmt_solucion->execute([$incidencia_id]);
$solucion = $stmt_solucion->fetch(PDO::FETCH_ASSOC);

// Obtener la evaluación si existe y el estado es 'Cerrada'
$evaluacion = null;
if ($incidencia_model->id_stat == 6) { // 6 = Cerrada
    $query_evaluacion = "SELECT e.ID, e.Calificacion, e.Comentario, e.FechaRegistro
                        FROM INCIDENCIA_EVALUACION e
                        WHERE e.ID_Incidencia = ?";
    $stmt_evaluacion = $conn->prepare($query_evaluacion);
    $stmt_evaluacion->execute([$incidencia_id]);
    if ($stmt_evaluacion->rowCount() > 0) {
        $evaluacion = $stmt_evaluacion->fetch(PDO::FETCH_ASSOC);
    }
}

// Obtener nombre del usuario que reportó
$query_usuario = "SELECT e.Nombre, e.Email 
                 FROM USUARIO u 
                 JOIN EMPLEADO e ON u.ID_Empleado = e.ID 
                 WHERE u.ID = ?";
$stmt_usuario = $conn->prepare($query_usuario);
$stmt_usuario->execute([$incidencia_model->created_by]);
$usuario_reporto = $stmt_usuario->fetch(PDO::FETCH_ASSOC);

// Procesar el formulario de comentario si se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'agregar_comentario') {
    try {
        $comentario = $_POST['comentario'];
        $publico = isset($_POST['publico']) ? 1 : 0;
        
        if (!empty($comentario)) {
            if ($incidencia_model->agregarComentario($incidencia_id, $_SESSION['user_id'], $comentario, 'COMENTARIO', $publico)) {
                header("Location: ver-incidencia.php?id=$incidencia_id&success=comment_added");
                exit;
            } else {
                $error = "Error al guardar el comentario.";
            }
        } else {
            $error = "El comentario no puede estar vacío.";
        }
    } catch (Exception $e) {
        $error = "Error en la base de datos: " . $e->getMessage();
    }
}
?>

<!-- Título de la página -->
<h1 class="h2">Detalles de Incidencia #<?php echo $incidencia_id; ?></h1>

<!-- Botones de acción -->
<div class="row mb-4">
    <div class="col-12">
        <a href="incidencias.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Volver a incidencias
        </a>
        
        <?php if ($incidencia_model->id_stat == 1): // 1 = Nueva ?>
        <a href="asignar-incidencia.php?id=<?php echo $incidencia_id; ?>" class="btn btn-primary ms-2">
            <i class="fas fa-user-plus me-2"></i>Asignar Incidencia
        </a>
        <?php endif; ?>
        
        <?php if ($incidencia_model->id_tecnico && $incidencia_model->id_stat != 6): // Si ya tiene técnico y no está cerrada ?>
        <a href="asignar-incidencia.php?id=<?php echo $incidencia_id; ?>" class="btn btn-warning ms-2">
            <i class="fas fa-exchange-alt me-2"></i>Reasignar Incidencia
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($_GET['success']) && $_GET['success'] == 'comment_added'): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle me-2"></i>Comentario agregado correctamente.
    </div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
    </div>
<?php endif; ?>

<!-- Información general de la incidencia -->
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Información General</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th class="w-25">ID:</th>
                                <td>#<?php echo $incidencia_model->id; ?></td>
                            </tr>
                            <tr>
                                <th>Estado:</th>
                                <td>
                                    <?php 
                                    $estado = htmlspecialchars($incidencia_model->estado);
                                    $badgeClass = 'bg-info';
                                    
                                    if ($estado === 'Nueva') {
                                        $badgeClass = 'bg-danger';
                                    } elseif ($estado === 'Asignada') {
                                        $badgeClass = 'bg-primary';
                                    } elseif ($estado === 'En proceso') {
                                        $badgeClass = 'bg-warning text-dark';
                                    } elseif ($estado === 'En espera') {
                                        $badgeClass = 'bg-secondary';
                                    } elseif ($estado === 'Resuelta') {
                                        $badgeClass = 'bg-success';
                                    } elseif ($estado === 'Cerrada') {
                                        $badgeClass = 'bg-dark';
                                    }
                                    
                                    echo "<span class='badge $badgeClass'>$estado</span>";
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Prioridad:</th>
                                <td>
                                    <?php 
                                    $prioridad = htmlspecialchars($incidencia_model->prioridad);
                                    $badgeClass = 'bg-info';
                                    
                                    if ($prioridad === 'Crítica') {
                                        $badgeClass = 'bg-danger';
                                    } elseif ($prioridad === 'Alta') {
                                        $badgeClass = 'bg-warning text-dark';
                                    } elseif ($prioridad === 'Media') {
                                        $badgeClass = 'bg-primary';
                                    }
                                    
                                    echo "<span class='badge $badgeClass'>$prioridad</span>";
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Elemento:</th>
                                <td>
                                    <?php if ($incidencia_model->ci_tipo): ?>
                                        <span class="badge bg-info"><?php echo htmlspecialchars($incidencia_model->ci_tipo); ?></span>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($incidencia_model->ci_nombre); ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Reportado por:</th>
                                <td>
                                    <?php echo htmlspecialchars($usuario_reporto['Nombre']); ?>
                                    <?php if (!empty($usuario_reporto['Email'])): ?>
                                        <br><small><a href="mailto:<?php echo htmlspecialchars($usuario_reporto['Email']); ?>"><?php echo htmlspecialchars($usuario_reporto['Email']); ?></a></small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th class="w-25">Fecha de reporte:</th>
                                <td><?php echo date('d/m/Y H:i', strtotime($incidencia_model->fecha_inicio)); ?></td>
                            </tr>
                            <tr>
                                <th>Técnico asignado:</th>
                                <td>
                                    <?php if ($incidencia_model->tecnico_nombre): ?>
                                        <?php echo htmlspecialchars($incidencia_model->tecnico_nombre); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Sin asignar</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Tiempo transcurrido:</th>
                                <td>
                                    <?php
                                    $fecha_inicio = new DateTime($incidencia_model->fecha_inicio);
                                    $fecha_actual = new DateTime();
                                    
                                    if ($incidencia_model->fecha_terminacion) {
                                        $fecha_fin = new DateTime($incidencia_model->fecha_terminacion);
                                        $intervalo = $fecha_inicio->diff($fecha_fin);
                                        echo "Resuelto en: ";
                                    } else {
                                        $intervalo = $fecha_inicio->diff($fecha_actual);
                                        echo "En curso: ";
                                    }
                                    
                                    if ($intervalo->days > 0) {
                                        echo $intervalo->days . " día(s) ";
                                    }
                                    
                                    echo $intervalo->h . " hora(s) " . $intervalo->i . " min";
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Fecha de resolución:</th>
                                <td>
                                    <?php if ($incidencia_model->fecha_terminacion): ?>
                                        <?php echo date('d/m/Y H:i', strtotime($incidencia_model->fecha_terminacion)); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Pendiente</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Descripción del problema:</h6>
                        <p><?php echo nl2br(htmlspecialchars($incidencia_model->descripcion)); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Preguntas de Control -->
<?php if ($stmt_respuestas->rowCount() > 0): ?>
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Preguntas de Control</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Pregunta</th>
                                <th>Respuesta</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($respuesta = $stmt_respuestas->fetch(PDO::FETCH_ASSOC)): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($respuesta['Pregunta']); ?></td>
                                    <td>
                                        <?php 
                                        if ($respuesta['Tipo'] == 'SI_NO') {
                                            if ($respuesta['Respuesta'] == 'SI') {
                                                echo '<span class="badge bg-success">Sí</span>';
                                            } else {
                                                echo '<span class="badge bg-danger">No</span>';
                                            }
                                        } else {
                                            echo htmlspecialchars($respuesta['Respuesta']);
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Solución -->
<?php if ($solucion): ?>
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Solución Aplicada</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-success">
                    <p class="mb-2"><strong>Aplicada por:</strong> <?php echo htmlspecialchars($solucion['Tecnico']); ?></p>
                    <p class="mb-2"><strong>Fecha:</strong> <?php echo date('d/m/Y H:i', strtotime($solucion['FechaRegistro'])); ?></p>
                    <hr>
                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($solucion['Descripcion'])); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Comentarios y Seguimiento -->
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Comentarios y Seguimiento</h5>
            </div>
            <div class="card-body">
                <?php if ($stmt_comentarios->rowCount() > 0): ?>
                    <div class="comentarios-container">
                        <?php while ($comentario = $stmt_comentarios->fetch(PDO::FETCH_ASSOC)): ?>
                            <div class="comentario <?php echo $comentario['ID_Usuario'] == $_SESSION['user_id'] ? 'comentario-propio' : ''; ?> mb-3">
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <span>
                                            <strong><?php echo htmlspecialchars($comentario['NombreEmpleado']); ?></strong>
                                            <?php if ($comentario['TipoComentario'] !== 'COMENTARIO'): ?>
                                                <span class="badge bg-info"><?php echo htmlspecialchars($comentario['TipoComentario']); ?></span>
                                            <?php endif; ?>
                                            <?php if ($comentario['Publico'] == 0): ?>
                                                <span class="badge bg-warning text-dark">Privado</span>
                                            <?php endif; ?>
                                        </span>
                                        <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($comentario['FechaRegistro'])); ?></small>
                                    </div>
                                    <div class="card-body">
                                        <p class="card-text"><?php echo nl2br(htmlspecialchars($comentario['Comentario'])); ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        No hay comentarios registrados para esta incidencia.
                    </div>
                <?php endif; ?>
                
                <!-- Formulario para agregar comentarios -->
                <?php if ($incidencia_model->id_stat != 6): // No permitir comentarios en incidencias cerradas ?>
                <div class="mt-4">
                    <h6>Agregar Comentario</h6>
                    <form action="" method="POST">
                        <input type="hidden" name="action" value="agregar_comentario">
                        <div class="mb-3">
                            <textarea class="form-control" name="comentario" id="comentario" rows="3" required placeholder="Escriba su comentario aquí..."></textarea>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" name="publico" id="publico" value="1" checked>
                            <label class="form-check-label" for="publico">Visible para el usuario</label>
                            <small class="form-text text-muted d-block">Desmarque esta opción si el comentario es solo para el equipo técnico.</small>
                        </div>
                        <button type="submit" class="btn btn-primary">Enviar Comentario</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Historial de Estados -->
<?php if ($stmt_historial->rowCount() > 0): ?>
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Historial de Estados</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Estado Anterior</th>
                                <th>Nuevo Estado</th>
                                <th>Actualizado por</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($historial = $stmt_historial->fetch(PDO::FETCH_ASSOC)): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y H:i', strtotime($historial['FechaCambio'])); ?></td>
                                    <td>
                                        <?php if ($historial['EstadoAnterior']): ?>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($historial['EstadoAnterior']); ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inicio</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $estado = htmlspecialchars($historial['EstadoNuevo']);
                                        $badgeClass = 'bg-info';
                                        
                                        if ($estado === 'Nueva') {
                                            $badgeClass = 'bg-danger';
                                        } elseif ($estado === 'Asignada') {
                                            $badgeClass = 'bg-primary';
                                        } elseif ($estado === 'En proceso') {
                                            $badgeClass = 'bg-warning text-dark';
                                        } elseif ($estado === 'En espera') {
                                            $badgeClass = 'bg-secondary';
                                        } elseif ($estado === 'Resuelta') {
                                            $badgeClass = 'bg-success';
                                        } elseif ($estado === 'Cerrada') {
                                            $badgeClass = 'bg-dark';
                                        }
                                        
                                        echo "<span class='badge $badgeClass'>$estado</span>";
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($historial['NombreEmpleado']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Evaluación del Servicio -->
<?php if ($evaluacion): ?>
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Evaluación del Servicio</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Calificación:</h6>
                        <div class="rating">
                            <?php
                            $calificacion = intval($evaluacion['Calificacion']);
                            for ($i = 1; $i <= 5; $i++) {
                                if ($i <= $calificacion) {
                                    echo '<i class="fas fa-star text-warning"></i>';
                                } else {
                                    echo '<i class="far fa-star text-warning"></i>';
                                }
                            }
                            ?>
                            <span class="badge bg-primary ms-2"><?php echo $calificacion; ?> de 5</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6>Fecha de evaluación:</h6>
                        <p><?php echo date('d/m/Y H:i', strtotime($evaluacion['FechaRegistro'])); ?></p>
                    </div>
                </div>
                
                <?php if (!empty($evaluacion['Comentario'])): ?>
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Comentarios del usuario:</h6>
                        <p><?php echo nl2br(htmlspecialchars($evaluacion['Comentario'])); ?></p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
.comentario-propio .card {
    background-color: #f8f9fa;
}
</style>

<?php
// Incluir el pie de página
require_once '../../includes/footer.php';
?>