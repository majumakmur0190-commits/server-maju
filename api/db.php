<?php

try {
    $databases = require __DIR__ . '/../config/database.php';
    $dbConfig = $databases['default'] ?? null;

    if (!$dbConfig) {
        throw new RuntimeException('Konfigurasi database default tidak ditemukan.');
    }

    $conn = new mysqli(
        $dbConfig['host'],
        $dbConfig['username'],
        $dbConfig['password'],
        $dbConfig['database'],
        (int) ($dbConfig['port'] ?? 3306)
    );

    if ($conn->connect_error) {
        throw new RuntimeException($conn->connect_error);
    }

    $conn->set_charset($dbConfig['charset'] ?? 'utf8mb4');
} catch (Throwable $e) {
    die(json_encode([
        "status" => "error",
        "message" => "Koneksi gagal: " . $e->getMessage()
    ]));
}
?>
