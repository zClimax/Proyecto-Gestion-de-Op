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
    header("Location: mis-incidencias.php?error=missing_id");
    exit;
}

$incidencia_id = intval($_GET['id']);

// Conexión a la base de datos
$database = new Database();
$conn = $database->getConnection();
$incidencia = new Incidencia($conn);

// Obtener detalles de la incidencia
if (!$incidencia->getById($incidencia_id)) {
    header("Location: mis-incidencias.php?error=not_found");
    exit;
}

// Verificar si la incidencia está asignada al técnico actual
if ($incidencia->id_tecnico != $_SESSION['empleado_id'] && !has_permission('admin')) {
    header("Location: mis-incidencias.php?error=permission_denied");
    exit;
}

// Verificar que la incidencia no esté cerrada (ID_Stat = 6)
if ($incidencia->id_stat == 6) {
    header("Location: ver-incidencia.php?id=$incidencia_id&error=closed_incident");
    exit;
}

// Obtener estados permitidos según el estado actual
$estados_permitidos = [];
switch ($incidencia->id_stat) {
    case 2: // Asignada
        $estados_permitidos = [3, 4]; // En proceso, En espera
        break;
    case 3: // En proceso
        $estados_permitidos = [4, 5]; // En espera, Resuelta
        break;
    case 4: // En espera
        $estados_permitidos = [3, 5]; // En proceso, Resuelta
        break;
    case 5: // Resuelta
        $estados_permitidos = [3]; // Volver a En proceso si hay rechazo
        break;
    default:
        $estados_permitidos = [];
}

// Obtener lista de estados para el select
$query_estados = "SELECT ID, Descripcion FROM ESTATUS_INCIDENCIA WHERE ID IN (" . implode(',', $estados_permitidos) . ") ORDER BY ID";
$stmt_estados = $conn->prepare($query_estados);
$stmt_estados->execute();
$estados = $stmt_estados->fetchAll(PDO::FETCH_ASSOC);

// Obtener respuestas a preguntas de control (si existen)
$query_respuestas = "SELECT r.ID, r.Respuesta, r.FechaRegistro, p.Pregunta, p.Tipo
                    FROM CONTROL_RESPUESTA r
                    JOIN CONTROL_PREGUNTA p ON r.ID_Pregunta = p.ID
                    WHERE r.ID_Incidencia = ?
                    ORDER BY p.Orden";
$stmt_respuestas = $conn->prepare($query_respuestas);
$stmt_respuestas->execute([$incidencia_id]);

// Obtener comentarios previos
$query_comentarios = "SELECT TOP 5 c.Comentario, c.FechaRegistro, c.TipoComentario, e.Nombre as NombreEmpleado
                     FROM INCIDENCIA_COMENTARIO c
                     LEFT JOIN USUARIO u ON c.ID_Usuario = u.ID
                     LEFT JOIN EMPLEADO e ON u.ID_Empleado = e.ID
                     WHERE c.ID_Incidencia = ?
                     ORDER BY c.FechaRegistro DESC";
$stmt_comentarios = $conn->prepare($query_comentarios);
$stmt_comentarios->execute([$incidencia_id]);

// Procesar el formulario si se envió
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validar datos del formulario
        $nuevo_estado = $_POST['nuevo_estado'] ?? '';
        $comentario = $_POST['comentario'] ?? '';
        $solucion = $_POST['solucion'] ?? '';
        
        // Validaciones básicas
        if (empty($nuevo_estado) || empty($comentario)) {
            $error = "Por favor complete todos los campos obligatorios.";
        } elseif (!in_array($nuevo_estado, array_column($estados, 'ID'))) {
            $error = "El estado seleccionado no es válido para esta incidencia.";
        } else {
            // Iniciar transacción
            $conn->beginTransaction();
            
            // Si el nuevo estado es "Resuelta", registrar la solución
            $tipo_comentario = 'ACTUALIZACION';
            if ($nuevo_estado == 5) { // 5 = Resuelta
                if (empty($solucion)) {
                    $error = "Debe proporcionar una descripción de la solución para marcar como resuelta.";
                    throw new Exception($error);
                }
                
                $tipo_comentario = 'SOLUCION';
                
                // Registrar la solución
                $incidencia->registrarSolucion($incidencia_id, $solucion, $_SESSION['user_id']);
            }
            
            // Guardar el estado anterior para el historial
            $estado_anterior = $incidencia->id_stat;
            
            // Actualizar el estado de la incidencia
            $incidencia->id_stat = $nuevo_estado;
            $incidencia->modified_by = $_SESSION['user_id'];
            
            if ($incidencia->update()) {
                // Registrar el cambio de estado en el historial
                $incidencia->registrarCambioEstado($incidencia_id, $estado_anterior, $nuevo_estado, $_SESSION['user_id']);
                
                // Agregar el comentario
                $incidencia->agregarComentario($incidencia_id, $_SESSION['user_id'], $comentario, $tipo_comentario, true);
                
                // Confirmar la transacción
                $conn->commit();
                
                // Redirigir con mensaje de éxito
                header("Location: ver-incidencia.php?id=$incidencia_id&success=updated");
                exit;
            } else {
                throw new Exception("Error al actualizar la incidencia.");
            }
        }
    } catch (PDOException $e) {
        // Revertir la transacción en caso de error
        $conn->rollBack();
        $error = "Error en la base de datos: " . $e->getMessage();
    } catch (Exception $e) {
        // Revertir la transacción en caso de error
        $conn->rollBack();
        $error = $e->getMessage();
    }
}
?>

<!-- Título de la página -->
<h1 class="h2">Actualizar Incidencia #<?php echo $incidencia_id; ?></h1>

<!-- Botones de acción -->
<div class="row mb-4">
    <div class="col-12">
        <a href="mis-incidencias.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Volver a mis incidencias
        </a>
        <a href="ver-incidencia.php?id=<?php echo $incidencia_id; ?>" class="btn btn-info ms-2">
            <i class="fas fa-eye me-2"></i>Ver detalles completos
        </a>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
    </div>
<?php endif; ?>

<!-- Formulario de actualización -->
<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Actualizar Estado de la Incidencia</h5>
            </div>
            <div class="card-body">
                <form action="" method="POST">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="estado_actual" class="form-label">Estado Actual:</label>
                            <input type="text" class="form-control" id="estado_actual" value="<?php echo htmlspecialchars($incidencia->estado); ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label for="nuevo_estado" class="form-label">Nuevo Estado: *</label>
                            <select class="form-select" id="nuevo_estado" name="nuevo_estado" required>
                                <option value="">Seleccionar nuevo estado...</option>
                                <?php foreach ($estados as $estado): ?>
                                    <option value="<?php echo $estado['ID']; ?>">
                                        <?php echo htmlspecialchars($estado['Descripcion']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="comentario" class="form-label">Comentarios: *</label>
                        <textarea class="form-control" id="comentario" name="comentario" rows="3" required placeholder="Proporcione detalles sobre la actualización..."></textarea>
                    </div>
                    
                    <div id="seccion_solucion" class="mb-3" style="display: none;">
                        <label for="solucion" class="form-label">Descripción de la Solución: *</label>
                        <textarea class="form-control" id="solucion" name="solucion" rows="4" placeholder="Describa detalladamente la solución aplicada..."></textarea>
                        <div class="form-text">Proporcione una descripción clara y completa de la solución implementada.</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-12">
                            <hr>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Guardar Actualización
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Información de la Incidencia</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <th>Elemento:</th>
                        <td>
                            <?php if ($incidencia->ci_tipo): ?>
                                <span class="badge bg-info"><?php echo htmlspecialchars($incidencia->ci_tipo); ?></span>
                            <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const nuevoEstadoSelect = document.getElementById('nuevo_estado');
    const seccionSolucion = document.getElementById('seccion_solucion');
    const solucionTextarea = document.getElementById('solucion');
    
    // Mostrar/ocultar sección de solución según el estado seleccionado
    nuevoEstadoSelect.addEventListener('change', function() {
        if (this.value == '5') { // 5 = Resuelta
            seccionSolucion.style.display = 'block';
            solucionTextarea.setAttribute('required', '');
        } else {
            seccionSolucion.style.display = 'none';
            solucionTextarea.removeAttribute('required');
        }
    });
});
</script>

<?php
// Incluir el pie de página
require_once '../../includes/footer.php';
?>
                            <?php echo htmlspecialchars($incidencia->ci_nombre); ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Prioridad:</th>
                        <td>
                            <?php 
                            $prioridad = htmlspecialchars($incidencia->prioridad);
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
                        <th>Reportado por:</th>
                        <td>
                            <?php
                            // Obtener el nombre del usuario que reportó
                            $query_usuario = "SELECT e.Nombre 
                                             FROM USUARIO u 
                                             JOIN EMPLEADO e ON u.ID_Empleado = e.ID 
                                             WHERE u.ID = ?";
                            $stmt_usuario = $conn->prepare($query_usuario);
                            $stmt_usuario->execute([$incidencia->created_by]);
                            $usuario = $stmt_usuario->fetch(PDO::FETCH_ASSOC);
                            echo htmlspecialchars($usuario['Nombre'] ?? 'Desconocido');
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Fecha reporte:</th>
                        <td><?php echo date('d/m/Y H:i', strtotime($incidencia->fecha_inicio)); ?></td>
                    </tr>
                </table>
                
                <hr>
                
                <h6>Descripción del problema:</h6>
                <p><?php echo nl2br(htmlspecialchars($incidencia->descripcion)); ?></p>
                
                <?php if ($stmt_respuestas->rowCount() > 0): ?>
                <hr>
                
                <h6>Respuestas a preguntas de control:</h6>
                <table class="table table-sm">
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
                </table>
                <?php endif; ?>
                
                <?php if ($stmt_comentarios->rowCount() > 0): ?>
                <hr>
                
                <h6>Comentarios recientes:</h6>
                <div style="max-height: 200px; overflow-y: auto;">
                    <?php while ($comentario = $stmt_comentarios->fetch(PDO::FETCH_ASSOC)): ?>
                        <div class="card mb-2">
                            <div class="card-body p-2">
                                <p class="mb-1">
                                    <strong><?php echo htmlspecialchars($comentario['NombreEmpleado']); ?></strong>
                                    <?php if ($comentario['TipoComentario'] != 'COMENTARIO'): ?>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($comentario['TipoComentario']); ?></span>
                                    <?php endif; ?>
                                    <small class="text-muted">(<?php echo date('d/m/Y H:i', strtotime($comentario['FechaRegistro'])); ?>)</small>
                                </p>
                                <p class="mb-0 small"><?php echo nl2br(htmlspecialchars($comentario['Comentario'])); ?></p>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
                <?php endif; ?>