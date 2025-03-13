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

// Obtener parámetros de filtrado
$tipo_ci = isset($_GET['tipo_ci']) ? $_GET['tipo_ci'] : '';
$ubicacion = isset($_GET['ubicacion']) ? $_GET['ubicacion'] : '';
$busqueda = isset($_GET['busqueda']) ? $_GET['busqueda'] : '';

// Construir la consulta base
$query = "SELECT ci.ID, ci.Nombre, ci.Descripcion, ci.NumSerie, ci.FechaAdquisicion, 
          t.Nombre as TipoCI, p.Nombre as Proveedor, e.Nombre as Encargado,
          l.Nombre as Localizacion, ed.Nombre as Edificio
          FROM CI ci
          LEFT JOIN TIPO_CI t ON ci.ID_TipoCI = t.ID
          LEFT JOIN PROVEEDOR p ON ci.ID_Proveedor = p.ID
          LEFT JOIN EMPLEADO e ON ci.ID_Encargado = e.ID
          LEFT JOIN LOCALIZACION l ON ci.ID_Localizacion = l.ID
          LEFT JOIN EDIFICIO ed ON l.ID_Edificio = ed.ID
          WHERE 1=1";

// Añadir condiciones de filtrado
$params = array();

if (!empty($tipo_ci)) {
    $query .= " AND ci.ID_TipoCI = ?";
    $params[] = $tipo_ci;
}

if (!empty($ubicacion)) {
    $query .= " AND l.ID_Edificio = ?";
    $params[] = $ubicacion;
}

if (!empty($busqueda)) {
    $query .= " AND (ci.Nombre LIKE ? OR ci.NumSerie LIKE ? OR ci.Descripcion LIKE ?)";
    $busqueda_param = "%$busqueda%";
    $params[] = $busqueda_param;
    $params[] = $busqueda_param;
    $params[] = $busqueda_param;
}

// Ordenar por ID
$query .= " ORDER BY ci.ID DESC";

// Preparar y ejecutar la consulta
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->execute($params);
} else {
    $stmt->execute();
}

// Consultas para obtener datos de filtros
$tipoStmt = $conn->prepare("SELECT ID, Nombre FROM TIPO_CI ORDER BY Nombre");
$tipoStmt->execute();

$ubicacionStmt = $conn->prepare("SELECT ID, Nombre FROM EDIFICIO ORDER BY Nombre");
$ubicacionStmt->execute();
?>

<!-- Título de la página -->
<h1 class="h2">Gestión de Elementos de Configuración</h1>

<!-- Botón para agregar nuevo CI -->
<div class="row mb-4">
    <div class="col-12">
        <a href="agregar-ci.php" class="btn btn-success">
            <i class="fas fa-plus-circle me-2"></i>Agregar Nuevo Elemento
        </a>
    </div>
</div>

<!-- Filtros -->
<div class="row mb-4">
    <div class="col-12">
        <div class="form-container">
            <h5 class="mb-3">Filtros</h5>
            <form action="" method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="tipo_ci" class="form-label">Tipo de CI</label>
                    <select class="form-select" id="tipo_ci" name="tipo_ci">
                        <option value="">Todos</option>
                        <?php while ($tipo = $tipoStmt->fetch(PDO::FETCH_ASSOC)): ?>
                            <option value="<?php echo $tipo['ID']; ?>" <?php echo ($tipo_ci == $tipo['ID']) ? 'selected' : ''; ?>>
                                <?php echo $tipo['Nombre']; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="ubicacion" class="form-label">Ubicación</label>
                    <select class="form-select" id="ubicacion" name="ubicacion">
                        <option value="">Todas</option>
                        <?php while ($ubicacion_item = $ubicacionStmt->fetch(PDO::FETCH_ASSOC)): ?>
                            <option value="<?php echo $ubicacion_item['ID']; ?>" <?php echo ($ubicacion == $ubicacion_item['ID']) ? 'selected' : ''; ?>>
                                <?php echo $ubicacion_item['Nombre']; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="busqueda" class="form-label">Búsqueda</label>
                    <input type="text" class="form-control" id="busqueda" name="busqueda" placeholder="Nombre, número de serie, descripción..." value="<?php echo htmlspecialchars($busqueda); ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Tabla de CIs -->
<div class="row">
    <div class="col-12">
        <div class="table-container">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Tipo</th>
                        <th>Número de Serie</th>
                        <th>Ubicación</th>
                        <th>Encargado</th>
                        <th>Fecha Adquisición</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($stmt->rowCount() > 0): ?>
                        <?php while ($ci = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                            <tr>
                                <td><?php echo $ci['ID']; ?></td>
                                <td><?php echo htmlspecialchars($ci['Nombre']); ?></td>
                                <td><?php echo htmlspecialchars($ci['TipoCI']); ?></td>
                                <td><?php echo htmlspecialchars($ci['NumSerie']); ?></td>
                                <td><?php echo htmlspecialchars($ci['Edificio'] . ' - ' . $ci['Localizacion']); ?></td>
                                <td><?php echo htmlspecialchars($ci['Encargado']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($ci['FechaAdquisicion'])); ?></td>
                                <td>
                                    <a href="ver-ci.php?id=<?php echo $ci['ID']; ?>" class="btn btn-sm btn-info" title="Ver detalles">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="editar-ci.php?id=<?php echo $ci['ID']; ?>" class="btn btn-sm btn-warning" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if ($_SESSION['permisos']['admin']): ?>
                                    <a href="javascript:void(0);" onclick="confirmarEliminacion(<?php echo $ci['ID']; ?>)" class="btn btn-sm btn-danger" title="Eliminar">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center">No se encontraron elementos de configuración.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Script para confirmación de eliminación -->
<script>
function confirmarEliminacion(id) {
    if (confirm('¿Está seguro de que desea eliminar este elemento de configuración?')) {
        window.location.href = 'eliminar-ci.php?id=' + id;
    }
}
</script>

<?php
// Incluir el pie de página
require_once '../../includes/footer.php';
?>