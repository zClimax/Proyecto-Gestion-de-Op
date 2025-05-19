<?php
/**
 * Modelo Problema - Gestiona la información y lógica relacionada con los problemas
 * Un problema es la causa raíz desconocida de uno o más incidentes 
 */
class Problema {
    // Conexión a la base de datos y nombre de la tabla
    private $conn;
    private $table_name = "PROBLEMA";
    
    // Propiedades del objeto
    public $id;
    public $titulo;
    public $descripcion;
    public $fecha_identificacion;
    public $fecha_resolucion;
    public $id_prioridad;
    public $id_categoria;
    public $id_impacto;
    public $id_stat;
    public $id_responsable;
    public $created_by;
    public $created_date;
    public $modified_by;
    public $modified_date;
    
    // Propiedades relacionadas
    public $prioridad;
    public $categoria;
    public $impacto;
    public $estado;
    public $responsable_nombre;
    
    // Constructor con conexión a la base de datos
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Obtener todos los problemas con filtros opcionales
     * @param array $filtros Array asociativo con los filtros a aplicar
     * @return PDOStatement Resultado de la consulta
     */
    public function getAll($filtros = []) {
        // Construir la consulta base
        $query = "SELECT p.ID, p.Titulo, p.Descripcion, p.FechaIdentificacion, p.FechaResolucion,
                         pri.Descripcion as Prioridad, cat.Nombre as Categoria, 
                         imp.Descripcion as Impacto, s.Descripcion as Estado,
                         e.Nombre as ResponsableNombre
                  FROM " . $this->table_name . " p
                  LEFT JOIN PRIORIDAD pri ON p.ID_Prioridad = pri.ID
                  LEFT JOIN CATEGORIA_PROBLEMA cat ON p.ID_Categoria = cat.ID
                  LEFT JOIN IMPACTO imp ON p.ID_Impacto = imp.ID
                  LEFT JOIN ESTATUS_PROBLEMA s ON p.ID_Stat = s.ID
                  LEFT JOIN EMPLEADO e ON p.ID_Responsable = e.ID
                  WHERE 1=1";
        
        $params = [];
        
        // Aplicar filtros
        if(isset($filtros['estado']) && !empty($filtros['estado'])) {
            $query .= " AND p.ID_Stat = ?";
            $params[] = $filtros['estado'];
        }
        
        if(isset($filtros['prioridad']) && !empty($filtros['prioridad'])) {
            $query .= " AND p.ID_Prioridad = ?";
            $params[] = $filtros['prioridad'];
        }
        
        if(isset($filtros['categoria']) && !empty($filtros['categoria'])) {
            $query .= " AND p.ID_Categoria = ?";
            $params[] = $filtros['categoria'];
        }
        
        if(isset($filtros['responsable']) && !empty($filtros['responsable'])) {
            $query .= " AND p.ID_Responsable = ?";
            $params[] = $filtros['responsable'];
        }
        
        if(isset($filtros['busqueda']) && !empty($filtros['busqueda'])) {
            $query .= " AND (p.Titulo LIKE ? OR p.Descripcion LIKE ?)";
            $busqueda = "%" . $filtros['busqueda'] . "%";
            $params[] = $busqueda;
            $params[] = $busqueda;
        }
        
        // Ordenar por los más recientes primero
        $query .= " ORDER BY p.FechaIdentificacion DESC";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        $stmt->execute($params);
        
        return $stmt;
    }
    
    /**
     * Obtener un problema por su ID
     * @param integer $id ID del problema
     * @return boolean True si se encontró el problema
     */
    public function getById($id) {
        $query = "SELECT p.ID, p.Titulo, p.Descripcion, p.FechaIdentificacion, p.FechaResolucion,
        p.ID_Prioridad, p.ID_Categoria, p.ID_Impacto, p.ID_Stat, p.ID_Responsable, 
        p.CreatedBy, p.CreatedDate, p.ModifiedBy, p.ModifiedDate,
        pri.Descripcion as Prioridad, cat.Nombre as Categoria, 
        imp.Descripcion as Impacto, s.Descripcion as Estado,
        e.Nombre as ResponsableNombre
 FROM [ControlIncidenciasDB].[dbo].[" . $this->table_name . "] p
 LEFT JOIN [ControlIncidenciasDB].[dbo].[PRIORIDAD] pri ON p.ID_Prioridad = pri.ID
 LEFT JOIN [ControlIncidenciasDB].[dbo].[CATEGORIA_PROBLEMA] cat ON p.ID_Categoria = cat.ID
 LEFT JOIN [ControlIncidenciasDB].[dbo].[IMPACTO] imp ON p.ID_Impacto = imp.ID
 LEFT JOIN [ControlIncidenciasDB].[dbo].[ESTATUS_PROBLEMA] s ON p.ID_Stat = s.ID
 LEFT JOIN [ControlIncidenciasDB].[dbo].[EMPLEADO] e ON p.ID_Responsable = e.ID
 WHERE p.ID = ?";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        $stmt->execute([$id]);
        
        // Verificar si se encontró el problema
        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Asignar valores a las propiedades
            $this->id = $row['ID'];
            $this->titulo = $row['Titulo'];
            $this->descripcion = $row['Descripcion'];
            $this->fecha_identificacion = $row['FechaIdentificacion'];
            $this->fecha_resolucion = $row['FechaResolucion'];
            $this->id_prioridad = $row['ID_Prioridad'];
            $this->id_categoria = $row['ID_Categoria'];
            $this->id_impacto = $row['ID_Impacto'];
            $this->id_stat = $row['ID_Stat'];
            $this->id_responsable = $row['ID_Responsable'];
            $this->created_by = $row['CreatedBy'];
            $this->created_date = $row['CreatedDate'];
            $this->modified_by = $row['ModifiedBy'];
            $this->modified_date = $row['ModifiedDate'];
            
            // Propiedades relacionadas
            $this->prioridad = $row['Prioridad'];
            $this->categoria = $row['Categoria'];
            $this->impacto = $row['Impacto'];
            $this->estado = $row['Estado'];
            $this->responsable_nombre = $row['ResponsableNombre'];
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Crear un nuevo problema
     * @return boolean True si se creó correctamente
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                  (Titulo, Descripcion, FechaIdentificacion, ID_Prioridad, ID_Categoria, 
                   ID_Impacto, ID_Stat, ID_Responsable, CreatedBy, CreatedDate) 
                  VALUES (?, ?, GETDATE(), ?, ?, ?, ?, ?, ?, GETDATE())";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        $result = $stmt->execute([
            $this->titulo,
            $this->descripcion,
            $this->id_prioridad,
            $this->id_categoria,
            $this->id_impacto,
            $this->id_stat,
            $this->id_responsable,
            $this->created_by
        ]);
        
        if($result) {
            // Obtener el último ID insertado
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        
        return false;
    }
    
    /**
     * Actualizar un problema existente
     * @return boolean True si se actualizó correctamente
     */
    public function update() {
        $query = "UPDATE " . $this->table_name . " 
                  SET Titulo = ?, Descripcion = ?, ID_Prioridad = ?, 
                      ID_Categoria = ?, ID_Impacto = ?, ID_Stat = ?,
                      ID_Responsable = ?, ModifiedBy = ?, ModifiedDate = GETDATE() 
                  WHERE ID = ?";
        
        // Si el problema está resuelto, registrar la fecha de resolución
        if($this->id_stat == 4) { // 4 = Resuelto
            $query = "UPDATE " . $this->table_name . " 
                      SET Titulo = ?, Descripcion = ?, ID_Prioridad = ?, 
                          ID_Categoria = ?, ID_Impacto = ?, ID_Stat = ?,
                          ID_Responsable = ?, FechaResolucion = GETDATE(),
                          ModifiedBy = ?, ModifiedDate = GETDATE() 
                      WHERE ID = ?";
        }
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        return $stmt->execute([
            $this->titulo,
            $this->descripcion,
            $this->id_prioridad,
            $this->id_categoria,
            $this->id_impacto,
            $this->id_stat,
            $this->id_responsable,
            $this->modified_by,
            $this->id
        ]);
    }
    
    /**
     * Eliminar un problema
     * @param integer $id ID del problema a eliminar
     * @return boolean True si se eliminó correctamente
     */
    public function delete($id) {
        // Primero verificar si hay relaciones con incidencias
        $incidenciasQuery = "SELECT COUNT(*) as total FROM PROBLEMA_INCIDENCIA WHERE ID_Problema = ?";
        $incStmt = $this->conn->prepare($incidenciasQuery);
        $incStmt->execute([$id]);
        $result = $incStmt->fetch(PDO::FETCH_ASSOC);
        
        if($result['total'] > 0) {
            // Eliminar primero las relaciones con incidencias
            $deleteRelacionesQuery = "DELETE FROM PROBLEMA_INCIDENCIA WHERE ID_Problema = ?";
            $deleteRelStmt = $this->conn->prepare($deleteRelacionesQuery);
            $deleteRelStmt->execute([$id]);
        }
        
        // Eliminar los comentarios asociados al problema
        $comentariosQuery = "DELETE FROM PROBLEMA_COMENTARIO WHERE ID_Problema = ?";
        $comentariosStmt = $this->conn->prepare($comentariosQuery);
        $comentariosStmt->execute([$id]);
        
        // Eliminar el historial de estados del problema
        $historialQuery = "DELETE FROM PROBLEMA_HISTORIAL WHERE ID_Problema = ?";
        $historialStmt = $this->conn->prepare($historialQuery);
        $historialStmt->execute([$id]);
        
        // Eliminar las soluciones propuestas
        $solucionesQuery = "DELETE FROM PROBLEMA_SOLUCION_PROPUESTA WHERE ID_Problema = ?";
        $solucionesStmt = $this->conn->prepare($solucionesQuery);
        $solucionesStmt->execute([$id]);
        
        // Finalmente eliminar el problema
        $query = "DELETE FROM " . $this->table_name . " WHERE ID = ?";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        return $stmt->execute([$id]);
    }
    
    /**
     * Asignar una incidencia a un problema
     * @param integer $problema_id ID del problema
     * @param integer $incidencia_id ID de la incidencia
     * @param integer $created_by ID del usuario que crea la relación
     * @return boolean True si se creó correctamente
     */
    public function asignarIncidencia($problema_id, $incidencia_id, $created_by) {
        // Verificar si ya existe la relación
        $checkQuery = "SELECT COUNT(*) as total FROM PROBLEMA_INCIDENCIA WHERE ID_Problema = ? AND ID_Incidencia = ?";
        $checkStmt = $this->conn->prepare($checkQuery);
        $checkStmt->execute([$problema_id, $incidencia_id]);
        $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if($result['total'] > 0) {
            // La relación ya existe
            return true;
        }
        
        $query = "INSERT INTO PROBLEMA_INCIDENCIA (ID_Problema, ID_Incidencia, CreatedBy, CreatedDate) 
                  VALUES (?, ?, ?, GETDATE())";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        return $stmt->execute([$problema_id, $incidencia_id, $created_by]);
    }
    
    /**
     * Desasignar una incidencia de un problema
     * @param integer $problema_id ID del problema
     * @param integer $incidencia_id ID de la incidencia
     * @return boolean True si se eliminó correctamente
     */
    public function desasignarIncidencia($problema_id, $incidencia_id) {
        $query = "DELETE FROM PROBLEMA_INCIDENCIA WHERE ID_Problema = ? AND ID_Incidencia = ?";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        return $stmt->execute([$problema_id, $incidencia_id]);
    }
    
    /**
     * Obtener incidencias relacionadas con un problema
     * @param integer $problema_id ID del problema
     * @return PDOStatement Resultado de la consulta
     */
    public function getIncidenciasAsociadas($problema_id) {
        $query = "SELECT i.ID, i.Descripcion, i.FechaInicio, i.FechaTerminacion, 
                         p.Descripcion as Prioridad, s.Descripcion as Estado,
                         ci.Nombre as CI_Nombre, t.Nombre as CI_Tipo,
                         e.Nombre as Tecnico
                  FROM PROBLEMA_INCIDENCIA pi
                  JOIN INCIDENCIA i ON pi.ID_Incidencia = i.ID
                  LEFT JOIN PRIORIDAD p ON i.ID_Prioridad = p.ID
                  LEFT JOIN ESTATUS_INCIDENCIA s ON i.ID_Stat = s.ID
                  LEFT JOIN CI ci ON i.ID_CI = ci.ID
                  LEFT JOIN TIPO_CI t ON ci.ID_TipoCI = t.ID
                  LEFT JOIN EMPLEADO e ON i.ID_Tecnico = e.ID
                  WHERE pi.ID_Problema = ?
                  ORDER BY i.FechaInicio DESC";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        $stmt->execute([$problema_id]);
        
        return $stmt;
    }
    
    /**
     * Agregar comentario a un problema
     * @param integer $problema_id ID del problema
     * @param integer $usuario_id ID del usuario que comenta
     * @param string $comentario Texto del comentario
     * @param string $tipo_comentario Tipo del comentario (COMENTARIO, ACTUALIZACION, ANÁLISIS, etc.)
     * @return boolean True si se agregó correctamente
     */
    public function agregarComentario($problema_id, $usuario_id, $comentario, $tipo_comentario = 'COMENTARIO') {
        $query = "INSERT INTO PROBLEMA_COMENTARIO (ID_Problema, ID_Usuario, Comentario, TipoComentario, FechaRegistro) 
                  VALUES (?, ?, ?, ?, GETDATE())";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        return $stmt->execute([$problema_id, $usuario_id, $comentario, $tipo_comentario]);
    }
    
    /**
     * Registrar historial de cambio de estado
     * @param integer $problema_id ID del problema
     * @param integer $estado_anterior ID del estado anterior
     * @param integer $estado_nuevo ID del nuevo estado
     * @param integer $usuario_id ID del usuario que realiza el cambio
     * @return boolean True si se registró correctamente
     */
    public function registrarCambioEstado($problema_id, $estado_anterior, $estado_nuevo, $usuario_id) {
        $query = "INSERT INTO PROBLEMA_HISTORIAL (ID_Problema, ID_EstadoAnterior, ID_EstadoNuevo, ID_Usuario, FechaCambio) 
                  VALUES (?, ?, ?, ?, GETDATE())";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        return $stmt->execute([$problema_id, $estado_anterior, $estado_nuevo, $usuario_id]);
    }
    
    /**
     * Agregar solución propuesta a un problema
     * @param integer $problema_id ID del problema
     * @param string $titulo Título de la solución
     * @param string $descripcion Descripción de la solución
     * @param string $tipo_solucion Tipo de solución (WORKAROUND, SOLUCION_PERMANENTE)
     * @param integer $usuario_id ID del usuario que propone la solución
     * @return boolean True si se agregó correctamente
     */
    public function agregarSolucionPropuesta($problema_id, $titulo, $descripcion, $tipo_solucion, $usuario_id) {
        $query = "INSERT INTO PROBLEMA_SOLUCION_PROPUESTA 
                  (ID_Problema, Titulo, Descripcion, TipoSolucion, ID_Usuario, FechaRegistro) 
                  VALUES (?, ?, ?, ?, ?, GETDATE())";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        return $stmt->execute([$problema_id, $titulo, $descripcion, $tipo_solucion, $usuario_id]);
    }
    
    /**
     * Obtener soluciones propuestas para un problema
     * @param integer $problema_id ID del problema
     * @return PDOStatement Resultado de la consulta
     */
    public function getSolucionesPropuestas($problema_id) {
        $query = "SELECT sp.ID, sp.Titulo, sp.Descripcion, sp.TipoSolucion, sp.FechaRegistro,
                         e.Nombre as NombreUsuario
                  FROM PROBLEMA_SOLUCION_PROPUESTA sp
                  LEFT JOIN USUARIO u ON sp.ID_Usuario = u.ID
                  LEFT JOIN EMPLEADO e ON u.ID_Empleado = e.ID
                  WHERE sp.ID_Problema = ?
                  ORDER BY sp.FechaRegistro DESC";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        $stmt->execute([$problema_id]);
        
        return $stmt;
    }
    
    /**
     * Obtener comentarios de un problema
     * @param integer $problema_id ID del problema
     * @return PDOStatement Resultado de la consulta
     */
    public function getComentarios($problema_id) {
        $query = "SELECT c.ID, c.Comentario, c.TipoComentario, c.FechaRegistro,
                         e.Nombre as NombreEmpleado
                  FROM PROBLEMA_COMENTARIO c
                  LEFT JOIN USUARIO u ON c.ID_Usuario = u.ID
                  LEFT JOIN EMPLEADO e ON u.ID_Empleado = e.ID
                  WHERE c.ID_Problema = ?
                  ORDER BY c.FechaRegistro ASC";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        $stmt->execute([$problema_id]);
        
        return $stmt;
    }
    
    /**
     * Obtener historial de estados de un problema
     * @param integer $problema_id ID del problema
     * @return PDOStatement Resultado de la consulta
     */
    public function getHistorialEstados($problema_id) {
        $query = "SELECT h.ID, h.ID_EstadoAnterior, h.ID_EstadoNuevo, h.FechaCambio,
                         s1.Descripcion as EstadoAnterior, s2.Descripcion as EstadoNuevo,
                         e.Nombre as NombreEmpleado
                  FROM PROBLEMA_HISTORIAL h
                  LEFT JOIN ESTATUS_PROBLEMA s1 ON h.ID_EstadoAnterior = s1.ID
                  LEFT JOIN ESTATUS_PROBLEMA s2 ON h.ID_EstadoNuevo = s2.ID
                  LEFT JOIN USUARIO u ON h.ID_Usuario = u.ID
                  LEFT JOIN EMPLEADO e ON u.ID_Empleado = e.ID
                  WHERE h.ID_Problema = ?
                  ORDER BY h.FechaCambio ASC";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        $stmt->execute([$problema_id]);
        
        return $stmt;
    }
    
    /**
     * Obtener todas las categorías de problemas
     * @return PDOStatement Resultado de la consulta
     */
    public function getCategorias() {
        $query = "SELECT ID, Nombre, Descripcion FROM CATEGORIA_PROBLEMA ORDER BY Nombre";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        $stmt->execute();
        
        return $stmt;
    }
    
    /**
     * Obtener todos los niveles de impacto
     * @return PDOStatement Resultado de la consulta
     */
    public function getImpactos() {
        $query = "SELECT ID, Descripcion FROM IMPACTO ORDER BY ID";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        $stmt->execute();
        
        return $stmt;
    }
    
    /**
     * Obtener todos los estados de problema
     * @return PDOStatement Resultado de la consulta
     */
    public function getEstados() {
        $query = "SELECT ID, Descripcion FROM ESTATUS_PROBLEMA ORDER BY ID";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        $stmt->execute();
        
        return $stmt;
    }
    
    /**
     * Obtener empleados que pueden ser responsables de problemas
     * @return PDOStatement Resultado de la consulta
     */
    public function getResponsablesPotenciales() {
        $query = "SELECT e.ID, e.Nombre, e.Email, r.Nombre as Rol 
                  FROM EMPLEADO e
                  JOIN ROL r ON e.ID_Rol = r.ID
                  WHERE r.Nombre IN ('Coordinador TI CEDIS', 'Coordinador TI Sucursales', 
                                     'Coordinador TI Corporativo', 'Supervisor Infraestructura', 
                                     'Supervisor Sistemas')
                  ORDER BY e.Nombre";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        $stmt->execute();
        
        return $stmt;
    }
    
    /**
     * Cambiar el estado de un problema
     * @param integer $id ID del problema
     * @param integer $id_stat ID del nuevo estado
     * @param integer $modified_by ID del usuario que modifica
     * @return boolean True si se actualizó correctamente
     */
    public function cambiarEstado($id, $id_stat, $modified_by) {
        $query = "UPDATE " . $this->table_name . " 
                  SET ID_Stat = ?, ModifiedBy = ?, ModifiedDate = GETDATE() 
                  WHERE ID = ?";
        
        // Si el problema está resuelto, registrar la fecha de resolución
        if($id_stat == 4) { // 4 = Resuelto
            $query = "UPDATE " . $this->table_name . " 
                      SET ID_Stat = ?, FechaResolucion = GETDATE(),
                          ModifiedBy = ?, ModifiedDate = GETDATE() 
                      WHERE ID = ?";
        }
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        return $stmt->execute([$id_stat, $modified_by, $id]);
    }
    
    /**
     * Obtener estadísticas de problemas
     * @param integer $responsable_id ID del responsable para filtrar (opcional)
     * @return array Estadísticas
     */
    public function getEstadisticas($responsable_id = null) {
        // Preparar array de resultados
        $estadisticas = [];
        
        try {
            // Condición para filtrar por responsable si se proporciona
            $responsable_condition = "";
            $params = [];
            
            if ($responsable_id) {
                $responsable_condition = " WHERE p.ID_Responsable = ?";
                $params[] = $responsable_id;
            }
            
            // 1. Total de problemas
            $queryTotal = "SELECT COUNT(*) as total FROM " . $this->table_name . " p" . $responsable_condition;
            $stmtTotal = $this->conn->prepare($queryTotal);
            $stmtTotal->execute($params);
            $estadisticas['total'] = $stmtTotal->fetch(PDO::FETCH_ASSOC)['total'];
            
            // 2. Problemas abiertos (Identificado, En análisis, En implementación)
            $params_abiertos = $params;
            $queryAbiertos = "SELECT COUNT(*) as total FROM " . $this->table_name . " p 
                             " . ($responsable_condition ? $responsable_condition . " AND" : " WHERE") . " p.ID_Stat IN (1, 2, 3)";
            $stmtAbiertos = $this->conn->prepare($queryAbiertos);
            $stmtAbiertos->execute($params_abiertos);
            $estadisticas['abiertos'] = $stmtAbiertos->fetch(PDO::FETCH_ASSOC)['total'];
            
            // 3. Problemas resueltos
            $params_resueltos = $params;
            $queryResueltos = "SELECT COUNT(*) as total FROM " . $this->table_name . " p 
                             " . ($responsable_condition ? $responsable_condition . " AND" : " WHERE") . " p.ID_Stat = 4";
            $stmtResueltos = $this->conn->prepare($queryResueltos);
            $stmtResueltos->execute($params_resueltos);
            $estadisticas['resueltos'] = $stmtResueltos->fetch(PDO::FETCH_ASSOC)['total'];
            
            // 4. Problemas con alto impacto
            $params_altoimpacto = $params;
            $queryAltoImpacto = "SELECT COUNT(*) as total FROM " . $this->table_name . " p 
                            " . ($responsable_condition ? $responsable_condition . " AND" : " WHERE") . " p.ID_Impacto = 1"; // 1 = Alto
            $stmtAltoImpacto = $this->conn->prepare($queryAltoImpacto);
            $stmtAltoImpacto->execute($params_altoimpacto);
            $estadisticas['alto_impacto'] = $stmtAltoImpacto->fetch(PDO::FETCH_ASSOC)['total'];
            
            // 5. Problemas por categoría
            $queryPorCategoria = "SELECT cat.Nombre as Categoria, COUNT(p.ID) as Total 
                                 FROM CATEGORIA_PROBLEMA cat
                                 LEFT JOIN " . $this->table_name . " p ON cat.ID = p.ID_Categoria
                                 " . $responsable_condition . "
                                 GROUP BY cat.Nombre
                                 ORDER BY Total DESC";
            $stmtPorCategoria = $this->conn->prepare($queryPorCategoria);
            $stmtPorCategoria->execute($params);
            $estadisticas['por_categoria'] = $stmtPorCategoria->fetchAll(PDO::FETCH_ASSOC);
            
            // 6. Problemas por estado
            $queryPorEstado = "SELECT ep.Descripcion as Estado, COUNT(p.ID) as Total 
                                 FROM ESTATUS_PROBLEMA ep
                                 LEFT JOIN " . $this->table_name . " p ON ep.ID = p.ID_Stat
                                 " . $responsable_condition . "
                                 GROUP BY ep.Descripcion
                                 ORDER BY Total DESC";
            $stmtPorEstado = $this->conn->prepare($queryPorEstado);
            $stmtPorEstado->execute($params);
            $estadisticas['por_estado'] = $stmtPorEstado->fetchAll(PDO::FETCH_ASSOC);
            
            // 7. Tiempo promedio de resolución (en días)
            $params_tiempo = $params;
            $queryTiempo = "SELECT AVG(DATEDIFF(day, FechaIdentificacion, FechaResolucion)) as promedio 
                           FROM " . $this->table_name . " p 
                           " . ($responsable_condition ? $responsable_condition . " AND" : " WHERE") . " p.FechaResolucion IS NOT NULL";
            $stmtTiempo = $this->conn->prepare($queryTiempo);
            $stmtTiempo->execute($params_tiempo);
            $estadisticas['tiempo_promedio'] = $stmtTiempo->fetch(PDO::FETCH_ASSOC)['promedio'];
            
            return $estadisticas;
        } catch (Exception $e) {
            // Registrar el error y devolver un array vacío
            error_log("Error en Problema::getEstadisticas(): " . $e->getMessage());
            return [
                'total' => 0,
                'abiertos' => 0,
                'resueltos' => 0,
                'alto_impacto' => 0,
                'por_categoria' => [],
                'por_estado' => [],
                'tiempo_promedio' => 0
            ];
        }
    }
}