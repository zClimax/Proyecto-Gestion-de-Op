<?php
// Incluir el encabezado
require_once '../../includes/header.php';

// Verificar permiso de gestión de CIs
check_permission('gestionar_ci');

// Incluir configuración de base de datos
require_once '../../config/database.php';

// Verificar si se proporcionó el ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    // Redireccionar a la lista
    header("Location: gestion-ci.php?error=missing_id");
    exit;
}

$ci_id = $_GET['id'];

// Conexión a la base de datos
$database = new Database();
$conn = $database->getConnection();

// Obtener datos del CI
try {
    $query = "SELECT ci.ID, ci.Nombre, ci.Descripcion, ci.NumSerie, ci.FechaAdquisicion, 
              ci.ID_TipoCI, ci.ID_Localizacion, ci.ID_Encargado, ci.ID_Proveedor,
              l.ID_Edificio
              FROM CI ci
              LEFT JOIN LOCALIZACION l ON ci.ID_Localizacion = l.ID
              WHERE ci.ID = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$ci_id]);
    
    if ($stmt->rowCount() == 0) {
        // CI no encontrado
        header("Location: gestion-ci.php?error=not_found");
        exit;
    }
    
    $ci = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener listas para los selectores
    $tipoStmt = $conn->prepare("SELECT ID, Nombre FROM TIPO_CI ORDER BY Nombre");
    $tipoStmt->execute();
    
    $proveedorStmt = $conn->prepare("SELECT ID, Nombre FROM PROVEEDOR ORDER BY Nombre");
    $proveedorStmt->execute();
    
    $edificioStmt = $conn->prepare("SELECT ID, Nombre FROM EDIFICIO ORDER BY Nombre");
    $edificioStmt->execute();
    
    $empleadoStmt = $conn->prepare("SELECT ID, Nombre FROM EMPLEADO ORDER BY Nombre");
    $empleadoStmt->execute();
    
    // Obtener localizaciones del edificio seleccionado
    $localizacionStmt = $conn->prepare("SELECT ID, Nombre FROM LOCALIZACION WHERE ID_Edificio = ? ORDER BY NumPlanta, Nombre");
    $localizacionStmt->execute([$ci['ID_Edificio']]);
    
    // Procesar el formulario si se envió
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            // Recoger datos del formulario
            $nombre = $_POST['nombre'];
            $descripcion = $_POST['descripcion'];
            $numSerie = $_POST['num_serie'];
            $fechaAdquisicion = $_POST['fecha_adquisicion'];
            $tipoCI = $_POST['tipo_ci'];
            $localizacion = $_POST['localizacion'];
            $encargado = $_POST['encargado'];
            $proveedor = $_POST['proveedor'];
            
            // Preparar la consulta SQL
            $updateQuery = "UPDATE CI SET 
                           Nombre = ?, 
                           Descripcion = ?, 
                           NumSerie = ?, 
                           FechaAdquisicion = ?, 
                           ID_TipoCI = ?, 
                           ID_Localizacion = ?, 
                           ID_Encargado = ?, 
                           ID_Proveedor = ?,
                           ModifiedBy = ?,
                           ModifiedDate = GETDATE()
                           WHERE ID = ?";
            
            $updateStmt = $conn->prepare($updateQuery);
            
            // Vincular parámetros
            $updateStmt->bindParam(1, $nombre);
            $updateStmt->bindParam(2, $descripcion);
            $updateStmt->bindParam(3, $numSerie);
            $updateStmt->bindParam(4, $fechaAdquisicion);
            $updateStmt->bindParam(5, $tipoCI);
            $updateStmt->bindParam(6, $localizacion);
            $updateStmt->bindParam(7, $encargado);
            $updateStmt->bindParam(8, $proveedor);
            $updateStmt->bindParam(9, $_SESSION['user_id']);
            $updateStmt->bindParam(10, $ci_id);
            
            // Ejecutar la consulta
            if ($updateStmt->execute()) {
                // Redireccionar a la página de gestión de CIs con mensaje de éxito
                header("Location: gestion-ci.php?success=2");
                exit;
            } else {
                $error = "Error al actualizar el elemento de configuración.";
            }
        } catch (PDOException $e) {
            $error = "Error en la base de datos: " . $e->getMessage();
        }
    }
    
} catch (PDOException $e) {
    $error = "Error en la base de datos: " . $e->getMessage();
}
?>

<!-- Título de la página -->
<h1 class="h2">Editar Elemento de Configuración</h1>

<!-- Formulario para editar CI -->
<div class="row">
    <div class="col-12">
        <div class="form-container">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form action="" method="POST" id="formCI">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="nombre" class="form-label">Nombre *</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" required maxlength="20" value="<?php echo htmlspecialchars($ci['Nombre']); ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="tipo_ci" class="form-label">Tipo de CI *</label>
                        <select class="form-select" id="tipo_ci" name="tipo_ci" required>
                            <option value="">Seleccionar tipo...</option>
                            <?php while ($tipo = $tipoStmt->fetch(PDO::FETCH_ASSOC)): ?>
                                <option value="<?php echo $tipo['ID']; ?>" <?php echo ($ci['ID_TipoCI'] == $tipo['ID']) ? 'selected' : ''; ?>>
                                    <?php echo $tipo['Nombre']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-12">
                        <label for="descripcion" class="form-label">Descripción *</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" required maxlength="40"><?php echo htmlspecialchars($ci['Descripcion']); ?></textarea>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="num_serie" class="form-label">Número de Serie *</label>
                        <input type="text" class="form-control" id="num_serie" name="num_serie" required maxlength="30" value="<?php echo htmlspecialchars($ci['NumSerie']); ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="fecha_adquisicion" class="form-label">Fecha de Adquisición *</label>
                        <input type="date" class="form-control" id="fecha_adquisicion" name="fecha_adquisicion" required value="<?php echo date('Y-m-d', strtotime($ci['FechaAdquisicion'])); ?>">
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="edificio" class="form-label">Edificio *</label>
                        <select class="form-select" id="edificio" name="edificio" required>
                            <option value="">Seleccionar edificio...</option>
                            <?php while ($edificio = $edificioStmt->fetch(PDO::FETCH_ASSOC)): ?>
                                <option value="<?php echo $edificio['ID']; ?>" <?php echo ($ci['ID_Edificio'] == $edificio['ID']) ? 'selected' : ''; ?>>
                                    <?php echo $edificio['Nombre']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="localizacion" class="form-label">Localización *</label>
                        <select class="form-select" id="localizacion" name="localizacion" required>
                            <option value="">Seleccionar localización...</option>
                            <?php while ($localizacion = $localizacionStmt->fetch(PDO::FETCH_ASSOC)): ?>
                                <option value="<?php echo $localizacion['ID']; ?>" <?php echo ($ci['ID_Localizacion'] == $localizacion['ID']) ? 'selected' : ''; ?>>
                                    <?php echo $localizacion['Nombre']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="encargado" class="form-label">Encargado *</label>
                        <select class="form-select" id="encargado" name="encargado" required>
                            <option value="">Seleccionar encargado...</option>
                            <?php while ($empleado = $empleadoStmt->fetch(PDO::FETCH_ASSOC)): ?>
                                <option value="<?php echo $empleado['ID']; ?>" <?php echo ($ci['ID_Encargado'] == $empleado['ID']) ? 'selected' : ''; ?>>
                                    <?php echo $empleado['Nombre']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="proveedor" class="form-label">Proveedor *</label>
                        <select class="form-select" id="proveedor" name="proveedor" required>
                            <option value="">Seleccionar proveedor...</option>
                            <?php while ($proveedor = $proveedorStmt->fetch(PDO::FETCH_ASSOC)): ?>
                                <option value="<?php echo $proveedor['ID']; ?>" <?php echo ($ci['ID_Proveedor'] == $proveedor['ID']) ? 'selected' : ''; ?>>
                                    <?php echo $proveedor['Nombre']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-12">
                        <hr>
                        <button type="submit" class="btn btn-success">Guardar Cambios</button>
                        <a href="gestion-ci.php" class="btn btn-secondary">Cancelar</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Script para cargar localizaciones basadas en el edificio seleccionado -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Referencia al selector de edificio
    const edificioSelect = document.getElementById('edificio');
    const localizacionSelect = document.getElementById('localizacion');
    const localizacionValorActual = '<?php echo $ci['ID_Localizacion']; ?>';
    
    // Función para cargar localizaciones
    function cargarLocalizaciones() {
        const edificioId = edificioSelect.value;
        
        // Resetear selector de localización
        localizacionSelect.innerHTML = '<option value="">Seleccionar localización...</option>';
        
        if (edificioId) {
            // Realizar petición AJAX para obtener localizaciones
            fetch('../../api/get_localizaciones.php?edificio_id=' + edificioId)
                .then(response => response.json())
                .then(data => {
                    if (data.length > 0) {
                        // Llenar selector de localizaciones
                        data.forEach(loc => {
                            const option = document.createElement('option');
                            option.value = loc.ID;
                            option.textContent = loc.Nombre;
                            
                            // Seleccionar la opción actual
                            if (loc.ID == localizacionValorActual) {
                                option.selected = true;
                            }
                            
                            localizacionSelect.appendChild(option);
                        });
                    } else {
                        localizacionSelect.innerHTML = '<option value="">No hay localizaciones disponibles</option>';
                    }
                })
                .catch(error => {
                    console.error('Error al cargar localizaciones:', error);
                    localizacionSelect.innerHTML = '<option value="">Error al cargar localizaciones</option>';
                });
        }
    }
    
    // Asignar evento al cambio de edificio
    edificioSelect.addEventListener('change', cargarLocalizaciones);
});
</script>

<?php
// Incluir el pie de página
require_once '../../includes/footer.php';
?>