<?php
// Incluir el encabezado y verificar permisos
require_once '../../includes/header.php';
check_permission('gestionar_incidencias');

// Incluir configuración de base de datos
require_once '../../config/database.php';

// Verificar ID de incidencia
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: mis-incidencias.php?error=missing_id");
    exit;
}

$incidencia_id = intval($_GET['id']);
$database = new Database();
$conn = $database->getConnection();

// Obtener detalles de la incidencia
$query = "SELECT i.ID, i.Descripcion, i.FechaInicio, i.ID_Prioridad, i.ID_CI, 
                 i.ID_Tecnico, i.ID_Stat, i.CreatedBy,
                 p.Descripcion as Prioridad, s.Descripcion as Estado,
                 ci.Nombre as CI_Nombre, t.Nombre as CI_Tipo, ci.NumSerie as CI_NumSerie,
                 emp.Nombre as Reportado_Por_Nombre, emp.Email as Reportado_Por_Email
          FROM INCIDENCIA i
          LEFT JOIN PRIORIDAD p ON i.ID_Prioridad = p.ID
          LEFT JOIN ESTATUS_INCIDENCIA s ON i.ID_Stat = s.ID
          LEFT JOIN CI ci ON i.ID_CI = ci.ID
          LEFT JOIN TIPO_CI t ON ci.ID_TipoCI = t.ID
          LEFT JOIN USUARIO u ON i.CreatedBy = u.ID
          LEFT JOIN EMPLEADO emp ON u.ID_Empleado = emp.ID
          WHERE i.ID = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$incidencia_id]);

// Verificar existencia y permisos
if ($stmt->rowCount() == 0) {
    header("Location: mis-incidencias.php?error=not_found");
    exit;
}

$incidencia = $stmt->fetch(PDO::FETCH_ASSOC);

if ($incidencia['ID_Tecnico'] != $_SESSION['empleado_id'] && !has_permission('admin')) {
    header("Location: mis-incidencias.php?error=permission_denied");
    exit;
}

if ($incidencia['ID_Stat'] == 6) { // 6 = Cerrada
    header("Location: ver-incidencia.php?id=$incidencia_id&error=closed_incident");
    exit;
}

// Determinar estados permitidos
$estados_permitidos = [];
switch ($incidencia['ID_Stat']) {
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
        $estados_permitidos = [3]; // Volver a En proceso
        break;
}

// Consultar estados disponibles
$query_estados = "SELECT ID, Descripcion FROM ESTATUS_INCIDENCIA 
                  WHERE ID IN (" . implode(',', $estados_permitidos) . ") 
                  ORDER BY ID";
$stmt_estados = $conn->prepare($query_estados);
$stmt_estados->execute();
$estados = $stmt_estados->fetchAll(PDO::FETCH_ASSOC);

// Obtener datos adicionales en paralelo
$stmt_respuestas = $conn->prepare("SELECT r.ID, r.Respuesta, p.Pregunta, p.Tipo
                                   FROM CONTROL_RESPUESTA r
                                   JOIN CONTROL_PREGUNTA p ON r.ID_Pregunta = p.ID
                                   WHERE r.ID_Incidencia = ?
                                   ORDER BY p.Orden");
$stmt_comentarios = $conn->prepare("SELECT TOP 5 c.Comentario, c.FechaRegistro, c.TipoComentario, 
                                    e.Nombre as NombreEmpleado
                                    FROM INCIDENCIA_COMENTARIO c
                                    LEFT JOIN USUARIO u ON c.ID_Usuario = u.ID
                                    LEFT JOIN EMPLEADO e ON u.ID_Empleado = e.ID
                                    WHERE c.ID_Incidencia = ?
                                    ORDER BY c.FechaRegistro DESC");
$stmt_respuestas->execute([$incidencia_id]);
$stmt_comentarios->execute([$incidencia_id]);

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nuevo_estado = $_POST['nuevo_estado'] ?? '';
    $comentario = $_POST['comentario'] ?? '';
    $solucion = $_POST['solucion'] ?? '';
    
    if (empty($nuevo_estado) || empty($comentario)) {
        $error = "Por favor complete todos los campos obligatorios.";
    } elseif (!in_array($nuevo_estado, array_column($estados, 'ID'))) {
        $error = "El estado seleccionado no es válido para esta incidencia.";
    } else {
        // Variables para la consulta
        $tipo_comentario = 'ACTUALIZACION';
        $params = [];
        $query_update = "UPDATE INCIDENCIA SET ID_Stat = ?, ModifiedBy = ?, ModifiedDate = GETDATE()";
        $params[] = $nuevo_estado;
        $params[] = $_SESSION['user_id'];
        
        // Si el nuevo estado es "Resuelta", registrar la solución
        if ($nuevo_estado == 5) { // 5 = Resuelta
            if (empty($solucion)) {
                $error = "Debe proporcionar una descripción de la solución para marcar como resuelta.";
                // Detener la ejecución si hay error
                if (isset($error)) {
                    // No continuar con el procesamiento
                } else {
                    $tipo_comentario = 'SOLUCION';
                    $query_update .= ", FechaTerminacion = GETDATE()";
                }
            } else {
                $tipo_comentario = 'SOLUCION';
                $query_update .= ", FechaTerminacion = GETDATE()";
                
                // Registrar solución
                $stmt_sol = $conn->prepare("INSERT INTO INCIDENCIA_SOLUCION 
                                          (ID_Incidencia, Descripcion, FechaRegistro, ID_Usuario) 
                                          VALUES (?, ?, GETDATE(), ?)");
                $stmt_sol->execute([$incidencia_id, $solucion, $_SESSION['user_id']]);
            }
        }
        
        // Continuar solo si no hay errores
        if (!isset($error)) {
            // Completar y ejecutar actualización
            $query_update .= " WHERE ID = ?";
            $params[] = $incidencia_id;
            $stmt_update = $conn->prepare($query_update);
            $stmt_update->execute($params);
            
            // Registrar historial y comentario
            $stmt_hist = $conn->prepare("INSERT INTO INCIDENCIA_HISTORIAL 
                                       (ID_Incidencia, ID_EstadoAnterior, ID_EstadoNuevo, ID_Usuario, FechaCambio) 
                                       VALUES (?, ?, ?, ?, GETDATE())");
            $stmt_hist->execute([$incidencia_id, $incidencia['ID_Stat'], $nuevo_estado, $_SESSION['user_id']]);
            
            $stmt_comment = $conn->prepare("INSERT INTO INCIDENCIA_COMENTARIO 
                                          (ID_Incidencia, ID_Usuario, Comentario, TipoComentario, FechaRegistro, Publico) 
                                          VALUES (?, ?, ?, ?, GETDATE(), 1)");
            $stmt_comment->execute([$incidencia_id, $_SESSION['user_id'], $comentario, $tipo_comentario]);
            
            // Redireccionar tras éxito
            header("Location: ver-incidencia.php?id=$incidencia_id&success=updated");
            exit;
        }
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
                            <input type="text" class="form-control" id="estado_actual" value="<?php echo htmlspecialchars($incidencia['Estado']); ?>" readonly>
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
                        <textarea class="form-control" id="comentario" name="comentario" rows="3" required 
                                 placeholder="Proporcione detalles sobre la actualización..."></textarea>
                    </div>
                    
                    <div id="seccion_solucion" class="mb-3" style="display: none;">
                        <label for="solucion" class="form-label">Descripción de la Solución: *</label>
                        <textarea class="form-control" id="solucion" name="solucion" rows="4" 
                                 placeholder="Describa detalladamente la solución aplicada..."></textarea>
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
                            <?php if ($incidencia['CI_Tipo']): ?>
                                <span class="badge bg-info"><?php echo htmlspecialchars($incidencia['CI_Tipo']); ?></span>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($incidencia['CI_Nombre']); ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Prioridad:</th>
                        <td>
                            <?php 
                            $prioridad = htmlspecialchars($incidencia['Prioridad']);
                            $badgeClass = 'bg-info';
                            
                            if ($prioridad === 'Crítica') $badgeClass = 'bg-danger';
                            elseif ($prioridad === 'Alta') $badgeClass = 'bg-warning text-dark';
                            elseif ($prioridad === 'Media') $badgeClass = 'bg-primary';
                            
                            echo "<span class='badge $badgeClass'>$prioridad</span>";
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Reportado por:</th>
                        <td><?php echo htmlspecialchars($incidencia['Reportado_Por_Nombre'] ?? 'Desconocido'); ?></td>
                    </tr>
                    <tr>
                        <th>Fecha reporte:</th>
                        <td><?php echo date('d/m/Y H:i', strtotime($incidencia['FechaInicio'])); ?></td>
                    </tr>
                </table>
                
                <hr>
                
                <h6>Descripción del problema:</h6>
                <p><?php echo nl2br(htmlspecialchars($incidencia['Descripcion'])); ?></p>
                
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
                                    echo $respuesta['Respuesta'] == 'SI' ? 
                                         '<span class="badge bg-success">Sí</span>' : 
                                         '<span class="badge bg-danger">No</span>';
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
                                    <strong><?php echo htmlspecialchars($comentario['NombreEmpleado'] ?? 'Usuario'); ?></strong>
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

<?php require_once '../../includes/footer.php'; ?>