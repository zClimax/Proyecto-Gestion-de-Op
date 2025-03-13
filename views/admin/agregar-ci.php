<?php
// Incluir el encabezado
require_once '../../includes/header.php';

// Verificar permiso de gestión de CIs
check_permission('gestionar_ci');

// Incluir configuración de base de datos
require_once '../../config/database.php';

// Conexión a la base de datos
$database = new Database();
$conn = $database->getConnection();

// Obtener listas para los selectores
$tipoStmt = $conn->prepare("SELECT ID, Nombre FROM TIPO_CI ORDER BY Nombre");
$tipoStmt->execute();

$proveedorStmt = $conn->prepare("SELECT ID, Nombre FROM PROVEEDOR ORDER BY Nombre");
$proveedorStmt->execute();

$edificioStmt = $conn->prepare("SELECT ID, Nombre FROM EDIFICIO ORDER BY Nombre");
$edificioStmt->execute();

$empleadoStmt = $conn->prepare("SELECT ID, Nombre FROM EMPLEADO ORDER BY Nombre");
$empleadoStmt->execute();

// Variable para almacenar localizaciones (se llenará mediante AJAX)
$localizaciones = [];

// Verificar si se envió el formulario
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
        $query = "INSERT INTO CI (Nombre, Descripcion, NumSerie, FechaAdquisicion, ID_TipoCI, ID_Localizacion, ID_Encargado, ID_Proveedor, CreatedBy, CreatedDate) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE())";
        
        $stmt = $conn->prepare($query);
        
        // Vincular parámetros
        $stmt->bindParam(1, $nombre);
        $stmt->bindParam(2, $descripcion);
        $stmt->bindParam(3, $numSerie);
        $stmt->bindParam(4, $fechaAdquisicion);
        $stmt->bindParam(5, $tipoCI);
        $stmt->bindParam(6, $localizacion);
        $stmt->bindParam(7, $encargado);
        $stmt->bindParam(8, $proveedor);
        $stmt->bindParam(9, $_SESSION['user_id']);
        
        // Ejecutar la consulta
        if ($stmt->execute()) {
            // Redireccionar a la página de gestión de CIs con mensaje de éxito
            header("Location: gestion-ci.php?success=1");
            exit;
        } else {
            $error = "Error al guardar el elemento de configuración.";
        }
    } catch (PDOException $e) {
        $error = "Error en la base de datos: " . $e->getMessage();
    }
}
?>

<!-- Título de la página -->
<h1 class="h2">Agregar Nuevo Elemento de Configuración</h1>

<!-- Formulario para agregar CI -->
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
                        <input type="text" class="form-control" id="nombre" name="nombre" required maxlength="20">
                    </div>
                    <div class="col-md-6">
                        <label for="tipo_ci" class="form-label">Tipo de CI *</label>
                        <select class="form-select" id="tipo_ci" name="tipo_ci" required>
                            <option value="">Seleccionar tipo...</option>
                            <?php while ($tipo = $tipoStmt->fetch(PDO::FETCH_ASSOC)): ?>
                                <option value="<?php echo $tipo['ID']; ?>"><?php echo $tipo['Nombre']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-12">
                        <label for="descripcion" class="form-label">Descripción *</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" required maxlength="40"></textarea>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="num_serie" class="form-label">Número de Serie *</label>
                        <input type="text" class="form-control" id="num_serie" name="num_serie" required maxlength="30">
                    </div>
                    <div class="col-md-6">
                        <label for="fecha_adquisicion" class="form-label">Fecha de Adquisición *</label>
                        <input type="date" class="form-control" id="fecha_adquisicion" name="fecha_adquisicion" required>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="edificio" class="form-label">Edificio *</label>
                        <select class="form-select" id="edificio" name="edificio" required>
                            <option value="">Seleccionar edificio...</option>
                            <?php while ($edificio = $edificioStmt->fetch(PDO::FETCH_ASSOC)): ?>
                                <option value="<?php echo $edificio['ID']; ?>"><?php echo $edificio['Nombre']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="localizacion" class="form-label">Localización *</label>
                        <select class="form-select" id="localizacion" name="localizacion" required disabled>
                            <option value="">Primero seleccione un edificio...</option>
                        </select>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="encargado" class="form-label">Encargado *</label>
                        <select class="form-select" id="encargado" name="encargado" required>
                            <option value="">Seleccionar encargado...</option>
                            <?php while ($empleado = $empleadoStmt->fetch(PDO::FETCH_ASSOC)): ?>
                                <option value="<?php echo $empleado['ID']; ?>"><?php echo $empleado['Nombre']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="proveedor" class="form-label">Proveedor *</label>
                        <select class="form-select" id="proveedor" name="proveedor" required>
                            <option value="">Seleccionar proveedor...</option>
                            <?php while ($proveedor = $proveedorStmt->fetch(PDO::FETCH_ASSOC)): ?>
                                <option value="<?php echo $proveedor['ID']; ?>"><?php echo $proveedor['Nombre']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-12">
                        <hr>
                        <button type="submit" class="btn btn-success">Guardar</button>
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
    
    // Función para cargar localizaciones
    function cargarLocalizaciones() {
        const edificioId = edificioSelect.value;
        
        // Resetear selector de localización
        localizacionSelect.innerHTML = '<option value="">Seleccionar localización...</option>';
        localizacionSelect.disabled = true;
        
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
                            localizacionSelect.appendChild(option);
                        });
                        
                        // Habilitar selector
                        localizacionSelect.disabled = false;
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