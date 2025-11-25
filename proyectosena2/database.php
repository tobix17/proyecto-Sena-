<?php
// Configuración de la base de datos
$host = 'localhost';
$dbname = 'bitacoras_db';  // Cambia por el nombre de tu base de datos
$username = 'root';   // Cambia por tu usuario
$password = '';       // Cambia por tu contraseña

try {
    // ========== CONEXIÓN PDO (para los nuevos archivos) ==========
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    
    // Configurar PDO para mostrar errores
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Opcional: Configurar zona horaria
    $pdo->exec("SET time_zone = '-05:00'"); // Ajusta según tu zona horaria (Colombia es -05:00)
    
} catch (PDOException $e) {
    // En caso de error, mostrar mensaje y terminar el script
    die("Error de conexión PDO a la base de datos: " . $e->getMessage());
}

try {
    // ========== CONEXIÓN MySQLi (para archivos existentes como login.php) ==========
    $conn = new mysqli($host, $username, $password, $dbname);
    
    // Verificar conexión MySQLi
    if ($conn->connect_error) {
        die("Error de conexión MySQLi: " . $conn->connect_error);
    }
    
    // Configurar charset para MySQLi
    $conn->set_charset("utf8mb4");
    
} catch (Exception $e) {
    die("Error de conexión MySQLi a la base de datos: " . $e->getMessage());
}

// Ahora tienes disponibles ambas variables:
// - $pdo para los archivos nuevos del dashboard
// - $conn para los archivos existentes como login.php
?>