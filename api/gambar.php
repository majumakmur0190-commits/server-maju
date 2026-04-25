<?php
// Aktifkan error reporting sementara untuk debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
include 'db.php';
$conn->set_charset("utf8");

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Database error"]);
    exit;
}

// Cek apakah Extension GD aktif (Wajib untuk manipulasi gambar)
if (!extension_loaded('gd')) {
    http_response_code(500);
    echo json_encode(["error" => "Extension GD PHP tidak aktif. Hubungi admin server."]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Handle Preflight Request (CORS)
if ($method === "OPTIONS") {
    http_response_code(200);
    exit;
}

// Determine Base URL dynamically (http://localhost/maju/api)
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$baseUrl = $protocol . "://" . $_SERVER['HTTP_HOST'] . str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));

/* =========================
   HELPER FUNCTION
========================= */
function cleanName($text)
{
    $text = strtolower(preg_replace("/[^a-z0-9]/", "_", $text));
    $text = preg_replace("/_+/", "_", $text); // Gabungkan underscore berlebih
    return trim($text, "_"); // Hapus underscore di awal/akhir
}

/* =========================
   GET : SEARCH BARANG
========================= */
if ($method === "GET" && ($action === "search" || $action === "")) {

    $q = $_GET['q'] ?? '';

    $stmt = $conn->prepare("
        SELECT b.*, (SELECT nama_gambar FROM gambar WHERE barang_id = b.barang_id LIMIT 1) as nama_gambar
        FROM barang b
        WHERE b.nama_barang LIKE ?
        AND b.aktif = 1
        ORDER BY b.nama_barang
        LIMIT 100
    ");

    $search = "%$q%";
    $stmt->bind_param("s", $search);
    $stmt->execute();

    $res = $stmt->get_result();
    $data = [];

    while ($row = $res->fetch_assoc()) {
        $row['gambar_url'] = $row['nama_gambar']
            ? $baseUrl . "/upload/" . $row['nama_gambar']
            : null;
        $data[] = $row;
    }

    echo json_encode($data);
    exit;
}

/* =========================
   DELETE : HAPUS GAMBAR
========================= */
if ($method === "DELETE" && $action === "delete") {

    $input = json_decode(file_get_contents("php://input"), true);
    $id = $input['barang_id'] ?? 0;

    // 1. Hapus file fisik gambar terlebih dahulu
    $stmt_img = $conn->prepare("SELECT nama_gambar FROM gambar WHERE barang_id=?");
    $stmt_img->bind_param("i", $id);
    $stmt_img->execute();
    $res_img = $stmt_img->get_result();
    
    $target_dir = __DIR__ . "/upload/";
    while ($row = $res_img->fetch_assoc()) {
        $path = $target_dir . $row['nama_gambar'];
        if (file_exists($path)) unlink($path);
    }
    $stmt_img->close();

    // 2. Hapus record dari tabel gambar (BUKAN barang)
    $stmt = $conn->prepare("DELETE FROM gambar WHERE barang_id=?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        echo json_encode(["success" => true]);
    } else {
        http_response_code(400);
        echo json_encode(["error" => "Gagal hapus"]);
    }
    exit;
}

/* =========================
   POST/PUT : INSERT / UPDATE GAMBAR
========================= */
if (($method === "POST" || $method === "PUT") && ($action === "insert_gambar" || $action === "update_gambar")) {

    $data = json_decode(file_get_contents("php://input"), true);

    $barang_id = isset($data['barang_id']) ? (int)$data['barang_id'] : 0;
    $image_url = $data['image_url'] ?? '';

    $stmt = $conn->prepare("SELECT nama_barang FROM barang WHERE barang_id=?");
    $stmt->bind_param("i", $barang_id);
    $stmt->execute();

    $barang = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$barang) {
        http_response_code(404);
        echo json_encode(["error" => "Barang tidak ditemukan"]);
        exit;
    }

    $clean_name = cleanName($barang['nama_barang'] ?? 'barang');
    if (empty($clean_name)) {
        $clean_name = "barang";
    }
    $filename = $clean_name . "_" . $barang_id . ".webp";
    $target_dir = __DIR__ . "/upload/";

    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    $path = $target_dir . $filename;

    // 1. Download gambar dulu ke memori (Validasi URL sebelum hapus data lama)
    $image_content = @file_get_contents($image_url);
    if ($image_content === false) {
        http_response_code(400);
        echo json_encode(["error" => "Gagal mengambil gambar dari URL"]);
        exit;
    }

    // Validasi & Buat Resource Gambar untuk Konversi
    $im = @imagecreatefromstring($image_content);
    if ($im === false) {
        http_response_code(400);
        echo json_encode(["error" => "URL tidak berisi gambar yang valid"]);
        exit;
    }

    // 2. Hapus Data & File Lama (Clean Slate)
    // Ambil daftar gambar lama untuk dihapus filenya
    $q_old = $conn->prepare("SELECT nama_gambar FROM gambar WHERE barang_id=?");
    $q_old->bind_param("i", $barang_id);
    $q_old->execute();
    $res_old = $q_old->get_result();

    while ($row_old = $res_old->fetch_assoc()) {
        $path_old = $target_dir . $row_old['nama_gambar'];
        if (file_exists($path_old)) {
            unlink($path_old);
        }
    }
    $q_old->close();

    // Hapus record di database (Pastikan ini berhasil sebelum insert)
    $del_stmt = $conn->prepare("DELETE FROM gambar WHERE barang_id=?");
    $del_stmt->bind_param("i", $barang_id);
    if (!$del_stmt->execute()) {
        // Jika gagal hapus, jangan lanjut insert
        http_response_code(500);
        echo json_encode(["error" => "Gagal menghapus data lama"]);
        exit;
    }
    $del_stmt->close();

    // 3. Simpan File Baru & Insert DB
    // Simpan sebagai WebP (Quality 80)
    if (!function_exists('imagewebp')) {
        http_response_code(500);
        echo json_encode(["error" => "Fungsi imagewebp tidak tersedia di PHP ini"]);
        exit;
    }

    if (!imagewebp($im, $path, 80)) {
        imagedestroy($im);
        http_response_code(500);
        echo json_encode(["error" => "Gagal menyimpan gambar (Cek permission folder)"]);
        exit;
    }
    imagedestroy($im);

    $stmt = $conn->prepare("INSERT INTO gambar (barang_id, nama_gambar) VALUES (?, ?)");
    $stmt->bind_param("is", $barang_id, $filename);
    $stmt->execute();
    $stmt->close();

    echo json_encode([
        "success" => true,
        "gambar_url" => $baseUrl . "/upload/" . $filename
    ]);
    exit;
}

/* =========================
   DEFAULT
========================= */
http_response_code(404);
echo json_encode(["error" => "Endpoint tidak ditemukan"]);
