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

include "db.php";

// Fungsi helper untuk konversi tipe data hasil query
function cast_data_types($row)
{
    if ($row) {
        $row['barang_id'] = intval($row['barang_id']);
        $row['kategori_id'] = intval($row['kategori_id']);
        $row['harga_hna'] = floatval($row['harga_hna']);
        $row['stok'] = intval($row['stok']);
        $row['aktif'] = intval($row['aktif']);
    }
    return $row;
}


$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents("php://input"), true);

switch ($method) {

    // ===================================================
    // 🔹 GET DATA
    // ===================================================
    case 'GET':
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $search = isset($_GET['search']) ? "%" . $_GET['search'] . "%" : '';
        $limit = 50;
        if ($id > 0) {
            $sql = "SELECT b.*, k.nama_kategori
                    FROM barang b
                    JOIN kategori k ON b.kategori_id = k.kategori_id
                    WHERE b.barang_id = ? AND b.aktif = 1 LIMIT  1";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
        } elseif (!empty($search)) {
            $sql = "SELECT b.*, k.nama_kategori
                    FROM barang b
                    JOIN kategori k ON b.kategori_id = k.kategori_id
                    WHERE (b.nama_barang LIKE ? OR b.barcode LIKE ? OR k.nama_kategori LIKE ?)
                    AND b.aktif = 1
                    ORDER BY b.barang_id  DESC LIMIT  1";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sss", $search, $search, $search);
        } else {
            $sql = "SELECT b.*, k.nama_kategori
                    FROM barang b
                    JOIN kategori k ON b.kategori_id = k.kategori_id
                    WHERE b.aktif = 1
                    ORDER BY b.barang_id DESC LIMIT  ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $limit);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        if ($id > 0) {
            $data = cast_data_types($result->fetch_assoc());
            if (!$data) {
                echo json_encode(["status" => "error", "message" => "Barang tidak ditemukan."]);
                exit;
            }
        } else {
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = cast_data_types($row);
            }

            if (empty($data)) {
                echo json_encode(["status" => "error", "message" => "Barang tidak ditemukan."]);
                exit;
            }
        }

        echo json_encode(["status" => "success", "data" => $data]);
        $stmt->close();
        break;


    // ===================================================
    // 🔹 METODE LAIN (ERROR)
    // ===================================================
    default:
        http_response_code(405);
        echo json_encode(["status" => "error", "message" => "Metode tidak diizinkan."]);
        break;
}

$conn->close();
?>