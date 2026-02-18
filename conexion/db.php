<?php
$host = "127.0.0.1";
$port = "3306";
$user = "root";
$pass = "Ficopwd.18";
$db   = "forza_gps1";

try {
    $conn = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    die(json_encode(["error" => true, "message" => $e->getMessage()]));
}