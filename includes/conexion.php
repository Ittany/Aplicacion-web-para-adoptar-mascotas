<?php
try {
    $conn = new PDO("sqlsrv:Server=LAPTOP-6E8MEQI7;Database=MichiHouseDB", "sa", "sa1234");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    error_log("Error de conexión BD: " . $e->getMessage());
    die("❌ Error de conexión: " . $e->getMessage());
}