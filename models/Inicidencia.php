<?php
/**
 * Modelo Incidencia - Gestiona la información y lógica relacionada con las incidencias
 */
class Incidencia {
    // Conexión a la base de datos y nombre de la tabla
    private $conn;
    private $table_name = "INCIDENCIA";
    
    // Propiedades del objeto
    public $id;
    public $descripcion;
    public $fecha_inicio;
    public $fecha_terminacion;
    public $id_prioridad;
    public $id_ci;
    public $id_tecnico;
    public $id_stat;
    public $created_by;
    public $created_date;
    public $modified_by;
    public $modified_date;
    
    // Propiedades relacionadas
    public $prioridad;
    public $estado;
    public $ci_nombre;
    public $ci_tipo;
    public $tecnico_nombre;
    
    // Constructor con conexión a la base de datos
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Obtener todas las incidencias con filtros opcionales
     * @param array $filtros Array asociativo con los filtros a aplicar
     * @return PDOStatement Resultado de la consulta
     */
    public function getAll($filtros = []) {
        // Construir la consulta base
        $query = "SELECT i.ID, i.Descripcion, i.FechaInicio, i.FechaTerminacion, 
                         p.Descripcion as Prioridad, p.ID as ID_Prioridad,
                         s.Descripcion as Estado, s.ID as ID_Estado,
                         ci.Nombre as CI_Nombre, t.Nombre as CI_Tipo,
                         e.Nombre as Tecnico_Nombre
                  FROM " . $this->table_name . " i
                  LEFT JOIN PRIORIDAD p ON i.ID_Prioridad = p.ID
                  LEFT JOIN ESTATUS_INCIDENCIA s ON i.ID_Stat = s.ID
                  LEFT JOIN CI ci ON i.ID_CI = ci.ID
                  LEFT JOIN TIPO_CI t ON ci.ID_TipoCI = t.ID
                  LEFT JOIN EMPLEADO e ON i.ID_Tecnico = e.ID
                  WHERE 1=1";
        
        $params = [];
        
        // Aplicar filtros
        if(isset($filtros['estado']) && !empty($filtros['estado'])) {
            $query .= " AND i.ID_Stat = ?";
            $params[] = $filtros['estado'];
        }
        
        if(isset($filtros['prioridad']) && !empty($filtros['prioridad'])) {
            $query .= " AND i.ID_Prioridad = ?";
            $params[] = $filtros['prioridad'];
        }
        
        if(isset($filtros['tecnico']) && !empty($filtros['tecnico'])) {
            $query .= " AND i.ID_Tecnico = ?";
            $params[] = $filtros['tecnico'];
        }
        
        if(isset($filtros['ci']) && !empty($filtros['ci'])) {
            $query .= " AND i.ID_CI = ?";
            $params[] = $filtros['ci'];
        }
        
        if(isset($filtros['busqueda']) && !empty($filtros['busqueda'])) {
            $query .= " AND (i.Descripcion LIKE ? OR ci.Nombre LIKE ? OR e.Nombre LIKE ?)";
            $busqueda = "%" . $filtros['busqueda'] . "%";
            $params[] = $busqueda;
            $params[] = $busqueda;
            $params[] = $busqueda;
        }
        
        // Filtrar por incidencias reportadas por un usuario específico
        if(isset($filtros['creado_por']) && !empty($filtros['creado_por'])) {
            $query .= " AND i.CreatedBy = ?";
            $params[] = $filtros['creado_por'];
        }
        
        // Ordenar los resultados (más recientes primero)
        $query .= " ORDER BY i.FechaInicio DESC";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        $stmt->execute($params);
        
        return $stmt;
    }
    
    /**
     * Obtener una incidencia por su ID
     * @param integer $id ID de la incidencia
     * @return boolean True si se encontró la incidencia
     */
    public function getById($id) {
        $query = "SELECT i.ID, i.Descripcion, i.FechaInicio, i.FechaTerminacion, 
                         i.ID_Prioridad, i.ID_CI, i.ID_Tecnico, i.ID_Stat,
                         i.CreatedBy, i.CreatedDate, i.ModifiedBy, i.ModifiedDate,
                         p.Descripcion as Prioridad, s.Descripcion as Estado,
                         ci.Nombre as CI_Nombre, t.Nombre as CI_Tipo,
                         e.Nombre as Tecnico_Nombre
                  FROM " . $this->table_name . " i
                  LEFT JOIN PRIORIDAD p ON i.ID_Prioridad = p.ID
                  LEFT JOIN ESTATUS_INCIDENCIA s ON i.ID_Stat = s.ID
                  LEFT JOIN CI ci ON i.ID_CI = ci.ID
                  LEFT JOIN TIPO_CI t ON ci.ID_TipoCI = t.ID
                  LEFT JOIN EMPLEADO e ON i.ID_Tecnico = e.ID
                  WHERE i.ID = ?";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        $stmt->execute([$id]);
        
        // Verificar si se encontró la incidencia
        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Asignar valores a las propiedades
            $this->id = $row['ID'];
            $this->descripcion = $row['Descripcion'];
            $this->fecha_inicio = $row['FechaInicio'];
            $this->fecha_terminacion = $row['FechaTerminacion'];
            $this->id_prioridad = $row['ID_Prioridad'];
            $this->id_ci = $row['ID_CI'];
            $this->id_tecnico = $row['ID_Tecnico'];
            $this->id_stat = $row['ID_Stat'];
            $this->created_by = $row['CreatedBy'];
            $this->created_date = $row['CreatedDate'];
            $this->modified_by = $row['ModifiedBy'];
            $this->modified_date = $row['ModifiedDate'];
            
            // Propiedades relacionadas
            $this->prioridad = $row['Prioridad'];
            $this->estado = $row['Estado'];
            $this->ci_nombre = $row['CI_Nombre'];
            $this->ci_tipo = $row['CI_Tipo'];
            $this->tecnico_nombre = $row['Tecnico_Nombre'];
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Crear una nueva incidencia
     * @return boolean True si se creó correctamente
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                  (Descripcion, FechaInicio, ID_Prioridad, ID_CI, ID_Tecnico, ID_Stat, CreatedBy, CreatedDate) 
                  VALUES (?, GETDATE(), ?, ?, ?, ?, ?, GETDATE())";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        return $stmt->execute([
            $this->descripcion,
            $this->id_prioridad,
            $this->id_ci,
            $this->id_tecnico,
            $this->id_stat,
            $this->created_by
        ]);
    }
    
    /**
     * Actualizar una incidencia existente
     * @return boolean True si se actualizó correctamente
     */
    public function update() {
        $query = "UPDATE " . $this->table_name . " 
                  SET Descripcion = ?, ID_Prioridad = ?, ID_CI = ?, 
                      ID_Tecnico = ?, ID_Stat = ?, ModifiedBy = ?, ModifiedDate = GETDATE() 
                  WHERE ID = ?";
        
        // Si la incidencia está resuelta o cerrada, registrar la fecha de terminación
        if($this->id_stat == 5 || $this->id_stat == 6) { // 5 = Resuelta, 6 = Cerrada
            $query = "UPDATE " . $this->table_name . " 
                      SET Descripcion = ?, ID_Prioridad = ?, ID_CI = ?, 
                          ID_Tecnico = ?, ID_Stat = ?, FechaTerminacion = GETDATE(),
                          ModifiedBy = ?, ModifiedDate = GETDATE() 
                      WHERE ID = ?";
        }
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Parámetros a ejecutar
        $params = [
            $this->descripcion,
            $this->id_prioridad,
            $this->id_ci,
            $this->id_tecnico,
            $this->id_stat,
            $this->modified_by,
            $this->id
        ];
        
        // Ejecutar la consulta
        return $stmt->execute($params);
    }
    
    /**
     * Actualizar el estado de una incidencia
     * @param integer $id ID de la incidencia
     * @param integer $id_stat ID del nuevo estado
     * @param integer $modified_by ID del usuario que modifica
     * @return boolean True si se actualizó correctamente
     */
    public function cambiarEstado($id, $id_stat, $modified_by) {
        $query = "UPDATE " . $this->table_name . " 
                  SET ID_Stat = ?, ModifiedBy = ?, ModifiedDate = GETDATE() 
                  WHERE ID = ?";
        
        // Si la incidencia está resuelta o cerrada, registrar la fecha de terminación
        if($id_stat == 5 || $id_stat == 6) { // 5 = Resuelta, 6 = Cerrada
            $query = "UPDATE " . $this->table_name . " 
                      SET ID_Stat = ?, FechaTerminacion = GETDATE(),
                          ModifiedBy = ?, ModifiedDate = GETDATE() 
                      WHERE ID = ?";
        }
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        return $stmt->execute([$id_stat, $modified_by, $id]);
    }
    
    /**
     * Asignar técnico a una incidencia
     * @param integer $id ID de la incidencia
     * @param integer $id_tecnico ID del técnico a asignar
     * @param integer $modified_by ID del usuario que modifica
     * @return boolean True si se actualizó correctamente
     */
    public function asignarTecnico($id, $id_tecnico, $modified_by) {
        $query = "UPDATE " . $this->table_name . " 
                  SET ID_Tecnico = ?, ID_Stat = 2, ModifiedBy = ?, ModifiedDate = GETDATE() 
                  WHERE ID = ?";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta (2 = Asignada)
        return $stmt->execute([$id_tecnico, $modified_by, $id]);
    }
    
    /**
     * Eliminar una incidencia
     * @param integer $id ID de la incidencia a eliminar
     * @return boolean True si se eliminó correctamente
     */
    public function delete($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE ID = ?";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        return $stmt->execute([$id]);
    }
    
    /**
     * Obtener todas las prioridades disponibles
     * @return PDOStatement Resultado de la consulta
     */
    public function getPrioridades() {
        $query = "SELECT ID, Descripcion FROM PRIORIDAD ORDER BY ID";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        $stmt->execute();
        
        return $stmt;
    }
    
    /**
     * Obtener todos los estados disponibles
     * @return PDOStatement Resultado de la consulta
     */
    public function getEstados() {
        $query = "SELECT ID, Descripcion FROM ESTATUS_INCIDENCIA ORDER BY ID";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        $stmt->execute();
        
        return $stmt;
    }
    
    /**
     * Obtener técnicos disponibles para asignar
     * @return PDOStatement Resultado de la consulta
     */
    public function getTecnicos() {
        $query = "SELECT e.ID, e.Nombre, e.Email 
                  FROM EMPLEADO e 
                  JOIN ROL r ON e.ID_Rol = r.ID 
                  WHERE r.Nombre = 'Técnico TI'
                  ORDER BY e.Nombre";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        $stmt->execute();
        
        return $stmt;
    }
    
    /**
     * Contar incidencias por estado (para dashboard)
     * @return PDOStatement Resultado de la consulta
     */
    public function contarPorEstado() {
        $query = "SELECT s.Descripcion as Estado, COUNT(i.ID) as Total 
                  FROM ESTATUS_INCIDENCIA s
                  LEFT JOIN INCIDENCIA i ON s.ID = i.ID_Stat
                  GROUP BY s.Descripcion
                  ORDER BY Total DESC";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        $stmt->execute();
        
        return $stmt;
    }
    
    /**
     * Obtener incidencias asignadas a un técnico
     * @param integer $id_tecnico ID del técnico
     * @return PDOStatement Resultado de la consulta
     */
    public function getAsignadasATecnico($id_tecnico) {
        $query = "SELECT i.ID, i.Descripcion, i.FechaInicio, p.Descripcion as Prioridad,
                         s.Descripcion as Estado, ci.Nombre as CI_Nombre
                  FROM " . $this->table_name . " i
                  LEFT JOIN PRIORIDAD p ON i.ID_Prioridad = p.ID
                  LEFT JOIN ESTATUS_INCIDENCIA s ON i.ID_Stat = s.ID
                  LEFT JOIN CI ci ON i.ID_CI = ci.ID
                  WHERE i.ID_Tecnico = ?
                  AND i.ID_Stat IN (2, 3, 4) -- Asignada, En proceso, En espera
                  ORDER BY i.FechaInicio DESC";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        $stmt->execute([$id_tecnico]);
        
        return $stmt;
    }
    
    /**
     * Obtener incidencias reportadas por un usuario
     * @param integer $id_usuario ID del usuario que reportó
     * @return PDOStatement Resultado de la consulta
     */
    public function getReportadasPorUsuario($id_usuario) {
        $query = "SELECT i.ID, i.Descripcion, i.FechaInicio, i.FechaTerminacion,
                         p.Descripcion as Prioridad, s.Descripcion as Estado,
                         ci.Nombre as CI_Nombre, e.Nombre as Tecnico_Nombre
                  FROM " . $this->table_name . " i
                  LEFT JOIN PRIORIDAD p ON i.ID_Prioridad = p.ID
                  LEFT JOIN ESTATUS_INCIDENCIA s ON i.ID_Stat = s.ID
                  LEFT JOIN CI ci ON i.ID_CI = ci.ID
                  LEFT JOIN EMPLEADO e ON i.ID_Tecnico = e.ID
                  WHERE i.CreatedBy = ?
                  ORDER BY i.FechaInicio DESC";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        $stmt->execute([$id_usuario]);
        
        return $stmt;
    }
    
    /**
     * Obtener estadísticas de incidencias (para dashboard)
     * @return array Resultado con estadísticas
     */
    public function getEstadisticas() {
        // Preparar array de resultados
        $estadisticas = [];
        
        // 1. Total de incidencias
        $queryTotal = "SELECT COUNT(*) as total FROM " . $this->table_name;
        $stmtTotal = $this->conn->prepare($queryTotal);
        $stmtTotal->execute();
        $estadisticas['total'] = $stmtTotal->fetch(PDO::FETCH_ASSOC)['total'];
        
        // 2. Incidencias abiertas (Nueva, Asignada, En proceso, En espera)
        $queryAbiertas = "SELECT COUNT(*) as total FROM " . $this->table_name . 
                          " WHERE ID_Stat IN (1, 2, 3, 4)";
        $stmtAbiertas = $this->conn->prepare($queryAbiertas);
        $stmtAbiertas->execute();
        $estadisticas['abiertas'] = $stmtAbiertas->fetch(PDO::FETCH_ASSOC)['total'];
        
        // 3. Incidencias resueltas/cerradas
        $queryResueltas = "SELECT COUNT(*) as total FROM " . $this->table_name . 
                           " WHERE ID_Stat IN (5, 6)";
        $stmtResueltas = $this->conn->prepare($queryResueltas);
        $stmtResueltas->execute();
        $estadisticas['resueltas'] = $stmtResueltas->fetch(PDO::FETCH_ASSOC)['total'];
        
        // 4. Incidencias críticas
        $queryCriticas = "SELECT COUNT(*) as total FROM " . $this->table_name . 
                          " WHERE ID_Prioridad = 1"; // 1 = Crítica
        $stmtCriticas = $this->conn->prepare($queryCriticas);
        $stmtCriticas->execute();
        $estadisticas['criticas'] = $stmtCriticas->fetch(PDO::FETCH_ASSOC)['total'];
        
        // 5. Tiempo promedio de resolución (en días)
        $queryTiempo = "SELECT AVG(DATEDIFF(day, FechaInicio, FechaTerminacion)) as promedio 
                         FROM " . $this->table_name . " 
                         WHERE FechaTerminacion IS NOT NULL";
        $stmtTiempo = $this->conn->prepare($queryTiempo);
        $stmtTiempo->execute();
        $estadisticas['tiempo_promedio'] = $stmtTiempo->fetch(PDO::FETCH_ASSOC)['promedio'];
        
        // 6. Incidencias creadas en los últimos 30 días
        $queryRecientes = "SELECT COUNT(*) as total FROM " . $this->table_name . 
                           " WHERE FechaInicio >= DATEADD(day, -30, GETDATE())";
        $stmtRecientes = $this->conn->prepare($queryRecientes);
        $stmtRecientes->execute();
        $estadisticas['recientes'] = $stmtRecientes->fetch(PDO::FETCH_ASSOC)['total'];
        
        return $estadisticas;
    }
}