<?php
// Incluir el encabezado
require_once '../../includes/header.php';

// Verificar permiso de gestión de CIs
check_permission('gestionar_ci');
?>

<!-- Título de la página -->
<h1 class="h2">Panel de Coordinador</h1>

<div class="row my-4">
    <!-- Tarjeta de CIs -->
    <div class="col-md-6">
        <div class="card card-dashboard">
            <div class="card-body">
                <h5 class="card-title">Elementos de Configuración</h5>
                <p class="card-text">Gestión de elementos de configuración asignados a su área.</p>
                <a href="gestion-ci.php" class="btn btn-primary">Gestionar CIs</a>
            </div>
        </div>
    </div>
    
    <!-- Tarjeta de Incidencias -->
    <div class="col-md-6">
        <div class="card card-dashboard">
            <div class="card-body">
                <h5 class="card-title">Incidencias</h5>
                <p class="card-text">Gestión y asignación de incidencias reportadas.</p>
                <a href="incidencias.php" class="btn btn-primary">Gestionar Incidencias</a>
            </div>
        </div>
    </div>
</div>

<!-- Últimas incidencias -->
<div class="row my-4">
    <div class="col-12">
        <div class="table-container">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5>Últimas Incidencias</h5>
                <a href="incidencias.php" class="btn btn-sm btn-outline-primary">Ver todas</a>
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
                            <a href="incidencia-detalle.php?id=1001" class="btn btn-sm btn-info">Ver</a>
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
                            <a href="incidencia-detalle.php?id=1002" class="btn btn-sm btn-info">Ver</a>
                        </td>
                    </tr>
                    <tr>
                        <td>1003</td>
                        <td>Switch no responde</td>
                        <td>SW-CORP-02</td>
                        <td>12/03/2025</td>
                        <td><span class="badge bg-danger">Alta</span></td>
                        <td><span class="badge bg-primary">Asignada</span></td>
                        <td>
                            <a href="incidencia-detalle.php?id=1003" class="btn btn-sm btn-info">Ver</a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Resumen de CIs por tipo -->
<div class="row my-4">
    <div class="col-12">
        <div class="table-container">
            <h5>Elementos de Configuración por Tipo</h5>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Tipo de CI</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Servidores</td>
                        <td>5</td>
                    </tr>
                    <tr>
                        <td>Computadoras</td>
                        <td>25</td>
                    </tr>
                    <tr>
                        <td>Impresoras</td>
                        <td>10</td>
                    </tr>
                    <tr>
                        <td>Equipos de Red</td>
                        <td>15</td>
                    </tr>
                    <tr>
                        <td>Terminales Punto de Venta</td>
                        <td>30</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
// Incluir el pie de página
require_once '../../includes/footer.php';
?>