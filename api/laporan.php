<?php
date_default_timezone_set('Asia/Jakarta');

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include 'db.php';

// Sinkronisasi zona waktu MySQL dengan Jakarta (UTC+7) agar CURDATE() akurat
$conn->query("SET time_zone = '+07:00'");

// laporan penjualan harian, bulanan, tahunan
$sql = "SELECT 
            SUM(CASE WHEN DATE(tanggal) = CURDATE() THEN total ELSE 0 END) as harian,
            SUM(CASE WHEN MONTH(tanggal) = MONTH(CURDATE()) AND YEAR(tanggal) = YEAR(CURDATE()) THEN total ELSE 0 END) as bulanan,
            SUM(total) as tahunan
        FROM penjualan 
        WHERE YEAR(tanggal) = YEAR(CURDATE())";

$result = $conn->query($sql);
$data = $result->fetch_assoc();

$response_penjualan = [

    "data" => [
        "harian" => (float) ($data['harian'] ?? 0),
        "bulanan" => (float) ($data['bulanan'] ?? 0),
        "tahunan" => (float) ($data['tahunan'] ?? 0)
    ],

];

//laporan pembelian harian, bulanan, tahunan
$sql_pembelian = "SELECT 
            SUM(CASE WHEN DATE(tanggal) = CURDATE() THEN total ELSE 0 END) as harian,
            SUM(CASE WHEN MONTH(tanggal) = MONTH(CURDATE()) AND YEAR(tanggal) = YEAR(CURDATE()) THEN total ELSE 0 END) as bulanan,
            SUM(total) as tahunan
        FROM pembelian 
        WHERE YEAR(tanggal) = YEAR(CURDATE())";

$result_pembelian = $conn->query($sql_pembelian);
$data_pembelian = $result_pembelian->fetch_assoc();

$response_pembelian = [
    "data" => [
        "harian" => (float) ($data_pembelian['harian'] ?? 0),
        "bulanan" => (float) ($data_pembelian['bulanan'] ?? 0),
        "tahunan" => (float) ($data_pembelian['tahunan'] ?? 0)
    ]
];

echo json_encode([
    "status" => "success",
    "penjualan" => $response_penjualan,
    "pembelian" => $response_pembelian,
    "info" => [
        "tanggal_server" => date('Y-m-d H:i:s')
    ]
]);
$conn->close();
?>