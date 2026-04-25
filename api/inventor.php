<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
include 'db.php';

// Fungsi untuk mengirim response JSON
function sendResponse($data)
{
    echo json_encode($data);
    exit;
}

// 1. Pencarian barang multi-kata (handal)
if (isset($_GET['cari'])) {

    $cari = trim($_GET['cari']);

    if ($cari === '') {
        sendResponse([
            "status" => "error",
            "message" => "Kata pencarian kosong"
        ]);
    }

    // Pecah kata berdasarkan spasi
    $keywords = preg_split('/\s+/', $cari);

    $where = [];
    $params = [];
    $types = '';

    foreach ($keywords as $kata) {
        $where[] = "nama_barang LIKE ?";
        $params[] = '%' . $kata . '%';
        $types .= 's';
    }

    $sql = "
        SELECT barang_id, barcode, nama_barang
        FROM barang
        WHERE " . implode(' AND ', $where) . "
        AND barcode = 0
        AND aktif = 1
        LIMIT 50
    ";

    $stmt = $conn->prepare($sql);

    // bind_param dinamis
    $stmt->bind_param($types, ...$params);

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }

        sendResponse([
            "status" => "success",
            "data" => $data
        ]);
    } else {
        sendResponse([
            "status" => "error",
            "message" => "Barang tidak ditemukan"
        ]);
    }

    $stmt->close();
}



// 2. Ambil barang berdasarkan ID
elseif (isset($_GET['id'])) {
    $barang_id = $_GET['id'];
    $sql = "SELECT * FROM barang WHERE barang_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $barang_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $barang = $result->fetch_assoc();
        sendResponse(["status" => "success", "data" => $barang]);
    } else {
        sendResponse(["status" => "error", "message" => "Barang tidak ditemukan."]);
    }

    $stmt->close();
}

// 3. Update barcode (gunakan POST)
elseif (isset($_POST['update']) && isset($_POST['barang_id']) && isset($_POST['barcode'])) {
    $barang_id = $_POST['barang_id'];
    $barcode = $_POST['barcode'];

    $sql = "UPDATE barang SET barcode = ? WHERE barang_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $barcode, $barang_id);

    if ($stmt->execute()) {
        sendResponse(["status" => "success", "message" => "Barcode berhasil diperbarui."]);
    } else {
        sendResponse(["status" => "error", "message" => "Gagal memperbarui barcode."]);
    }

    $stmt->close();
}

// 4. Ambil semua barang jika tidak ada parameter
else {
    $sql = "SELECT * FROM barang WHERE barcode = 0 LIMIT 50";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $barangList = [];
        while ($row = $result->fetch_assoc()) {
            $barangList[] = $row;
        }
        sendResponse(["status" => "success", "data" => $barangList]);
    } else {
        sendResponse(["status" => "error", "message" => "Tidak ada barang ditemukan."]);
    }
}

$conn->close();
?>