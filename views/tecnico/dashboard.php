<?php
// Incluir el encabezado
require_once '../../includes/header.php';

// Verificar permiso de gestión de incidencias
check_permission('gestionar_incidencias');
?>

<!-- Título de la página -->
<h1 class="h2">Panel de Técnico</h1>

<div class="row my-4">
    <!-- Tarjeta de incidencias asignadas -->
    <div class="col-md-6">
        <div class="card card-dashboard">
            <div class="card-body">
                <h5 class="card-title">Incidencias Asignadas</h5>
                <p class="card-text">Gestión de incidencias asignadas a usted.</p>
                <a href="mis-incidencias.php" class="btn btn-primary">Ver Mis Incidencias</a>
            </div>
        </div>
    </div>
    
    <!-- Tarjeta de registro de soluciones -->
    <div class="col-md-6">
        <div class="card card-dashboard">
            <div class="card-body">
                <h5 class="card-title">Registro de Soluciones</h5>
                <p class="card-text">Documentación de soluciones aplicadas.</p>
                <a href="soluciones.php" class="btn btn-primary">Registrar Solución</a>
            </div>
        </div>
    </div>
</div>

<!-- Incidencias asignadas -->
<div class="row my-4">
    <div class="col-12">
        <div class="table-container">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5>Mis Incidencias Asignadas</h5>
                <a href="mis-incidencias.php" class="btn btn-sm btn-outline-primary">Ver todas</a>
            </div>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Descripción</th>
                        <th>CI Afectado</th>
                        <th>Fecha</th>
                        <th>Prioridad</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1001</td>
                        <td>Error en terminal punto de venta</td>
                        <td>TPV-SUC15-01</td>
                        <td>10/03/2025</td>
                        <td><span class="badge bg-danger">Alta</span></td>
                        <td><span class="badge bg-warning text-dark">En proceso</span></td>
                        <td>
                            <a href="actualizar-incidencia.php?id=1001" class="btn btn-sm btn-primary">Actualizar</a>
                        </td>
                    </tr>
                    <tr>
                        <td>1002</td>
                        <td>Impresora sin tóner</td>
                        <td>IMP-CEDIS-05</td>
                        <td>11/03/2025</td>
                        <td><span class="badge bg-warning text-dark">Media</span></td>
                        <td><span class="badge bg-primary">Asignada</span></td>
                        <td>
                            <a href="actualizar-incidencia.php?id=1002" class="btn btn-sm btn-primary">Actualizar</a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Formulario rápido de actualización -->
<div class="row my-4">
    <div class="col-12">
        <div class="form-container">
            <h5>Actualización Rápida de Incidencia</h5>
            <form action="procesar-actualizacion.php" method="POST">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="id_incidencia" class="form-label">ID Incidencia</label>
                        <input type="text" class="form-control" id="id_incidencia" name="id_incidencia" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="estado" class="form-label">Nuevo Estado</label>
                        <select class="form-select" id="estado" name="estado" required>
                            <option value="">Seleccionar...</option>
                            <option value="3">En proceso</option>
                            <option value="4">En espera</option>
                            <option value="5">Resuelta</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="comentario" class="form-label">Comentario</label>
                        <input type="text" class="form-control" id="comentario" name="comentario" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <button type="submit" class="btn btn-success">Actualizar Estado</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Incluir el pie de página
require_once '../../includes/footer.php';
?>