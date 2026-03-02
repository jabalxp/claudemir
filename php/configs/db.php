<?php
$host = 'localhost';
$user = 'root';
$pass = '';

$db = 'gestao_escolar';
$port = 3308;

$conn = mysqli_connect($host, $user, $pass, $db, $port);

if (!$conn) {
     die("Erro ao conectar ao banco de dados: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");

// Alias OOP para compatibilidade com código portado do Parafal
$mysqli = $conn;

// Helper de escape HTML
if (!function_exists('xe')) {
     function xe($v)
     {
          return htmlspecialchars((string) $v, ENT_QUOTES | ENT_XML1, 'UTF-8');
     }
}
?>