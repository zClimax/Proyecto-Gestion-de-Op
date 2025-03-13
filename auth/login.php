<?php
// Incluir archivos necesarios
require_once '../config/database.php';

// Mostrar errores durante el desarrollo
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verificar si la solicitud es POST
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    // Si no es POST, redirigir al index
    header("Location: ../index.php");
    exit();
}

// Obtener datos del formulario
$username = isset($_POST['username']) ? trim($_POST['username']) : "";
$password = isset($_POST['password']) ? $_POST['password'] : "";

// Validación básica
if (empty($username) || empty($password)) {
    header("Location: ../index.php?error=empty_fields");
    exit();
}

try {
    // Conectar a la base de datos
    $database = new Database();
    $conn = $database->getConnection();

    if (!$conn) {
        die("No se pudo establecer la conexión a la base de datos");
    }
    
    // Consulta SQL
    $query = "SELECT * FROM USUARIO WHERE Username = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$username]);
    
    // Obtener usuario
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Verificar si el usuario existe
    if ($user) {
        // Verificar estado
        if ($user['Estado'] != 1) {
            header("Location: ../index.php?error=inactive_user");
            exit();
        }
        
        // Verificar contraseña
        if ($password == $user['Password']) {
            // Iniciar sesión
            session_start();
            
            // Guardar datos básicos
            $_SESSION['user_id'] = $user['ID'];
            $_SESSION['username'] = $user['Username'];
            $_SESSION['role_id'] = $user['ID_Rol'];
            
            try {
                // Obtener rol
                $rol_query = "SELECT * FROM ROL WHERE ID = ?";
                $rol_stmt = $conn->prepare($rol_query);
                $rol_stmt->execute([$user['ID_Rol']]);
                $rol = $rol_stmt->fetch(PDO::FETCH_ASSOC);
                
                // Obtener empleado
                $emp_query = "SELECT * FROM EMPLEADO WHERE ID = ?";
                $emp_stmt = $conn->prepare($emp_query);
                $emp_stmt->execute([$user['ID_Empleado']]);
                $emp = $emp_stmt->fetch(PDO::FETCH_ASSOC);
                
                $_SESSION['role_name'] = $rol ? $rol['Nombre'] : 'Desconocido';
                $_SESSION['full_name'] = $emp ? $emp['Nombre'] : 'Usuario';
            } catch (Exception $e) {
                $_SESSION['role_name'] = 'Desconocido';
                $_SESSION['full_name'] = 'Usuario';
            }
            
            // Asignar permisos básicos (temporalmente todos tienen acceso admin)
            $_SESSION['permisos'] = [
                'admin' => true,
                'gestionar_usuarios' => true,
                'gestionar_ci' => true,
                'gestionar_incidencias' => true,
                'ver_reportes' => true,
                'reportar_incidencia' => true
            ];
            
            // Redirigir al dashboard de admin
            header("Location: ../views/admin/dashboard.php");
            exit();
        } else {
            // Contraseña incorrecta
            header("Location: ../index.php?error=wrong_password");
            exit();
        }
    } else {
        // Usuario no encontrado
        header("Location: ../index.php?error=user_not_found");
        exit();
    }
} catch (PDOException $e) {
    // Error de base de datos
    echo "Error PDO: " . $e->getMessage();
    exit();
} catch (Exception $e) {
    // Error general
    echo "Error general: " . $e->getMessage();
    exit();
}
?>