<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");
include 'db.php';
$conn->set_charset("utf8");

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Database error"]);
    exit;
}

// Tentukan Base URL secara dinamis (misal: http://localhost/maju/api)
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$baseUrl = $protocol . "://" . $_SERVER['HTTP_HOST'] . str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));

// Ambil parameter GET
$q = $_GET['q'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

if ($page < 1) $page = 1;
if ($limit < 1) $limit = 10;

$offset = ($page - 1) * $limit;
$search = "%$q%";

// --- 1. Ambil total data untuk paginasi ---
$count_stmt = $conn->prepare("SELECT COUNT(*) FROM barang WHERE nama_barang LIKE ? AND aktif = 1");
$count_stmt->bind_param("s", $search);
$count_stmt->execute();
$count_res = $count_stmt->get_result();
$total_rows = $count_res->fetch_row()[0];
$count_stmt->close();

// --- 2. Ambil data produk per halaman ---
$stmt = $conn->prepare("
    SELECT b.*, (SELECT nama_gambar FROM gambar WHERE barang_id = b.barang_id LIMIT 1) as nama_gambar
    FROM barang b
    WHERE b.nama_barang LIKE ?
    AND b.aktif = 1
    ORDER BY b.nama_barang
    LIMIT ? OFFSET ?
");
$stmt->bind_param("sii", $search, $limit, $offset);
$stmt->execute();

$res = $stmt->get_result();
$data = [];

while ($row = $res->fetch_assoc()) {
    $row['gambar_url'] = $row['nama_gambar']
        ? $baseUrl . "/upload/" . $row['nama_gambar']
        : null;
    $data[] = $row;
}
$stmt->close();

// --- 3. Siapkan response ---
$has_more = ($page * $limit) < $total_rows;

$response = [
    'data' => $data,
    'has_more' => $has_more
];

echo json_encode($response);

$conn->close();