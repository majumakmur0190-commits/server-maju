<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include 'db.php';

// Mendapatkan metode HTTP
$method = $_SERVER['REQUEST_METHOD'];

// API untuk Edit Barcode
if ($method == 'POST' && isset($_GET['action']) && $_GET['action'] == 'update-barcode') {
    // Ambil data dari JSON atau x-www-form-urlencoded
    if ($_SERVER['CONTENT_TYPE'] === "application/json") {
        $inputData = json_decode(file_get_contents('php://input'), true);
    } else {
        $inputData = $_POST;
    }

    // Validasi data yang diperlukan
    if (!isset($inputData['barang_id'], $inputData['barcode'])) {
        header("HTTP/1.1 400 Bad Request");
        echo json_encode(["message" => "Data tidak lengkap."]);
        exit;
    }

    $barang_id = $inputData['barang_id'];
    $barcode = $inputData['barcode'];

    // Query untuk update barcode berdasarkan barang_id
    $sql = "UPDATE barang SET barcode = ? WHERE barang_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $barcode, $barang_id);

    if ($stmt->execute()) {
        echo json_encode(["message" => "Barcode berhasil diperbarui."]);
    } else {
        header("HTTP/1.1 500 Internal Server Error");
        echo json_encode(["message" => "Gagal memperbarui barcode."]);
    }

    $stmt->close();
}

// API untuk Menampilkan Semua Barang
elseif ($method == 'GET' && isset($_GET['action']) && $_GET['action'] == 'get-all-barang') {
    // Query untuk mendapatkan semua barang
    $sql = "SELECT * FROM barang WHERE limit 1";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $barangList = [];
        while ($row = $result->fetch_assoc()) {
            $barangList[] = $row;
        }
        echo json_encode($barangList);
    } else {
        header("HTTP/1.1 404 Not Found");
        echo json_encode(["message" => "Tidak ada barang ditemukan."]);
    }
}

// API untuk Menampilkan Barang Berdasarkan barang_id
elseif ($method == 'GET' && isset($_GET['action']) && $_GET['action'] == 'get-barang') {
    // Pastikan barang_id ada di query parameter
    if (!isset($_GET['barang_id'])) {
        header("HTTP/1.1 400 Bad Request");
        echo json_encode(["message" => "barang_id tidak ditemukan."]);
        exit;
    }

    $barang_id = $_GET['barang_id'];

    // Query untuk mendapatkan data barang berdasarkan barang_id
    $sql = "SELECT * FROM barang WHERE barang_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $barang_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $barang = $result->fetch_assoc();
        echo json_encode($barang);
    } else {
        header("HTTP/1.1 404 Not Found");
        echo json_encode(["message" => "Barang tidak ditemukan."]);
    }

    $stmt->close();
}

// API untuk Mencari Barang Berdasarkan nama_barang
elseif ($method == 'POST' && isset($_GET['action']) && $_GET['action'] == 'search-barang') {
    // Ambil data dari JSON atau x-www-form-urlencoded
    if ($_SERVER['CONTENT_TYPE'] === "application/json") {
        $inputData = json_decode(file_get_contents('php://input'), true);
    } else {
        $inputData = $_POST;
    }

    // Validasi jika nama_barang tidak ada
    if (!isset($inputData['nama_barang'])) {
        header("HTTP/1.1 400 Bad Request");
        echo json_encode(["message" => "Nama barang tidak ditemukan."]);
        exit;
    }

    $nama_barang = $inputData['nama_barang'];

    // Query untuk mencari barang berdasarkan nama_barang
    $sql = "SELECT * FROM barang WHERE nama_barang LIKE ?";
    $stmt = $conn->prepare($sql);
    $searchTerm = "%$nama_barang%"; // Menambahkan wildcard untuk pencarian
    $stmt->bind_param("s", $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $barangList = [];
        while ($row = $result->fetch_assoc()) {
            $barangList[] = $row;
        }
        echo json_encode($barangList);
    } else {
        header("HTTP/1.1 404 Not Found");
        echo json_encode(["message" => "Barang tidak ditemukan."]);
    }

    $stmt->close();
}
// pencarian barang berdasar barcode
elseif ($method == 'POST' && isset($_GET['action']) && $_GET['action'] == 'search-barcode') {
    // Ambil data dari JSON atau x-www-form-urlencoded
    if ($_SERVER['CONTENT_TYPE'] === "application/json") {
        $inputData = json_decode(file_get_contents('php://input'), true);
    } else {
        $inputData = $_POST;
    }

    // Validasi jika barcode tidak ada
    if (!isset($inputData['barcode'])) {
        header("HTTP/1.1 400 Bad Request");
        echo json_encode(["message" => "Barcode tidak ditemukan."]);
        exit;
    }

    $barcode = $inputData['barcode'];

    // Query untuk mencari barang berdasarkan barcode
    $sql = "SELECT * FROM barang WHERE barcode = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $barcode);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $barangList = [];
        while ($row = $result->fetch_assoc()) {
            $barangList[] = $row;
        }
        echo json_encode($barangList);
    } else {
        header("HTTP/1.1 404 Not Found");
        echo json_encode(["message" => "Barang tidak ditemukan."]);
    }

    $stmt->close();
}

// Menutup koneksi database
$conn->close();
?>