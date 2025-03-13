<?php
// Incluir configuración de base de datos
require_once 'config/database.php';

// Usuario y contraseña de prueba
$username = "admin";
$password = "admin123";

// Conectar a la base de datos
$database = new Database();
$conn = $database->getConnection();

echo "<h2>Prueba directa de inicio de sesión</h2>";

if (!$conn) {
    die("Error: No se pudo conectar a la base de datos.");
}

// Consulta exactamente como está en login.php
$query = "SELECT u.ID, u.Username, u.Password, u.Estado, u.ID_Empleado, u.ID_Rol, 
         r.Nombre as role_name, e.Nombre as full_name 
  FROM USUARIO u 
  INNER JOIN ROL r ON u.ID_Rol = r.ID 
  INNER JOIN EMPLEADO e ON u.ID_Empleado = e.ID 
  WHERE u.Username = ? AND u.Estado = 1";

$stmt = $conn->prepare($query);
$stmt->execute([$username]);

echo "Filas encontradas: " . $stmt->rowCount() . "<br>";

if ($stmt->rowCount() > 0) {
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Usuario encontrado:<br>";
    echo "ID: " . $user['ID'] . "<br>";
    echo "Username: " . $user['Username'] . "<br>";
    echo "Password: " . $user['Password'] . "<br>";
    echo "Rol: " . $user['role_name'] . "<br>";
    echo "Nombre: " . $user['full_name'] . "<br><br>";
    
    echo "Verificación de contraseña:<br>";
    echo "Contraseña ingresada: " . $password . "<br>";
    echo "Contraseña en BD: " . $user['Password'] . "<br>";
    echo "¿Coinciden? " . ($password == $user['Password'] ? "SÍ" : "NO") . "<br>";
} else {
    echo "Usuario no encontrado<br>";
    
    // Intentar con una consulta más simple
    $simple_query = "SELECT * FROM USUARIO WHERE Username = ?";
    $simple_stmt = $conn->prepare($simple_query);
    $simple_stmt->execute([$username]);
    
    echo "<br>Prueba con consulta simple:<br>";
    echo "Filas encontradas: " . $simple_stmt->rowCount() . "<br>";
    
    if ($simple_stmt->rowCount() > 0) {
        $simple_user = $simple_stmt->fetch(PDO::FETCH_ASSOC);
        echo "Usuario encontrado con consulta simple:<br>";
        echo "ID: " . $simple_user['ID'] . "<br>";
        echo "Username: " . $simple_user['Username'] . "<br>";
        echo "Estado: " . $simple_user['Estado'] . "<br>";
    }
}

// Analizar el string de la conexión PDO
echo "<h3>Información de la conexión:</h3>";
echo "Host: " . (property_exists($database, 'host') ? $database->host : 'No disponible') . "<br>";
echo "Database: " . (property_exists($database, 'db_name') ? $database->db_name : 'No disponible') . "<br>";

// Ver si hay problemas con PDO::rowCount()
echo "<h3>Prueba de PDO::rowCount()</h3>";
$test_query = "SELECT COUNT(*) as total FROM USUARIO";
$test_stmt = $conn->query($test_query);
$result = $test_stmt->fetch(PDO::FETCH_ASSOC);
echo "Total de usuarios según COUNT(*): " . $result['total'] . "<br>";

$all_users = "SELECT * FROM USUARIO";
$all_stmt = $conn->query($all_users);
echo "Total de usuarios según rowCount(): " . $all_stmt->rowCount() . "<br>";

// Ver si hay un problema con PDO y SQL Server
echo "<h3>Driver PDO usado:</h3>";
echo "Drivers disponibles: " . implode(", ", PDO::getAvailableDrivers()) . "<br>";
?>